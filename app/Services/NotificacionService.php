<?php
// app/Services/NotificacionService.php
//
// SERVICIO DE NOTIFICACIONES IN-APP (M5).
//
// CAMBIOS V4:
//   - Acepta SolicitudInt|SolicitudExt (unión de tipos) en todos los métodos.
//   - FKs renombradas: planta_origen_id → id_planta_origen, etc.
//   - 'usuario_id' → 'id_usuario_sca' en Notificacion::create.
//   - 'where activo = true' → 'whereNull(fecha_baja)' (el usuario activo no tiene fecha_baja).
//   - Corregido typo '$ensaje' → '$mensaje'.
//   - Rutas unificadas: solicitudes.show.int y solicitudes.show.ext.
//   - Helper obtenerAprobadoresDePlanta() optimizado.
//
// LÓGICA:
//   - solicitudCreadaYAutoAprobada(): notifica a otros aprobadores/admins.
//   - solicitudAprobadaTotalmente(): notifica al creador + otros aprobadores/admins.
//   - solicitudAprobadaParcialmente(): notifica al creador + aprobadores del siguiente paso.
//   - solicitudRechazada(): notifica al creador.
//   - nuevaSolicitudPendiente(): notifica a aprobadores de la planta correspondiente.
//
// NOTA: Los métodos reciben $solicitud que puede ser SolicitudInt o SolicitudExt.
//       PHP no soporta union types en parámetros antes de 8.0, pero como el
//       proyecto usa PHP 8.4, usamos union types explícitos.

namespace App\Services;

use App\Models\Notificacion;
use App\Models\UsuarioSca;
use App\Models\SolicitudInt;
use App\Models\SolicitudExt;
use App\Models\LogSistema;
use Illuminate\Support\Collection;

class NotificacionService
{
    // ============================================================
    // HELPER BASE: enviar una notificación
    // ============================================================

    /**
     * Crea una notificación para un usuario.
     *
     * Cambio V4: 'usuario_id' → 'id_usuario_sca'.
     */
    public static function enviar(int $usuarioId, string $tipo, string $titulo, string $mensaje, ?string $url = null): void
    {
        Notificacion::create([
            'id_usuario_sca' => $usuarioId,
            'tipo'           => $tipo,
            'titulo'         => $titulo,
            'mensaje'        => $mensaje,  // corregido typo '$ensaje'
            'url'            => $url,
            'leida'          => false,
        ]);
    }

    // ============================================================
    // HELPER: obtener aprobadores de una planta
    // ============================================================

    /**
     * Devuelve los usuarios Aprobador/Admin/Superadmin que pertenecen a una planta.
     *
     * Cambios V4:
     *   - 'where activo = true' → 'whereNull(fecha_baja)'.
     *   - Filtrado por planta: derivada del empleado (via ext_sca.id_planta) o del guardia.
     *
     * Nota: Los Admin/Superadmin sin planta asignada se incluyen SIEMPRE (ven todas).
     */
    private static function obtenerAprobadoresDePlanta(int $plantaId): Collection
    {
        // Cargar todos los aprobadores/admins activos
        $usuarios = UsuarioSca::whereHas('rol', fn($q) => $q->whereIn('nombre', ['Aprobador', 'Admin', 'Superadmin']))
            ->whereNull('fecha_baja')
            ->get();

        // Filtrar por planta: la planta del usuario (derivada de su empleado o guardia)
        return $usuarios->filter(function ($u) use ($plantaId) {
            // Admin/Superadmin sin planta asignada → ven todas
            if (($u->isAdmin() || $u->isSuperadmin()) && $u->planta_id === null) {
                return true;
            }
            return $u->planta_id === $plantaId;
        });
    }

    // ============================================================
    // HELPER: obtener la URL de detalle de una solicitud
    // ============================================================

