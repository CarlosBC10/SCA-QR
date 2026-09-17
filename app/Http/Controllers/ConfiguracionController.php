<?php

// app/Http/Controllers/ConfiguracionController.php
//
// MÓDULO DE CONFIGURACIÓN DEL SISTEMA (Admin/Superadmin).
//
// Acciones:
//   index()     → pantalla /configuracion (tarjetas por sección).
//   update()    → guarda una sección completa del formulario.
//   restaurar() → devuelve una sección a sus valores por defecto.
//
// SEGURIDAD (3 capas):
//   1. Ruta con middleware role:Superadmin,Admin (Aprobador/Guardia
//      ni ven el menú ni pueden abrir la URL).
//   2. Sección validada contra SECCIONES → nadie inyecta una
//      sección inexistente.
//   3. ConfigSistemaService::guardar() solo acepta claves del
//      catálogo DEFECTOS → imposible escribir claves arbitrarias.
//
// AUDITORÍA: cada cambio queda en Logs del sistema con acción
// CONFIG_ACTUALIZADA / CONFIG_RESTAURADA (lo hace el service).

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ConfigSistemaService;

class ConfiguracionController extends Controller
{
    /**
     * Pantalla principal: snapshot de todos los parámetros
     * (valor actual, default, quién modificó) agrupados por sección.
     */
    public function index()
    {
        $config    = ConfigSistemaService::todo();
        $secciones = ConfigSistemaService::SECCIONES;

        return view('configuracion.index', compact('config', 'secciones'));
    }

    /**
     * Guarda los campos de UNA sección (cada tarjeta de la vista es
     * un formulario independiente con su hidden "seccion").
     */
    public function update(Request $request)
    {
        // ── Capa 2: sección debe existir en el catálogo ──────────
        $datos = $request->validate([
            'seccion' => ['required', 'string', 'in:' . implode(',', array_keys(ConfigSistemaService::SECCIONES))],
        ]);
        $seccion = $datos['seccion'];
        $claves  = ConfigSistemaService::clavesDeSeccion($seccion);

        // ── Reglas de validación dinámicas desde el catálogo ─────
        // int  → required|integer|min|max (rango del catálogo).
        // ids  → vacío O lista de números separados por coma.
        $reglas    = [];
        $atributos = [];
        foreach ($claves as $clave) {
            $meta = ConfigSistemaService::meta($clave);
            $atributos[$clave] = $meta['etiqueta'];

            if ($meta['tipo'] === 'int') {
                $reglas[$clave] = ['required', 'integer', "min:{$meta['min']}", "max:{$meta['max']}"];
            } else {
                // Vacío permitido (= nadie exento); o "1001,23846,...".
                $reglas[$clave] = ['nullable', 'regex:/^$|^\d+(\s*,\s*\d+)*$/'];
            }
        }

        $request->validate($reglas, [], $atributos);

        // ── Guardar clave por clave (el service normaliza, clampa,
        //    flushea cache y registra auditoría) ──────────────────
        $usuarioId = auth()->user()?->id_usuario_sca;

        // Capturar los valores VIEJOS antes de guardar (una sola
        // lectura; guardar() hace flush de cache por cada clave) para
        // poder mostrar el cambio real en el modal de éxito.
        $viejos = [];
        foreach ($claves as $clave) {
            $viejos[$clave] = ConfigSistemaService::obtener($clave);
        }

        // Guardar + armar el detalle del modal: "Etiqueta: viejo → nuevo".
        $items = [];
        foreach ($claves as $clave) {
            $meta   = ConfigSistemaService::meta($clave);
            $nuevo  = ConfigSistemaService::normalizar($clave, $request->input($clave, ''));

            ConfigSistemaService::guardar($clave, $request->input($clave, ''), $usuarioId);

            $items[] = $viejos[$clave] === $nuevo
                ? $meta['etiqueta'] . ': <strong>' . $this->valorLegible($meta, $nuevo) . '</strong> (sin cambios)'
                : $meta['etiqueta'] . ': ' . $this->valorLegible($meta, $viejos[$clave])
                    . ' → <strong>' . $this->valorLegible($meta, $nuevo) . '</strong>';
        }

        $titulo = ConfigSistemaService::SECCIONES[$seccion]['titulo'] ?? 'Configuración';

        // SCA-MODAL: flash estructurado. El layout (layouts/app.blade.php)
        // lo lee al cargar la página y dispara ScaModal.success() con el
        // bloque de detalles. sca_modal_flash() es el helper global del
        // sistema de modales (app/helpers.php), el mismo que usan los
        // demás controllers del sistema.
        return redirect()
            ->route('configuracion.index')
            ->with('sca_modal', sca_modal_flash(
                'success',
                "Sección «{$titulo}» guardada",
                'Los cambios aplican en caliente desde el próximo escaneo y quedaron registrados en <strong>Logs del sistema</strong>.',
                [[
                    'titulo' => 'Valores guardados',
                    'icono'  => 'bi-sliders',
                    'items'  => $items,
                ]]
            ));
    }

    /**
     * Restaura UNA sección completa a sus valores por defecto
     * (borra las filas, limpia cache y lo registra en el log).
     */
    public function restaurar(Request $request)
    {
        $datos = $request->validate([
            'seccion' => ['required', 'string', 'in:' . implode(',', array_keys(ConfigSistemaService::SECCIONES))],
        ]);
        $seccion   = $datos['seccion'];
        $usuarioId = auth()->user()?->id_usuario_sca;

        foreach (ConfigSistemaService::clavesDeSeccion($seccion) as $clave) {
            ConfigSistemaService::restaurar($clave, $usuarioId);
        }

        $titulo = ConfigSistemaService::SECCIONES[$seccion]['titulo'] ?? 'Configuración';

        // Detalle del modal: a qué valores volvió cada parámetro.
        $items = [];
        foreach (ConfigSistemaService::clavesDeSeccion($seccion) as $clave) {
            $meta   = ConfigSistemaService::meta($clave);
            $items[] = $meta['etiqueta'] . ': <strong>' . $this->valorLegible($meta, (string) $meta['valor']) . '</strong>';
        }

        // SCA-MODAL: flash estructurado → el layout dispara ScaModal.info()
        // (azul: es informativo, no un error ni una advertencia).
        return redirect()
            ->route('configuracion.index')
            ->with('sca_modal', sca_modal_flash(
                'info',
                "Sección «{$titulo}» restaurada",
                'Los parámetros volvieron a sus valores de fábrica. El cambio quedó registrado en <strong>Logs del sistema</strong>.',
                [[
                    'titulo' => 'Valores por defecto aplicados',
                    'icono'  => 'bi-arrow-counterclockwise',
                    'items'  => $items,
                ]]
            ));
    }

    /**
     * Valor listo para mostrar en el modal ScaModal (SCA-MODAL):
     * ints con su sufijo ("30 min"), listas vacías como "(nadie)".
     */
    private function valorLegible(array $meta, string $valor): string
    {
        if ($meta['tipo'] === 'ids' && trim($valor) === '') {
            return '(nadie)';
        }
        return $valor . $meta['sufijo'];
    }
}