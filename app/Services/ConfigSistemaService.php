<?php

// app/Services/ConfigSistemaService.php
//
// SERVICIO CENTRAL DE CONFIGURACIÓN OPERATIVA DEL SISTEMA.
//
// ÚNICA puerta de entrada a la configuración (probabilidad F10,
// tolerancias de horario, exentos de revisión...). Nadie más lee
// la tabla configuracion_sistema ni config/sca_qr.php para estos
// parámetros: todo pasa por aquí.
//
// ¿POR QUÉ EXISTE? Antes los parámetros estaban hardcodeados:
//   - Tolerancias: constantes de AccesoService (30/15/30 min).
//   - Probabilidad F10 y exentos: config/sca_qr.php (con el bug
//     del default 20% cuando el SRS pide 10%).
// Cambiar cualquier margen requería editar código y desplegar.
// Ahora un Admin/Superadmin los ajusta desde /configuracion en
// caliente, con auditoría en Logs del sistema.
//
// CADENA DE LECTURA (3 niveles, a prueba de fallos):
//   1. Cache Laravel (todo el bloque en una sola entrada).
//   2. Tabla configuracion_sistema (SOLO desviaciones del default).
//   3. Default en código (catálogo DEFECTOS de abajo).
// Si la tabla no existe aún (migración sin correr) o el cache
// falla, el sistema se comporta EXACTAMENTE como antes del módulo.
//
// PATRÓN DE CACHE: réplica de PlantaCacheService/TipoEmpleadoCacheService
// (Cache::remember con TTL + Cache::forget al escribir).

namespace App\Services;

use App\Models\ConfiguracionSistema;
use App\Models\LogSistema;
use Illuminate\Support\Facades\Cache;

class ConfigSistemaService
{
    // Una sola entrada de cache para TODO el bloque: se lee completo
    // (son ≤ 6 claves), escribir/olvidar es O(1).
    const CACHE_KEY = 'sca_config_sistema';
    // 5 min: el flush explícito en guardar()/restaurar() es la vía
    // normal; el TTL solo cubre ediciones directas en BD.
    const CACHE_TTL = 300;

    // ================================================================
    // CATÁLOGO DE PARÁMETROS (ÚNICA fuente de defaults y metadatos)
    // ================================================================
    // Cada entrada define:
    //   valor       → default (lo que regresa si no hay desviación).
    //   tipo        → 'int' (entero con rango) | 'ids' (lista de IDs
    //                 separados por coma, ej: "1001,23846").
    //   min / max   → rango válido (solo 'int'); guardar() hace clamp
    //                 defensivo y el controlador valida antes.
    //   etiqueta    → nombre visible en la UI.
    //   descripcion→ texto de ayuda en la UI (QUÉ efecto tiene).
    //   seccion     → tarjeta de la vista / formulario que la edita.
    //   sufijo      → unidad mostrada en la UI (%, min, vacío).
    //
    // Para AGREGAR un parámetro nuevo en el futuro: basta registrarlo
    // aquí — la tabla, el cache, la vista y la validación lo toman
    // de este catálogo. No se toca el schema.
    // ================================================================
    const DEFECTOS = [
        // ---- Sección: revisión (F10) ----
        'probabilidad_revision' => [
            'valor'       => 10,      // SRS F10: 10% de los accesos
            'tipo'        => 'int',
            'min'         => 0,
            'max'         => 100,
            'etiqueta'    => 'Probabilidad de revisión',
            'descripcion' => 'Porcentaje de escaneos de operativos/administrativos que caen en revisión física aleatoria. Se recomienda 10%. Advertencia: 0 desactiva la revisión; 100 satura al guardia (TODOS los escaneos quedan pendientes). Aplica a cada escaneo (entrada y salida).',
            'seccion'     => 'revision',
            'sufijo'      => '%',
        ],

        // ---- Sección: tolerancias ----
        'tolerancia_entrada_antes' => [
            'valor'       => 30,
            'tipo'        => 'int',
            'min'         => 0,
            'max'         => 120,
            'etiqueta'    => 'Llegada temprana permitida',
            'descripcion' => 'Minutos ANTES de su hora de entrada en que una persona puede entrar sin permiso ni justificación (ej: turno 07:00 → desde 06:30 pasa directo). Llegar más temprano requiere permiso de entrada o deniega.',
            'seccion'     => 'tolerancias',
            'sufijo'      => ' min',
        ],
        'tolerancia_entrada_despues' => [
            'valor'       => 15,
            'tipo'        => 'int',
            'min'         => 0,
            'max'         => 120,
            'etiqueta'    => 'Tolerancia de retardo',
            'descripcion' => 'Minutos DESPUÉS de su hora de entrada que pasan sin justificación (ej: turno 07:00 → hasta 07:15 directo). Llegar más tarde pasa, pero el guardia debe justificar la llegada tardía. 0 = cualquier minuto de retardo se justifica.',
            'seccion'     => 'tolerancias',
            'sufijo'      => ' min',
        ],
        'tolerancia_salida_despues' => [
            'valor'       => 30,
            'tipo'        => 'int',
            'min'         => 0,
            'max'         => 120,
            'etiqueta'    => 'Tolerancia de salida tardía',
            'descripcion' => 'Minutos DESPUÉS de su hora de salida que se registran sin justificación (ej: salida 15:00 → hasta 15:30 directo). Salir más tarde pasa, pero el guardia debe justificar la salida tardía.',
            'seccion'     => 'tolerancias',
            'sufijo'      => ' min',
        ],
    ];