    /**
     * Devuelve la ruta de detalle según el tipo de solicitud.
     * - SolicitudInt → solicitudes.show.int
     * - SolicitudExt → solicitudes.show.ext
     */
    private static function urlShow(SolicitudInt|SolicitudExt $solicitud): string
    {
        if ($solicitud instanceof SolicitudInt) {
            return route('solicitudes.show.int', $solicitud->id_solicitud_int);
        }
        return route('solicitudes.show.ext', $solicitud->id_solicitud_ext);
    }

    // ============================================================
    // HELPER: obtener el ID de la solicitud (para mensajes)
    // ============================================================

    /**
     * Devuelve el ID de la solicitud (int o ext) para mostrar en mensajes.
     */
    private static function idSolicitud(SolicitudInt|SolicitudExt $solicitud): int
    {
        return $solicitud instanceof SolicitudInt
            ? $solicitud->id_solicitud_int
            : $solicitud->id_solicitud_ext;
    }

    // ============================================================
    // HELPER: obtener las plantas involucradas en una solicitud
    // ============================================================

    /**
     * Devuelve un array con los IDs de planta involucrados en la solicitud.
     * - SolicitudInt: [id_planta_origen, id_planta_destino] (sin duplicados).
     * - SolicitudExt: [id_planta_destino].
     */
    private static function plantasInvolucradas(SolicitudInt|SolicitudExt $solicitud): array
    {
        if ($solicitud instanceof SolicitudInt) {
            $plantas = [$solicitud->id_planta_origen, $solicitud->id_planta_destino];
            // Para pase_salida, origen = destino, pero unique() lo resuelve
            return array_values(array_unique($plantas));
        }
        // SolicitudExt
        return [$solicitud->id_planta_destino];
    }

    // ============================================================
    // CASO A: Solicitud creada y auto-aprobada por Aprobador+
    // ============================================================

    /**
     * Un Aprobador/Admin/Superadmin CREÓ una solicitud auto-aprobada.
     * Se notifica a los demás aprobadores de la(s) planta(s) y a otros admins/superadmins.
     */
    public static function solicitudCreadaYAutoAprobada(SolicitudInt|SolicitudExt $solicitud, $creador): void
    {
        $tipoLabel = self::tipoLabel($solicitud);
        $nombreCreador = $creador->nombre_completo;
        $url = self::urlShow($solicitud);
        $idSol = self::idSolicitud($solicitud);

        // Recolectar usuarios a notificar
        $usuariosANotificar = collect();

        foreach (self::plantasInvolucradas($solicitud) as $plantaId) {
            $usuariosANotificar = $usuariosANotificar->merge(self::obtenerAprobadoresDePlanta($plantaId));
        }

        // Agregar todos los Admin/Superadmin (ven todas las plantas)
        $admins = UsuarioSca::whereHas('rol', fn($q) => $q->whereIn('nombre', ['Admin', 'Superadmin']))
            ->whereNull('fecha_baja')
            ->get();
        $usuariosANotificar = $usuariosANotificar->merge($admins);

        // Deduplicar y excluir al creador
        $usuariosANotificar = $usuariosANotificar
            ->unique('id_usuario_sca')
            ->where('id_usuario_sca', '!=', $creador->id_usuario_sca);

        foreach ($usuariosANotificar as $usuario) {
            self::enviar(
                $usuario->id_usuario_sca,
                'solicitud_creada_auto',
                '📋 Nueva Solicitud Auto-Aprobada',
                "{$nombreCreador} ha generado y aprobado un {$tipoLabel} (Solicitud #{$idSol}).",
                $url
            );
        }

        LogSistema::registrar(
            'SOLICITUD_AUTO_APROBADA',
            "Solicitud #{$idSol} creada y auto-aprobada por {$nombreCreador}"
        );
    }

    // ============================================================
    // CASO B: Solicitud aprobada COMPLETAMENTE
    // ============================================================

