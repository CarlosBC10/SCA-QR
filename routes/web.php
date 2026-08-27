<?php
// routes/web.php
//
// RUTAS DEL SISTEMA SCA-QR (V4).
//
// CAMBIOS V4:
//   - Kiosko se activa con login de guardia (no PIN global).
//   - Ruta /api/kiosko/{turno}/status (polling de turno activo).
//   - Ruta /api/guardia/justificar (guardia justifica anomalía de horario).
//   - Eliminada /api/scan/justificar (la justificación la hace el guardia, no el empleado).
//   - Eliminada /api/guardia/marcar-visto (se eliminó visto_guardia de bitacora).
//   - Solicitudes split: /solicitudes/int/{id} y /solicitudes/ext/{id}.
//   - Aprobaciones split: aprobarInt, aprobarExt, aprobarGrupalInt.
//   - Ruta /solicitudes/buscar-externo (búsqueda por CURP).
//   - /solicitudes/mis renombrada (era mis-solicitudes).
//   - /api/guardia/dentro renombrada a /api/guardia/personas-dentro.

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\PuestoTipoEmpleadoController;
use App\Http\Controllers\EmpleadoExtScaController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ClasificacionPlantaController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\AprobacionController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\CatalogoController;

// ============================================================
// RUTA RAÍZ
// ============================================================
// Redirige a login o al dashboard según el rol.
Route::get('/', function () {
    // M2 [ScaModal] — reflash() preserva los flashes para el SIGUIENTE redirect.
    // Necesario porque esta ruta redirige al dashboard, y sin reflash los
    // flashes (como sca_modal) se pierden en esa segunda redirección.
    session()->reflash();

    if (Auth::check()) {
        $rol = Auth::user()->rol->nombre;
        $route = match($rol) {
            'Superadmin' => 'superadmin.dashboard',
            'Admin'      => 'admin.dashboard',
            'Aprobador'  => 'aprobador.dashboard',
            'Empleado'   => 'empleado.dashboard',
            'Guardia'    => 'guardia.dashboard',
            default      => 'login'
        };
        return redirect()->route($route);
    }
    return redirect()->route('login');
})->name('home');

// ============================================================
// RUTAS PÚBLICAS (sin sesión)
// ============================================================
Route::middleware('guest')->group(function () {
    // Login del sistema (admin, aprobador, empleado)
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    // Registro inicial del primer Superadmin
    Route::get('/register/initial', [LoginController::class, 'showInitialRegistration'])->name('register.initial');
    Route::post('/register/initial', [LoginController::class, 'initialRegistration']);

    // Login del kiosko (página pública, pero requiere credenciales de guardia)
    // El guardia ingresa username + password + planta + modo.
    Route::get('/kiosko/activar', [ScannerController::class, 'mostrarActivarKiosko'])->name('kiosko.activar.form');
    Route::post('/kiosko/activar', [ScannerController::class, 'activarKiosko'])->name('kiosko.activar');
    // Olvidé mi contraseña (público)
    Route::get('/password/reset', [\App\Http\Controllers\Auth\LoginController::class, 'showResetForm'])->name('password.request');
    Route::post('/password/reset', [\App\Http\Controllers\Auth\LoginController::class, 'sendResetEmail'])->name('password.email');
});

// ============================================================
// RUTAS DEL KIOSKO (públicas, requieren turno en la URL)
// ============================================================
// Una vez activado el kiosko, la pantalla de escaneo es pública.
// Cualquiera puede escanear; el turno en la URL identifica al guardia/planta.

// Pantalla del kiosko (escaneo)
Route::get('/kiosko/{turno}', [ScannerController::class, 'kiosko'])->name('kiosko.show');

// Cerrar un kiosko individual (sin cerrar sesión del guardia)
Route::post('/kiosko/{turno}/salir', [ScannerController::class, 'salirKiosko'])->name('kiosko.salir');

// Polling: el kiosko pregunta si el turno sigue activo (cada ~5s)
// Si retorna activo=false, el kiosko muestra "El guardia ha cerrado sesión" y redirige al login.
Route::get('/api/kiosko/{turno}/status', [ScannerController::class, 'kioskoStatus'])->name('api.kiosko.status');

// API principal de escaneo (el kiosko envía el código escaneado)
Route::post('/api/scan', [ScannerController::class, 'procesarEscaneo'])->name('api.scan');