    // ================================================================
    // SECCIONES (tarjetas de la vista / formularios)
    // ================================================================
    const SECCIONES = [
        'revision' => [
            'titulo'      => 'Revisión aleatoria',
            'descripcion' => 'Frecuencia y comportamiento de las revisiones físicas aleatorias',
            'icono'       => 'bi-shield-exclamation',
        ],
        'tolerancias' => [
            'titulo'      => 'Tolerancias de horario',
            'descripcion' => 'Márgenes (en minutos) alrededor del turno dentro de los cuales un escaneo pasa directo, sin justificación del guardia.',
            'icono'       => 'bi-clock-history',
        ],
    ];

    // ================================================================
    // LECTURA
    // ================================================================

    /**
     * Valor CRUDO (string) de una clave, con la cadena cache → BD → default.
     * Los consumidores deben usar obtenerInt()/obtenerArray() (tipados).
     */
    public static function obtener(string $clave): ?string
    {
        // Nivel 3: default del catálogo.
        $default = self::DEFECTOS[$clave]['valor'] ?? null;

        // Niveles 1+2: bloque cacheado; si la tabla aún no existe
        // (migración sin correr) devolvemos [] y cae al default —
        // el sitio NO truena por un módulo a medio instalar.
        $bloque = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            try {
                return ConfiguracionSistema::query()->pluck('valor', 'clave')->toArray();
            } catch (\Throwable $e) {
                return [];
            }
        });

        return $bloque[$clave] ?? (string) $default;
    }

    /**
     * Valor INT de una clave, con clamp defensivo al rango del catálogo.
     * Esto garantiza que un valor corrupto en BD (texto, fuera de rango)
     * NUNCA rompa la lógica de acceso: se recorta al mínimo/máximo.
     */
    public static function obtenerInt(string $clave): int
    {
        $meta   = self::DEFECTOS[$clave] ?? null;
        $valor  = (int) self::obtener($clave);

        if ($meta !== null && $meta['tipo'] === 'int') {
            $valor = max($meta['min'], min($meta['max'], $valor));
        }
        return $valor;
    }

    /**
     * Lista de IDs (array de int) para claves tipo 'ids'.
     * "1001, 23846 ,1001,x" → [1001, 23846] (trim, solo numéricos,
     * sin duplicados). Siempre devuelve array (vacío si no hay nada).
     */
    public static function obtenerArray(string $clave): array
    {
        return self::obtenerArrayDesdeTexto((string) self::obtener($clave));
    }

    /**
     * Conveniencia para AccesoService::validarHorario(): las 3
     * tolerancias ya convertidas a SEGUNDOS (como las usaba el
     * código de timestamps). Una sola llamada → un solo hit de cache
     * para las 3 claves.
     */
    public static function toleranciasSegundos(): array
    {
        return [
            'entrada_antes'   => self::obtenerInt('tolerancia_entrada_antes') * 60,
            'entrada_despues' => self::obtenerInt('tolerancia_entrada_despues') * 60,
            'salida_despues'  => self::obtenerInt('tolerancia_salida_despues') * 60,
        ];
    }

    // ================================================================
    // ESCRITURA (solo vía ConfiguracionController)
    // ================================================================

    /**
     * Guarda (o restaura) una clave.
     *
     * Reglas de oro:
     *   1. Clave debe existir en DEFECTOS (whitelist: imposible
     *      inyectar claves arbitrarias desde el formulario).
     *   2. Normaliza por tipo: int → cast + clamp; ids → limpiar
     *      lista (trim/numéricos/únicos/ordenados).
     *   3. Si el valor normalizado ES el default → se BORRA la fila
     *      (la tabla solo guarda desviaciones; el badge "por defecto"
     *      de la vista siempre es veraz).
     *   4. Siempre: flush de cache + auditoría en LogSistema con
     *      valor viejo → nuevo.
     */
    public static function guardar(string $clave, $valor, ?int $usuarioId = null): void
    {
        $meta = self::DEFECTOS[$clave] ?? null;
        if ($meta === null) {
            throw new \InvalidArgumentException("Clave de configuración no permitida: {$clave}");
        }

        $valorNormalizado = self::normalizar($clave, $valor);

        // Auditoría: capturar el valor VIEJO antes de escribir.
        $viejo = self::obtener($clave);

        if ($valorNormalizado === (string) $meta['valor']) {
            // Igual al default → eliminar desviación.
            ConfiguracionSistema::where('clave', $clave)->delete();
        } else {
            ConfiguracionSistema::updateOrCreate(
                ['clave' => $clave],
                ['valor' => $valorNormalizado, 'actualizado_por' => $usuarioId]
            );
        }

        // El próximo escaneo lee el valor nuevo, no el cacheado.
        Cache::forget(self::CACHE_KEY);

        LogSistema::registrar(
            'CONFIG_ACTUALIZADA',
            "{$clave}: {$viejo} → {$valorNormalizado}"
            . ($usuarioId ? " (usuario #{$usuarioId})" : '')
        );
    }

    /**
     * Restaura UNA clave a su default (borra la fila + cache + log).
     */
    public static function restaurar(string $clave, ?int $usuarioId = null): void
    {
        $meta = self::DEFECTOS[$clave] ?? null;
        if ($meta === null) {
            throw new \InvalidArgumentException("Clave de configuración no permitida: {$clave}");
        }

        $viejo = self::obtener($clave);

        ConfiguracionSistema::where('clave', $clave)->delete();
        Cache::forget(self::CACHE_KEY);

        LogSistema::registrar(
            'CONFIG_RESTAURADA',
            "{$clave}: {$viejo} → {$meta['valor']} (default)"
            . ($usuarioId ? " (usuario #{$usuarioId})" : '')
        );
    }

    /**
     * Normalización por tipo. PÚBLICO porque el controlador lo usa
     * para comparar el input del formulario contra el default.
     */
    public static function normalizar(string $clave, $valor): string
    {
        $meta = self::DEFECTOS[$clave];

        if ($meta['tipo'] === 'int') {
            $entero = (int) $valor;
            // Clamp defensivo: aunque el controlador ya validó el
            // rango, aquí se garantiza a nivel de servicio.
            $entero = max($meta['min'], min($meta['max'], $entero));
            return (string) $entero;
        }

        if ($meta['tipo'] === 'ids') {
            $ids = self::obtenerArrayDesdeTexto((string) $valor);
            sort($ids);
            return implode(',', $ids);
        }

        return trim((string) $valor);
    }

    /** "1001, 23846 ,x,1001" → [1001, 23846]. Compartido por leer/escribir. */
    private static function obtenerArrayDesdeTexto(string $texto): array
    {
        if (trim($texto) === '') {
            return [];
        }
        return array_values(array_unique(
            array_map('intval',
                array_filter(
                    array_map('trim', explode(',', $texto)),
                    fn($id) => $id !== '' && is_numeric($id)
                )
            )
        ));
    }

    // ================================================================
    // HELPERS PARA EL CONTROLADOR / VISTA
    // ================================================================

    /** Metadatos de una clave (o null si no existe). */
    public static function meta(string $clave): ?array
    {
        return self::DEFECTOS[$clave] ?? null;
    }

    /** Claves que pertenecen a una sección (orden del catálogo). */
    public static function clavesDeSeccion(string $seccion): array
    {
        return array_keys(array_filter(
            self::DEFECTOS,
            fn($meta) => $meta['seccion'] === $seccion
        ));
    }

    /**
     * Snapshot completo para la vista: por cada clave del catálogo,
     * su valor actual, si es el default, y quién/cuándo lo modificó.
     * Una sola consulta (eager del actualizador) para todo.
     */
    public static function todo(): array
    {
        try {
            $filas = ConfiguracionSistema::with('actualizador')->get()->keyBy('clave');
        } catch (\Throwable $e) {
            $filas = collect(); // tabla sin migrar → todo default
        }

        $salida = [];
        foreach (self::DEFECTOS as $clave => $meta) {
            $fila = $filas->get($clave);

            $salida[$clave] = [
                'meta'               => $meta,
                // Valor listo para el <input> (int tal cual / ids como texto).
                'valor_input'        => $fila?->valor ?? (string) $meta['valor'],
                'es_default'         => $fila === null,
                'actualizado_nombre' => $fila?->actualizador?->username,
                'updated_at'         => $fila?->updated_at,
            ];
        }
        return $salida;
    }
}