    /**
     * Un Aprobador/Admin/Superadmin aprobó COMPLETAMENTE una solicitud pendiente.
     * Notifica al creador + otros aprobadores/admins.
     */
    public static function solicitudAprobadaTotalmente(SolicitudInt|SolicitudExt $solicitud, $aprobador): void
    {
        $nombreAprobador = $aprobador->nombre_completo;
        $url = self::urlShow($solicitud);
        $idSol = self::idSolicitud($solicitud);

        // 1. Notificar al creador
        $creador = self::obtenerUsuarioCreador($solicitud);
        if ($creador) {
            self::enviar(
                $creador->id_usuario_sca,
                'solicitud_aprobada',
                '✅ Solicitud Aprobada',
                "Tu Solicitud #{$idSol} fue aprobada por {$nombreAprobador}.",
                $url
            );
        }

        // 2. Notificar a aprobadores de ambas plantas + otros admins
        $usuariosANotificar = collect();
        foreach (self::plantasInvolucradas($solicitud) as $plantaId) {
            $usuariosANotificar = $usuariosANotificar->merge(self::obtenerAprobadoresDePlanta($plantaId));
        }

        $admins = UsuarioSca::whereHas('rol', fn($q) => $q->whereIn('nombre', ['Admin', 'Superadmin']))
            ->whereNull('fecha_baja')
            ->get();
        $usuariosANotificar = $usuariosANotificar->merge($admins);

        // Excluir al aprobador y al creador
        $usuariosANotificar = $usuariosANotificar
            ->unique('id_usuario_sca')
            ->where('id_usuario_sca', '!=', $aprobador->id_usuario_sca);
        if ($creador) {
            $usuariosANotificar = $usuariosANotificar->where('id_usuario_sca', '!=', $creador->id_usuario_sca);
        }

        foreach ($usuariosANotificar as $usuario) {
            self::enviar(
                $usuario->id_usuario_sca,
                'solicitud_aprobada_tercero',
                '✅ Solicitud Aprobada',
                "{$nombreAprobador} ha aprobado la Solicitud #{$idSol}.",
                $url
            );
        }

        LogSistema::registrar(
            'APROBAR_SOLICITUD_TOTAL',
            "Solicitud #{$idSol} aprobada completamente por {$nombreAprobador}"
        );
    }

    // ============================================================
    // CASO C: Solicitud aprobada en un paso (origen o destino)
    // ============================================================

    /**
     * Un Aprobador aprobó su paso (origen o destino).
     * Notifica al creador +, si fue origen, a los aprobadores de DESTINO.
     */
    public static function solicitudAprobadaParcialmente(SolicitudInt|SolicitudExt $solicitud, $aprobador, string $paso): void
    {
        $nombreAprobador = $aprobador->nombre_completo;
        $url = self::urlShow($solicitud);
        $idSol = self::idSolicitud($solicitud);

        // 1. Notificar al creador
        $creador = self::obtenerUsuarioCreador($solicitud);
        if ($creador) {
            self::enviar(
                $creador->id_usuario_sca,
                'solicitud_aprobada_parcial',
                '✅ Solicitud Actualizada',
                "Tu Solicitud #{$idSol} fue aprobada en {$paso} por {$nombreAprobador}.",
                $url
            );
        }

        // 2. Si el paso fue "origen" o "origen_parcial", notificar a los aprobadores de DESTINO
        //    (para que sepan que les toca aprobar)
        if (in_array($paso, ['origen', 'origen_parcial']) && $solicitud instanceof SolicitudInt) {
            $aprobadoresDestino = self::obtenerAprobadoresDePlanta($solicitud->id_planta_destino);
            foreach ($aprobadoresDestino as $usuario) {
                if ($usuario->id_usuario_sca !== $aprobador->id_usuario_sca) {
                    self::enviar(
                        $usuario->id_usuario_sca,
                        'nueva_solicitud',
                        '🔔 Solicitud Pendiente',
                        "La Solicitud #{$idSol} requiere tu aprobación de destino.",
                        $url
                    );
                }
            }
        }

        LogSistema::registrar(
            'APROBAR_SOLICITUD_PARCIAL',
            "Solicitud #{$idSol} aprobada en {$paso} por {$nombreAprobador}"
        );
    }