// Polling: el kiosko pregunta si una bitácora ya fue autorizada/denegada (cada ~2s)
// Mientras el empleado espera al guardia (F10 o justificación de horario).
Route::get('/api/scan/status/{bitacora_id}', [ScannerController::class, 'estadoRevision'])->name('api.scan.status');

// Proxy de fotos de empleados (público para que el kiosko pueda verlas)
Route::get('/foto-empleado/{id}', [ScannerController::class, 'fotoEmpleado'])->name('foto.empleado');

// ============================================================
// RUTAS AUTENTICADAS (requieren sesión)
// ============================================================
Route::middleware(['auth', 'debe_cambiar_password'])->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // ------------------------------------------------------------
    // FIX I-5: Cambio de contraseña obligatorio (temporal)
    // ------------------------------------------------------------
    Route::get('/password/cambiar', [\App\Http\Controllers\PasswordController::class, 'edit'])
        ->name('password.cambiar');
    Route::post('/password/cambiar', [\App\Http\Controllers\PasswordController::class, 'update'])
        ->name('password.cambiar.update');

    // ------------------------------------------------------------
    // Dashboards por rol
    // ------------------------------------------------------------
    Route::get('/superadmin/dashboard', fn () => view('dashboards.superadmin'))->middleware('role:Superadmin')->name('superadmin.dashboard');
    Route::get('/admin/dashboard', fn () => view('dashboards.admin'))->middleware('role:Superadmin,Admin')->name('admin.dashboard');
    Route::get('/aprobador/dashboard', fn () => view('dashboards.aprobador'))->middleware('role:Superadmin,Admin,Aprobador')->name('aprobador.dashboard');
    Route::get('/empleado/dashboard', fn () => view('dashboards.empleado'))->middleware('role:Superadmin,Admin,Aprobador,Empleado')->name('empleado.dashboard');

    // ------------------------------------------------------------
    // Panel del Guardia (dashboard web)
    // ------------------------------------------------------------
    Route::get('/guardia/dashboard', [ScannerController::class, 'panelGuardia'])
        ->middleware('role:Superadmin,Admin,Aprobador,Guardia')
        ->name('guardia.dashboard');

    // APIs del panel del guardia
    Route::middleware('role:Superadmin,Admin,Aprobador,Guardia')->group(function () {
        // Pendientes: F10 (pendiente_revision) + justificaciones (revision_horario)
        Route::get('/api/guardia/pending', [ScannerController::class, 'pendientesGuardia'])->name('api.guardia.pending');

        // Denegados del día
        Route::get('/api/guardia/denegados', [ScannerController::class, 'denegadosGuardia'])->name('api.guardia.denegados');

        // Feed de actividad (últimos 15 eventos)
        Route::get('/api/guardia/feed', [ScannerController::class, 'feedActividad'])->name('api.guardia.feed');

        // Personas actualmente dentro de la planta
        Route::get('/api/guardia/personas-dentro', [ScannerController::class, 'personasDentro'])->name('api.guardia.personas-dentro');

        // Justificar anomalía de horario (guardia selecciona del catálogo)
        Route::post('/api/guardia/justificar', [ScannerController::class, 'justificarEscaneo'])->name('api.guardia.justificar');

        // Resolver revisión F10 (guardia marca todo_bien|anomalia)
        Route::post('/api/guardia/resolve', [ScannerController::class, 'resolverPendiente'])->name('api.guardia.resolve');

        // Terminar turno (cierra TODOS los kioskos del guardia + logout)
        Route::post('/guardia/terminar-turno', [ScannerController::class, 'terminarTurno'])->name('guardia.terminar.turno');

        // API: catálogo de justificaciones de horario (para el dropdown del guardia)
        Route::get('/api/justificaciones', [CatalogoController::class, 'justificaciones'])->name('api.justificaciones');
        // Resetear contraseña desde el panel de admin
        Route::post('/usuarios/{usuario}/reset-password', [\App\Http\Controllers\UsuarioController::class, 'resetPassword'])->name('usuarios.reset-password');

        // M2 [ScaModal] — Cambiar contraseña de guardia directamente (sin correo).
        Route::post('/usuarios/{usuario}/cambiar-password-guardia', [\App\Http\Controllers\UsuarioController::class, 'cambiarPasswordGuardia'])->name('usuarios.cambiar-password-guardia');
    });

    // ------------------------------------------------------------
    // M2: Gestión de Usuarios
    // ------------------------------------------------------------
    Route::middleware('role:Superadmin,Admin,Aprobador')->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('/usuarios/create', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::get('/usuarios/{usuario}/edit', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');
        Route::post('/usuarios/empleado', [UsuarioController::class, 'storeEmpleado'])->name('usuarios.store.empleado');
        Route::post('/usuarios/guardia', [UsuarioController::class, 'storeGuardia'])->name('usuarios.store.guardia');
        Route::get('/usuarios/buscar-empleado', [UsuarioController::class, 'buscarEmpleado'])->name('usuarios.buscar');
    });

    // ------------------------------------------------------------
    // M2: Clasificación de Puestos
    // ------------------------------------------------------------
    Route::middleware('role:Superadmin,Admin,Aprobador')->group(function () {
        Route::get('/puestos', [PuestoTipoEmpleadoController::class, 'index'])->name('puestos.index');
        Route::post('/puestos', [PuestoTipoEmpleadoController::class, 'store'])->name('puestos.store');
        Route::get('/puestos/contar', [PuestoTipoEmpleadoController::class, 'contar'])->name('puestos.contar');
        
        // FIX J: Rutas nuevas
        Route::post('/puestos/clasificar-masivo', [PuestoTipoEmpleadoController::class, 'clasificarMasivo'])->name('puestos.clasificar.masivo');
        Route::get('/puestos/{id_puesto}/empleados', [PuestoTipoEmpleadoController::class, 'empleadosDelPuesto'])->name('puestos.empleados');
    });

    // ------------------------------------------------------------
    // M2: Configuración de Empleados (ext_sca)
    // ------------------------------------------------------------
    Route::middleware('role:Superadmin,Admin,Aprobador')->group(function () {
        Route::get('/empleados', [EmpleadoExtScaController::class, 'index'])->name('empleados.index');
        Route::get('/empleados/{empleadoId}/edit', [EmpleadoExtScaController::class, 'edit'])->name('empleados.edit');
        Route::put('/empleados/{empleadoId}', [EmpleadoExtScaController::class, 'update'])->name('empleados.update');
        Route::get('/empleados/buscar', [EmpleadoExtScaController::class, 'buscar'])->name('empleados.buscar');
    });

    // ------------------------------------------------------------
    // M2: Mapeo Clasificaciones → Plantas
    // ------------------------------------------------------------
    Route::middleware('role:Superadmin,Admin,Aprobador')->group(function () {
        Route::get('/clasificaciones', [ClasificacionPlantaController::class, 'index'])->name('clasificaciones.index');
        Route::put('/clasificaciones/{idClasificacion}', [ClasificacionPlantaController::class, 'update'])->name('clasificaciones.update');
        Route::delete('/clasificaciones/{idClasificacion}', [ClasificacionPlantaController::class, 'destroy'])->name('clasificaciones.destroy');

        // M2 [ScaModal] — Ruta AJAX para obtener excepciones
        // (empleados con planta_forzada=true que no se actualizaron al cambiar mapeo)
        Route::get('/clasificaciones/{idClasificacion}/excepciones', [ClasificacionPlantaController::class, 'excepciones'])
            ->name('clasificaciones.excepciones');
    });

    // ------------------------------------------------------------
    // M4 & M5: Solicitudes
    // ------------------------------------------------------------

    // Hub (página principal de solicitudes según rol)
    Route::get('/solicitudes', [SolicitudController::class, 'hub'])->name('solicitudes.hub');

    // Mis solicitudes (creadas por el usuario)
    Route::get('/solicitudes/mis', [SolicitudController::class, 'misSolicitudes'])->name('solicitudes.mis');

    // Mis aprobaciones (que el usuario aprobó)
    Route::get('/solicitudes/mis-aprobaciones', [SolicitudController::class, 'misAprobaciones'])->name('solicitudes.mis-aprobaciones');

    // Historial general (solo Admin y Superadmin)
    Route::middleware('role:Admin,Superadmin')->group(function () {
        Route::get('/solicitudes/historial', [SolicitudController::class, 'historial'])->name('solicitudes.historial');
    });

    // Descargar credenciales en PDF (todas juntas)
    Route::get('/solicitudes/ext/{solicitud}/descargar-credenciales', 
        [\App\Http\Controllers\SolicitudController::class, 'descargarCredenciales'])
        ->name('solicitudes.descargar.credenciales');

    // Descargar PNG individual
    Route::get('/solicitudes/credencial/{idDetalle}/png', 
        [\App\Http\Controllers\SolicitudController::class, 'descargarCredencialPng'])
        ->name('solicitudes.descargar.png');

    // Reenviar credenciales por correo
    Route::post('/solicitudes/ext/{solicitud}/reenviar-credenciales', 
        [\App\Http\Controllers\SolicitudController::class, 'reenviarCredenciales'])
        ->name('solicitudes.reenviar.credenciales');

    // Bandeja de aprobaciones (pendientes)
    Route::get('/solicitudes/bandeja', [SolicitudController::class, 'bandeja'])->name('solicitudes.bandeja');

    // Crear solicitud
    Route::get('/solicitudes/create', [SolicitudController::class, 'create'])->name('solicitudes.create');
    Route::post('/solicitudes', [SolicitudController::class, 'store'])->name('solicitudes.store');

    // APIs de búsqueda para el formulario
    Route::get('/solicitudes/buscar-empleado', [SolicitudController::class, 'buscarEmpleado'])->name('solicitudes.buscar-empleado');
    // CAMBIO V7: unificadas las dos rutas viejas en una sola.
    Route::get('/solicitudes/buscar-externo-identificacion', [SolicitudController::class, 'buscarExternoPorIdentificacion'])
         ->name('solicitudes.buscar-externo-identificacion');

    // Ver detalle (split int/ext)
    Route::get('/solicitudes/int/{solicitud}', [SolicitudController::class, 'showInt'])->name('solicitudes.show.int');
    Route::get('/solicitudes/ext/{solicitud}', [SolicitudController::class, 'showExt'])->name('solicitudes.show.ext');

    // Cancelar solicitud pendiente (split int/ext)
    Route::post('/solicitudes/int/{solicitud}/cancel', [SolicitudController::class, 'cancelInt'])->name('solicitudes.cancel.int');
    Route::post('/solicitudes/ext/{solicitud}/cancel', [SolicitudController::class, 'cancelExt'])->name('solicitudes.cancel.ext');

    // FIX A-8: Cancelar un empleado específico de una solicitud grupal
    Route::post('/solicitudes/int/{solicitud}/cancel-empleado', [SolicitudController::class, 'cancelEmpleadoInt'])->name('solicitudes.cancel.empleado.int');

    // FIX M-1: Cancelar un externo específico de una solicitud
    Route::post('/solicitudes/ext/{solicitud}/cancel-externo', [SolicitudController::class, 'cancelExternoIndividual'])->name('solicitudes.cancel.externo.individual');

    // FIX 3: Validar turnos para pase de salida
    Route::get('/solicitudes/validar-turno-pase', [SolicitudController::class, 'validarTurnoPase'])->name('solicitudes.validar.turno');
    
    // ------------------------------------------------------------
    // M5: Aprobaciones (split int/ext)
    // ------------------------------------------------------------

    // Aprobar/rechazar solicitud interna individual
    Route::post('/solicitudes/int/{solicitud}/aprobar', [AprobacionController::class, 'aprobarInt'])->name('solicitudes.aprobar.int');
    Route::post('/solicitudes/int/{solicitud}/rechazar', [AprobacionController::class, 'rechazarInt'])->name('solicitudes.rechazar.int');

    // Aprobar/rechazar solicitud interna grupal (todos-o-nada con aprobado_parcial)
    Route::post('/solicitudes/int/{solicitud}/aprobar-grupal', [AprobacionController::class, 'aprobarGrupalInt'])->name('solicitudes.aprobar.grupal');

    // Aprobar/rechazar solicitud externa (genera QRs)
    Route::post('/solicitudes/ext/{solicitud}/aprobar', [AprobacionController::class, 'aprobarExt'])->name('solicitudes.aprobar.ext');
    Route::post('/solicitudes/ext/{solicitud}/rechazar', [AprobacionController::class, 'rechazarExt'])->name('solicitudes.rechazar.ext');

    // ------------------------------------------------------------
    // Notificaciones
    // ------------------------------------------------------------
    Route::get('/api/notificaciones', [NotificacionController::class, 'api'])->name('api.notificaciones');
    Route::post('/api/notificaciones/marcar-leida', [NotificacionController::class, 'marcarLeida'])->name('api.notificaciones.marcar');
    Route::post('/api/notificaciones/marcar-todas', [NotificacionController::class, 'marcarTodasLeidas'])->name('api.notificaciones.marcar-todas');
});