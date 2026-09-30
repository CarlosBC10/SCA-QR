<?php
// app/Http/Controllers/LogController.php
//
// Módulo de Logs del Sistema (solo Superadmin).
// SRS OT-01: auditoría de acciones críticas del sistema.
//
// Acciones registradas (categoria para filtros):
//   AUTENTICACIÓN:    LOGIN, LOGOUT, CAMBIO_PASSWORD, CAMBIO_PASSWORD_GUARDIA, RESeteo_PASSWORD
//   SOLICITUDES:      CREAR_SOLICITUD, CANCELAR_SOLICITUD, CANCELAR_EMPLEADO, CANCELAR_EXTERNO
//   APROBACIONES:     APROBAR_SOLICITUD, RECHAZAR_SOLICITUD, REENVIAR_CREDENCIALES
//   ACCESO:           JUSTIFICAR_HORARIO, REVISION_ALEATORIA, RECHAZAR_ACCESO
//   CATÁLOGOS:        CLASIFICAR_PUESTO, CLASIFICAR_PUESTOS_MASIVO
//   SISTEMA:          ERROR_MAIL, ERROR_NOTIF, ERROR_PNG, WARN_INTERPLANTAS_DIA

namespace App\Http\Controllers;

use App\Models\LogSistema;
use App\Models\UsuarioSca;
use Illuminate\Http\Request;

class LogController extends Controller
{
    /**
     * Lista los logs con filtros en cascada.
     * Solo Superadmin puede acceder (lo valida la ruta).
     */
    public function index(Request $request)
    {
        // ============================================================
        // FILTROS
        // ============================================================
        $filtros = [
            'categoria'    => $request->query('categoria', ''),
            'accion'       => $request->query('accion', ''),
            'usuario_id'   => $request->query('usuario_id', ''),
            'fecha_desde'  => $request->query('fecha_desde', ''),
            'fecha_hasta'  => $request->query('fecha_hasta', ''),
            'buscar'       => $request->query('buscar', ''),
        ];

        // ============================================================
        // QUERY BASE con eager loading del usuario
        // ============================================================
        $query = LogSistema::with('usuario');

        // ============================================================
        // APLICAR FILTROS
        // ============================================================

        // Categoría: mapea a un grupo de acciones
        if (!empty($filtros['categoria'])) {
            $accionesDeCategoria = $this->accionesPorCategoria($filtros['categoria']);
            if (!empty($accionesDeCategoria)) {
                $query->whereIn('accion', $accionesDeCategoria);
            }
        }

        // Acción específica
        if (!empty($filtros['accion'])) {
            $query->where('accion', $filtros['accion']);
        }

        // Usuario específico
        if (!empty($filtros['usuario_id'])) {
            $query->where('id_usuario_sca', (int) $filtros['usuario_id']);
        }

        // Fecha desde (inclusive)
        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
        }