    // ============================================================
    // CASO D: Solicitud rechazada
    // ============================================================

    /**
     * Cualquier rechazo (total o parcial).
     * Notifica al creador con el motivo.
     */
    public static function solicitudRechazada(SolicitudInt|SolicitudExt $solicitud, $aprobador, string $motivo = ''): void
    {
        $creador = self::obtenerUsuarioCreador($solicitud);
        if (!$creador) return;

        $nombreAprobador = $aprobador->nombre_completo ?? 'Sistema';
        $url = self::urlShow($solicitud);
        $idSol = self::idSolicitud($solicitud);

        self::enviar(
            $creador->id_usuario_sca,
            'solicitud_rechazada',
            '❌ Solicitud Rechazada',
            "Tu Solicitud #{$idSol} fue rechazada por {$nombreAprobador}. {$motivo}",
            $url
        );
    }

    // ============================================================
    // V10.3.5-fix · DESTINATARIOS DE CORREO (alcance por planta)
    // ============================================================
    //
    // ANTES (V7): el correo de "nueva solicitud" se enviaba a TODOS los
    // usuarios Aprobador/Admin/Superadmin del sistema, SIN filtro de
    // planta → aprobadores de plantas ajenas recibían correos que no
    // les correspondían (hallazgo del usuario).
    //
    // Alcance CORRECTO (espejo de las notificaciones in-app):
    //   · Pendiente creada por Empleado → aprobadores de la planta
    //     RESPONSABLE de aprobar (origen si pendiente_origen; destino
    //     en el resto) + todos los Admin/Superadmin.
    //   · Auto-aprobada al crearla (creador Aprobador+) → aprobadores
    //     de las plantas involucradas + Admin/Superadmin, sin el creador.
    //   · Aprobada directamente por Admin/Superadmin (resuelve una
    //     pendiente en un solo paso) → aprobadores de las plantas
    //     involucradas + Admin/Superadmin, sin aprobador ni creador.

    /**
     * V10.3.5-fix: destinatarios cuando un Empleado crea una solicitud
     * PENDIENTE. Aprobadores de la planta responsable + Admin/Superadmin.
     */
    public static function destinatariosCorreoNuevaPendiente(SolicitudInt|SolicitudExt $solicitud): Collection
    {
        $plantaId = self::plantaResponsablePendiente($solicitud);

        $usuarios = $plantaId !== null
            ? self::obtenerAprobadoresDePlanta($plantaId)
            : collect();

        $admins = UsuarioSca::whereHas('rol', fn($q) => $q->whereIn('nombre', ['Admin', 'Superadmin']))
            ->whereNull('fecha_baja')
            ->get();

        return $usuarios->merge($admins)->unique('id_usuario_sca')->values();
    }

    /**
     * V10.3.5-fix: destinatarios cuando un Aprobador+ CREÓ y auto-aprobó
     * una solicitud (pase/permiso/ext, o interplantas siendo Admin/Super).
     * Aprobadores de las plantas involucradas + Admin/Super, sin el creador
     * (él ya sabe: lo acaba de hacer).
     */
    public static function destinatariosCorreoAutoAprobada(SolicitudInt|SolicitudExt $solicitud, $creador): Collection
    {
        $usuarios = collect();
        foreach (self::plantasInvolucradas($solicitud) as $plantaId) {
            $usuarios = $usuarios->merge(self::obtenerAprobadoresDePlanta($plantaId));
        }

        $admins = UsuarioSca::whereHas('rol', fn($q) => $q->whereIn('nombre', ['Admin', 'Superadmin']))
            ->whereNull('fecha_baja')
            ->get();

        return $usuarios->merge($admins)
            ->unique('id_usuario_sca')
            ->where('id_usuario_sca', '!=', $creador->id_usuario_sca)
            ->values();
    }

