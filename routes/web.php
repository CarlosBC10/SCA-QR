<?php
// routes/web.php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\PuestoTipoEmpleadoController;
use App\Http\Controllers\EmpleadoExtScaController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ClasificacionPlantaController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\SolicitudController;

// Ruta raíz: redirige a login o al dashboard
Route::get('/', function () {
    if (Auth::check()) {
        $rol = Auth::user()->rol->nombre;
        $route = match($rol) {
            'Superadmin' => 'superadmin.dashboard',
            'Admin' => 'admin.dashboard',
            'Aprobador' => 'aprobador.dashboard',
            'Empleado' => 'empleado.dashboard',
            'Guardia' => 'guardia.dashboard',
            default => 'login'
        };
        return redirect()->route($route);
    }
    return redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Rutas públicas (Sin sesión)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/register/initial', [LoginController::class, 'showInitialRegistration'])->name('register.initial');
    Route::post('/register/initial', [LoginController::class, 'initialRegistration']);
    
    // Activar kiosko con PIN (Público)
    Route::post('/kiosko/activar', [ScannerController::class, 'activarKiosko'])->name('kiosko.activar');
});

/*
|--------------------------------------------------------------------------
| Rutas del Kiosco (Públicas, pero requieren un turno activo en la URL)
|--------------------------------------------------------------------------
*/
Route::get('/kiosko/{turno}', [ScannerController::class, 'kiosko'])->name('kiosko.show');
Route::post('/kiosko/{turno}/salir', [ScannerController::class, 'salirKiosko'])->name('kiosko.salir');

// APIs internas para procesar escaneos
Route::post('/api/scan', [ScannerController::class, 'procesarEscaneo'])->name('api.scan');
Route::post('/api/scan/justificar', [ScannerController::class, 'justificarEscaneo'])->name('api.scan.justificar');
Route::get('/api/scan/status/{bitacora_id}', [ScannerController::class, 'estadoRevision'])->name('api.scan.status');

// Proxy de fotos (Público para que el kiosco pueda verlas)
Route::get('/foto-empleado/{id}', [ScannerController::class, 'fotoEmpleado'])->name('foto.empleado');

/*
|--------------------------------------------------------------------------
| Rutas autenticadas (Requieren inicio de sesión)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Dashboards por rol
    Route::get('/superadmin/dashboard', fn () => view('dashboards.superadmin'))->middleware('role:Superadmin')->name('superadmin.dashboard');
    Route::get('/admin/dashboard', fn () => view('dashboards.admin'))->middleware('role:Superadmin,Admin')->name('admin.dashboard');
    Route::get('/aprobador/dashboard', fn () => view('dashboards.aprobador'))->middleware('role:Superadmin,Admin,Aprobador')->name('aprobador.dashboard');
    Route::get('/empleado/dashboard', fn () => view('dashboards.empleado'))->middleware('role:Superadmin,Admin,Aprobador,Empleado')->name('empleado.dashboard');
    
    // Panel del Guardia (Usa el ScannerController)
    Route::get('/guardia/dashboard', [ScannerController::class, 'panelGuardia'])->middleware('role:Superadmin,Admin,Aprobador,Guardia')->name('guardia.dashboard');
    Route::get('/api/guardia/pending', [ScannerController::class, 'pendientesGuardia'])->name('api.guardia.pending');
    Route::get('/api/guardia/denegados', [ScannerController::class, 'denegadosGuardia'])->name('api.guardia.denegados');
    Route::post('/api/guardia/resolve', [ScannerController::class, 'resolverPendiente'])->name('api.guardia.resolve');
    Route::post('/guardia/terminar-turno', [ScannerController::class, 'terminarTurno'])->name('guardia.terminar.turno');

    // M2: Gestión de Usuarios
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

    // M2: Clasificación de Puestos
    Route::middleware('role:Superadmin,Admin,Aprobador')->group(function () {
        Route::get('/puestos', [PuestoTipoEmpleadoController::class, 'index'])->name('puestos.index');
        Route::post('/puestos', [PuestoTipoEmpleadoController::class, 'store'])->name('puestos.store');
        Route::get('/puestos/contar', [PuestoTipoEmpleadoController::class, 'contar'])->name('puestos.contar');
    });

    // M2: Configuración de Empleados
    Route::middleware('role:Superadmin,Admin,Aprobador')->group(function () {
        Route::get('/empleados', [EmpleadoExtScaController::class, 'index'])->name('empleados.index');
        Route::get('/empleados/{empleadoId}/edit', [EmpleadoExtScaController::class, 'edit'])->name('empleados.edit');
        Route::put('/empleados/{empleadoId}', [EmpleadoExtScaController::class, 'update'])->name('empleados.update');
        Route::get('/empleados/buscar', [EmpleadoExtScaController::class, 'buscar'])->name('empleados.buscar');
    });

    // NUEVO: Mapeo Clasificaciones → Plantas
    Route::middleware('role:Superadmin,Admin,Aprobador')->group(function () {
        Route::get('/clasificaciones', [ClasificacionPlantaController::class, 'index'])->name('clasificaciones.index');
        Route::put('/clasificaciones/{idClasificacion}', [ClasificacionPlantaController::class, 'update'])->name('clasificaciones.update');
        Route::delete('/clasificaciones/{idClasificacion}', [ClasificacionPlantaController::class, 'destroy'])->name('clasificaciones.destroy');
    });
    // APIs del guardia (NUEVAS)
    Route::get('/api/guardia/feed', [ScannerController::class, 'feedActividad'])->name('api.guardia.feed');
    Route::get('/api/guardia/dentro', [ScannerController::class, 'personasDentro'])->name('api.guardia.dentro');
    Route::post('/api/guardia/marcar-visto', [ScannerController::class, 'marcarDenegadoVisto'])->name('api.guardia.marcar-visto');

    // M4: Solicitudes
    Route::get('/solicitudes', [SolicitudController::class, 'hub'])->name('solicitudes.hub');
    Route::get('/solicitudes/historial', [SolicitudController::class, 'historial'])->name('solicitudes.historial');
    Route::get('/solicitudes/bandeja', [SolicitudController::class, 'bandeja'])->name('solicitudes.bandeja');
    Route::get('/solicitudes/create', [SolicitudController::class, 'create'])->name('solicitudes.create');
    Route::post('/solicitudes', [SolicitudController::class, 'store'])->name('solicitudes.store');
    
    // IMPORTANTE: Esta ruta debe ir ANTES de la ruta con el parámetro {solicitud}
    Route::get('/solicitudes/buscar-empleado', [SolicitudController::class, 'buscarEmpleado'])->name('solicitudes.buscar-empleado');
    
    // Rutas con parámetros dinámicos al final
    Route::get('/solicitudes/{solicitud}', [SolicitudController::class, 'show'])->name('solicitudes.show');
    Route::post('/solicitudes/{solicitud}/cancel', [SolicitudController::class, 'cancel'])->name('solicitudes.cancel');


});