        // Fecha hasta (inclusive)
        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
        }

        // Búsqueda libre en descripción
        if (!empty($filtros['buscar'])) {
            $query->where('descripcion', 'like', '%' . $filtros['buscar'] . '%');
        }

        // ============================================================
        // ORDEN Y PAGINACIÓN
        // ============================================================
        $logs = $query->orderByDesc('created_at')
                      ->orderByDesc('id_log')
                      ->paginate(50)
                      ->withQueryString();

        // ============================================================
        // DATOS PARA LOS FILTROS DEL FORMULARIO
        // ============================================================
        $categorias = $this->listaCategorias();
        $accionesTodas = $this->listaAcciones();
        $usuarios = UsuarioSca::orderBy('username')->get();

        // Resumen rápido (cards)
        $resumen = [
            'total_hoy'     => LogSistema::whereDate('created_at', today())->count(),
            'total_7dias'   => LogSistema::where('created_at', '>=', now()->subDays(7))->count(),
            'errores_hoy'   => LogSistema::whereDate('created_at', today())
                                  ->whereIn('accion', ['ERROR_MAIL', 'ERROR_NOTIF', 'ERROR_PNG'])
                                  ->count(),
            'logins_hoy'    => LogSistema::whereDate('created_at', today())
                                  ->where('accion', 'LOGIN')
                                  ->count(),
        ];

        return view('logs.index', compact('logs', 'filtros', 'categorias', 'accionesTodas', 'usuarios', 'resumen'));
    }

    /**
     * Mapa de categorías → acciones.
     * Permite filtrar por categoría en el formulario.
     */
    private function accionesPorCategoria(string $categoria): array
    {
        return match ($categoria) {
            'autenticacion' => ['LOGIN', 'LOGOUT', 'CAMBIO_PASSWORD', 'CAMBIO_PASSWORD_GUARDIA', 'RESETEO_PASSWORD'],
            'solicitudes'   => ['CREAR_SOLICITUD', 'CANCELAR_SOLICITUD', 'CANCELAR_EMPLEADO', 'CANCELAR_EXTERNO'],
            'aprobaciones'  => ['APROBAR_SOLICITUD', 'RECHAZAR_SOLICITUD', 'REENVIAR_CREDENCIALES'],
            'acceso'        => ['JUSTIFICAR_HORARIO', 'REVISION_ALEATORIA', 'RECHAZAR_ACCESO'],
            'catalogos'     => ['CLASIFICAR_PUESTO', 'CLASIFICAR_PUESTOS_MASIVO'],
            'errores'       => ['ERROR_MAIL', 'ERROR_NOTIF', 'ERROR_PNG'],
            'advertencias'  => ['WARN_INTERPLANTAS_DIA'],
            default         => [],
        };
    }

    /**
     * Lista de categorías para el dropdown del formulario.
     */
    private function listaCategorias(): array
    {
        return [
            ''              => '— Todas las categorías —',
            'autenticacion' => 'Autenticación (login, logout, password)',
            'solicitudes'   => 'Solicitudes (crear, cancelar)',
            'aprobaciones'  => 'Aprobaciones (aprobar, rechazar, reenviar)',
            'acceso'        => 'Acceso (justificaciones, F10, rechazos)',
            'catalogos'     => 'Catálogos (clasificación de puestos)',
            'errores'       => 'Errores (mail, notificaciones, PNG)',
            'advertencias'  => 'Advertencias (interplantas en día no laboral)',
        ];
    }

    /**
     * Lista de todas las acciones registradas (para el dropdown de acción específica).
     */
    private function listaAcciones(): array
    {
        return [
            '' => '— Todas las acciones —',
            'LOGIN' => 'Login',
            'LOGOUT' => 'Logout',
            'CAMBIO_PASSWORD' => 'Cambio de contraseña',
            'CAMBIO_PASSWORD_GUARDIA' => 'Cambio de contraseña (guardia)',
            'RESETEO_PASSWORD' => 'Reseteo de contraseña',
            'CREAR_SOLICITUD' => 'Crear solicitud',
            'CANCELAR_SOLICITUD' => 'Cancelar solicitud',
            'CANCELAR_EMPLEADO' => 'Cancelar empleado',
            'CANCELAR_EXTERNO' => 'Cancelar externo',
            'APROBAR_SOLICITUD' => 'Aprobar solicitud',
            'RECHAZAR_SOLICITUD' => 'Rechazar solicitud',
            'REENVIAR_CREDENCIALES' => 'Reenviar credenciales',
            'JUSTIFICAR_HORARIO' => 'Justificar horario',
            'REVISION_ALEATORIA' => 'Revisión aleatoria (F10)',
            'RECHAZAR_ACCESO' => 'Rechazar acceso',
            'CLASIFICAR_PUESTO' => 'Clasificar puesto',
            'CLASIFICAR_PUESTOS_MASIVO' => 'Clasificar puestos (masivo)',
            'ERROR_MAIL' => 'Error de correo',
            'ERROR_NOTIF' => 'Error de notificación',
            'ERROR_PNG' => 'Error generando PNG',
            'WARN_INTERPLANTAS_DIA' => 'Advertencia interplantas día no laboral',
        ];
    }
}