    /**
     * V10.3.5-fix: destinatarios cuando un Admin/Superadmin APRUEBA
     * directamente (en un solo paso) una solicitud que estaba pendiente.
     * Aprobadores de las plantas involucradas + Admin/Super, sin el
     * aprobador ni el creador (ambos ya fueron notificados por su lado).
     */
    public static function destinatariosCorreoAprobacionDirecta(SolicitudInt|SolicitudExt $solicitud, $aprobador): Collection
    {
        $usuarios = self::destinatariosCorreoAutoAprobada($solicitud, $aprobador);

        $creador = self::obtenerUsuarioCreador($solicitud);
        if ($creador) {
            $usuarios = $usuarios->where('id_usuario_sca', '!=', $creador->id_usuario_sca);
        }

        return $usuarios->values();
    }

    /**
     * V10.3.5-fix: planta responsable de la próxima aprobación de una
     * solicitud pendiente (espejo de nuevaSolicitudPendiente):
     * INT pendiente_origen → planta origen; INT pendiente/pendiente_destino
     * → planta destino; EXT → planta destino.
     */
    private static function plantaResponsablePendiente(SolicitudInt|SolicitudExt $solicitud): ?int
    {
        if ($solicitud instanceof SolicitudInt) {
            return $solicitud->estado === 'pendiente_origen'
                ? $solicitud->id_planta_origen
                : $solicitud->id_planta_destino;
        }
        return $solicitud->id_planta_destino;
    }

    // ============================================================
    // CASO E: Nueva solicitud pendiente (creada por Empleado)
    // ============================================================

    /**
     * Un Empleado creó una solicitud pendiente.
     * Notifica a los aprobadores de la planta correspondiente.
     *
     * Lógica de planta:
     *   - SolicitudInt pendiente_origen → notificar aprobadores de id_planta_origen.
     *   - SolicitudInt pendiente_destino → notificar aprobadores de id_planta_destino.
     *   - SolicitudExt pendiente → notificar aprobadores de id_planta_destino.
     */
    public static function nuevaSolicitudPendiente(SolicitudInt|SolicitudExt $solicitud): void
    {
        // V10.3.5-fix: lógica extraída a plantaResponsablePendiente()
        // (misma planta que usa el correo de destinatariosCorreoNuevaPendiente).
        $plantaId = self::plantaResponsablePendiente($solicitud);

        if (!$plantaId) return;

        $aprobadores = self::obtenerAprobadoresDePlanta($plantaId);
        $idSol = self::idSolicitud($solicitud);
        $url = self::urlShow($solicitud);

        foreach ($aprobadores as $aprobador) {
            self::enviar(
                $aprobador->id_usuario_sca,
                'nueva_solicitud',
                '🔔 Nueva Solicitud Pendiente',
                "Solicitud #{$idSol} esperando tu aprobación.",
                $url
            );
        }
    }

    // ============================================================
    // HELPERS PRIVADOS
    // ============================================================

    /**
     * Devuelve el UsuarioSca del creador de la solicitud (si tiene cuenta).
     */
    private static function obtenerUsuarioCreador(SolicitudInt|SolicitudExt $solicitud): ?UsuarioSca
    {
        return UsuarioSca::where('username', (string) $solicitud->creador_empleado_id)->first();
    }

    /**
     * Etiqueta legible del tipo de solicitud.
     */
    private static function tipoLabel(SolicitudInt|SolicitudExt $solicitud): string
    {
        if ($solicitud instanceof SolicitudInt) {
            return match($solicitud->tipo) {
                'pase_salida'     => 'Pase de Salida',
                'interplantas'    => 'Interplantas',
                'permiso_entrada' => 'Permiso de Entrada', // V10.3-fix (hallazgo usuario)
                default           => $solicitud->tipo,
            };
        }

        return match($solicitud->tipo) {
            'visitante'   => 'Visita',
            'contratista' => 'Contratista',
            default       => $solicitud->tipo,
        };
    }
}