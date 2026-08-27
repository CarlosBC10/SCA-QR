<?php
// app/Http/Controllers/ScannerController.php
//
// CONTROLADOR DEL KIOSKO Y PANEL DEL GUARDIA (M3).
//
// Responsabilidades:
//   1. Activación del kiosko (login de guardia → crea turno_guardia).
//   2. Procesar escaneos (empleado o externo) y crear bitácora.
//   3. Justificación de anomalías de horario (guardia selecciona del catálogo).
//   4. Resolución de revisiones F10 (guardia marca todo_bien|anomalia).
//   5. Polling de estado (el kiosko pregunta si ya puede pasar).
//   6. Panel del guardia (pendientes, feed, personas dentro, denegados).
//   7. Cierre masivo de turnos (terminar turno cierra todos sus kioskos).
//
// CAMBIOS V4:
//   - Login del kiosko con credenciales de guardia (sin PIN global).
//   - Usa los nuevos modelos: Bitacora (con persona_id unificado), SolicitudInt, 
//     SolicitudExt, SolicitudExternoDetalle, Externo.
//   - FKs renombradas: id_guardia, id_planta, id_externos, id_bitacora, etc.
//   - Manejo de F11 con fecha_ultimo_manual (1 uso por día, sin bloqueo_manual).
//   - Estados de bitácora: autorizado, pendiente_revision, revision_horario, denegado.
//   - Cierre masivo: terminar turno cierra TODOS los turnos activos del guardia.
//   - Notificaciones al solicitante + guardias en turno cuando llega un externo.

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Bitacora;
use App\Models\Guardia;
use App\Models\Planta;
use App\Models\TurnoGuardia;
use App\Models\RevisionHallazgo;
use App\Models\JustificacioneHorario;
use App\Models\SolicitudExternoDetalle;
use App\Models\UsuarioSca;
use App\Models\EmpleadoExtSca;
use App\Models\Sitec\Empleado;
use App\Models\LogSistema;
use App\Models\Notificacion;
use App\Services\AccesoService;
use App\Services\NotificacionService;

class ScannerController extends Controller
{
    // ============================================================
    // 1. ACTIVACIÓN DEL KIOSKO
    // ============================================================

    /**
     * Muestra el formulario de login del kiosko.
     * Ruta: GET /kiosko/activar
     * Pública (sin sesión).
     */
    public function mostrarActivarKiosko()
    {
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();
        return view('kiosko.activar', compact('plantas'));
    }

    /**
     * Procesa el login del kiosko.
     * Ruta: POST /kiosko/activar
     *
     * Valida credenciales de guardia, crea un turno_guardia y redirige
     * al kiosko. Un guardia puede tener múltiples kioskos activos
     * (entrada en un monitor, salida en otro, etc.).
     */
    public function activarKiosko(Request $request)
    {
        // FIX B-1: Ya no pedimos 'id_planta' en el form. Se usa la del guardia.
        $credentials = $request->validate([
            'username'  => ['required', 'string'],
            'password'  => ['required', 'string'],
            'modo'      => ['required', 'in:entrada,salida,mixto'],
        ]);

        // Buscar usuario por username
        $usuario = UsuarioSca::where('username', $credentials['username'])->first();

        // Validar credenciales y que sea guardia activo
        if (!$usuario
            || !password_verify($credentials['password'], $usuario->password)
            || !$usuario->activo
            || !$usuario->esGuardia()
        ) {
            return back()
                ->withErrors(['username' => 'Credenciales inválidas o usuario no autorizado.'])
                ->withInput();
        }

        $guardia = $usuario->guardia;
        if (!$guardia || !$guardia->activo) {
            return back()
                ->withErrors(['username' => 'Guardia inactivo. Contacte al administrador.'])
                ->withInput();
        }

        if (!$guardia->id_planta) {
            return back()
                ->withErrors(['username' => 'El guardia no tiene planta asignada. Contacte al administrador.'])
                ->withInput();
        }

        // Crear el turno_guardia usando la planta del guardia automáticamente
        $turno = TurnoGuardia::create([
            'id_guardia'         => $guardia->id_guardia,
            'id_planta'          => $guardia->id_planta, // FIX B-1: Planta automática
            'modo'               => $credentials['modo'],
            'fecha_hora_inicio'  => now(),
        ]);

        // Log de auditoría
        LogSistema::registrar(
            'INICIO_TURNO_GUARDIA',
            "Kiosko activado en planta {$guardia->id_planta} (Modo: {$credentials['modo']}) por guardia {$usuario->username}"
        );

        return redirect()->route('kiosko.show', $turno->id_turno_guardia);
    }

    /**
     * Muestra la pantalla del kiosko (pública, sin login).
     * Ruta: GET /kiosko/{turno}
     *
     * Cualquiera puede escanear aquí; el turno en la URL identifica
     * qué guardia y planta están activos.
     */
    public function kiosko(TurnoGuardia $turno)
    {
        // Si el turno ya cerró, redirigir al login del kiosko
        if ($turno->fecha_hora_fin) {
            return redirect()->route('kiosko.activar.form')
                ->with('kiosko_error', 'Este kiosko ha sido desactivado. Inicie sesión de nuevo.');
        }

        $turno->load(['guardia', 'planta']);
        return view('kiosko.index', compact('turno'));
    }

        /**
     * Cierra un kiosko individual (validando credenciales del guardia).
     * Ruta: POST /kiosko/{turno}/salir
     *
     * Lógica:
     *   - Valida username + password del guardia.
     *   - El guardia debe ser el mismo que activó el kiosko (o Admin/Super).
     *   - Si las credenciales son válidas → cierra el turno (fecha_hora_fin = now).
     *   - Si no → vuelve atrás con error.
     */
    public function salirKiosko(Request $request, TurnoGuardia $turno)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Buscar el usuario por username
        $usuario = UsuarioSca::where('username', $credentials['username'])->first();

        // Validar credenciales
        if (!$usuario
            || !password_verify($credentials['password'], $usuario->password)
            || !$usuario->activo
        ) {
            return back()->withErrors(['username' => 'Credenciales inválidas.'])->withInput();
        }

        // Validar que sea el guardia que activó el kiosko, o Admin/Super
        $esSuperior = $usuario->isAdmin() || $usuario->isSuperadmin();
        $esGuardiaDelTurno = $usuario->esGuardia() && $usuario->username === $turno->id_guardia; // V5: Compara username con id_guardia

        if (!$esSuperior && !$esGuardiaDelTurno) {
            return back()->withErrors(['username' => 'No tiene permisos para cerrar este kiosko.'])->withInput();
        }

        // Cerrar el turno
        $turno->fecha_hora_fin = now();
        $turno->save();

        LogSistema::registrar(
            'FIN_KIOSKO',
            "Kiosko #{$turno->id_turno_guardia} cerrado en planta {$turno->id_planta} por {$usuario->username}"
        );

        return redirect()->route('kiosko.activar.form')
            ->with('kiosko_info', 'Kiosko cerrado. Inicie sesión para activarlo de nuevo.');
    }

    /**
     * Polling: el kiosko pregunta si el turno sigue activo.
     * Ruta: GET /api/kiosko/{turno}/status
     *
     * El kiosko llama esto cada ~5s. Si retorna activo=false,
     * el kiosko muestra "El guardia ha cerrado sesión" y redirige al login.
     */
    public function kioskoStatus(TurnoGuardia $turno)
    {
        return response()->json([
            'activo'   => $turno->fecha_hora_fin === null,
            'guardia'  => $turno->guardia?->nombre_completo,
            'planta'   => $turno->planta?->nombre,
            'modo'     => $turno->modo,
        ]);
    }

    /**
     * Procesa un código escaneado o ingresado manualmente.
     * Ruta: POST /api/scan
     *
     * CAMBIO V5: Si el origen es 'guardia' (escaneo desde el panel web), 
     * no requiere turno_id. Usa/crea su turno activo buscando por $user->username.
     */
    public function procesarEscaneo(Request $request)
    {
        $codigo = trim($request->input('codigo', ''));
        $origen = $request->input('origen', 'kiosco');
        $turnoId = $request->input('turno_id');

        if (empty($codigo)) {
            return response()->json(['success' => false, 'message' => 'Código vacío.'], 400);
        }

        $plantaId = null;
        $guardiaId = null;
        $modoKiosko = 'mixto';

        // ============================================================
        // CAMBIO V7 (Problema D): si el guardia opera desde el panel web,
        // NO se crea un turno automático. El panel es una herramienta web
        // separada del kiosko público. Los turnos solo se crean cuando el
        // guardia activa un kiosko público explícitamente. Así evitamos
        // turnos fantasma que aparecen como "kiosko activo" sin serlo.
        // ============================================================
        $esGuardiaAutenticado = Auth::check() && Auth::user()->esGuardia();
        $origenesDeGuardia = ['guardia', 'manual'];

        if ($esGuardiaAutenticado && in_array($origen, $origenesDeGuardia, true)) {
            // El guardia está usando el panel web. Usamos sus datos directamente.
            $user = Auth::user();
            $plantaId = $user->guardia->id_planta;
            $guardiaId = $user->username;
            $modoKiosko = 'mixto';
            $turnoId = null;  // no hay turno asociado (no es un kiosko público)
        } else {
            // Kiosko público: requiere turno_id en la URL
            $turno = TurnoGuardia::find($turnoId);
            if (!$turno || $turno->fecha_hora_fin) {
                return response()->json(['success' => false, 'message' => 'Kiosko desactivado.'], 403);
            }

            $plantaId = $turno->id_planta;
            $guardiaId = $turno->id_guardia;
            $modoKiosko = $turno->modo;
        }

        if (str_starts_with($codigo, config('sca_qr.prefijo_qr_externo'))) {
            return $this->procesarExterno($codigo, $origen, $turnoId, $plantaId, $guardiaId);
        }

        return $this->procesarEmpleado($codigo, $origen, $turnoId, $plantaId, $guardiaId, $modoKiosko);
    }

    /**
     * Lógica principal para escaneo/búsqueda de empleados.
     * CAMBIO V5: Bitacora usa 'persona_id' (string) en lugar de 'empleado_id'.
     */
    private function procesarEmpleado($codigo, $origen, $turnoId, $plantaId, $guardiaId, $modoKiosko = 'mixto')
    {
        try {
            if ($origen === 'manual') {
                $empleado = Empleado::with(['turno', 'clasificacion', 'puesto', 'departamento'])
                    ->where('id_empleado', $codigo)->first();
            } else {
                $empleado = Empleado::with(['turno', 'clasificacion', 'puesto', 'departamento'])
                    ->where('semilla', $codigo)->first();
            }

            if (!$empleado) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR o número de empleado no válido.',
                ]);
            }

            if (!$empleado->estaActivo()) {
                // V5: Guardar en persona_id (string)
                Bitacora::create([
                    'persona_id'     => (string) $empleado->id_empleado,
                    'id_guardia'     => $guardiaId,
                    'tipo_evento'    => $modoKiosko === 'salida' ? 'salida' : 'entrada',
                    'estado_evento'  => 'denegado',
                    'motivo_denegacion' => 'empleado_inactivo',
                    'fecha_hora'     => now(),
                    'id_planta'      => $plantaId,
                ]);
                return response()->json(['success' => false, 'message' => 'Empleado no activo.']);
            }

            $ext = EmpleadoExtSca::firstOrNew(['empleado_id' => $empleado->id_empleado]);
            if (!$ext->exists) {
                $ext->usos_manuales_restantes = 4;
                $ext->fecha_ultimo_manual = null;
                $ext->save();
            }

            $usosRestantes = $ext->usos_manuales_restantes;

            if ($origen === 'manual') {
                if ($usosRestantes <= 0) {
                    Bitacora::create([
                        'persona_id'     => (string) $empleado->id_empleado,
                        'id_guardia'     => $guardiaId,
                        'tipo_evento'    => $modoKiosko === 'salida' ? 'salida' : 'entrada',
                        'estado_evento'  => 'denegado',
                        'motivo_denegacion' => 'manual_bloqueado',
                        'fecha_hora'     => now(),
                        'id_planta'      => $plantaId,
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Búsqueda bloqueada. URGE REPOSICIÓN DE CREDENCIAL.',
                    ]);
                }

                if (!$ext->yaUsoManualHoy()) {
                    $ext->usos_manuales_restantes = max(0, $usosRestantes - 1);
                    $ext->fecha_ultimo_manual = today();
                    $ext->save();
                    $usosRestantes = $ext->usos_manuales_restantes;
                }
            } elseif ($origen === 'kiosco' || $origen === 'guardia') {
                if ($usosRestantes < 4 || $ext->fecha_ultimo_manual !== null) {
                    $ext->reiniciarUsosManuales();
                    $usosRestantes = 4;
                }
            }

            // ============================================================
            // CAMBIO V7 (fix N2): reorganizar validaciones.
            // ============================================================
            // Antes validarAccesoPlanta() se ejecutaba PRIMERO, antes de saber
            // si era entrada o salida. Eso bloqueaba salidas legítimas cuando
            // el permiso interplantas había expirado (la persona quedaba
            // atrapada adentro).
            //
            // Ahora:
            // 1. Determinar tipoEvento (entrada/salida) según última bitácora.
            // 2. Validar continuidad de planta (evita inconsistencias).
            // 3. SOLO si es ENTRADA, validar acceso a planta (permisos).
            //    Si es SALIDA, no validar: si ya está adentro, siempre puede salir.
            // ============================================================

            // 1. Buscar último evento para determinar si es entrada o salida
            $ultimoEvento = Bitacora::where('persona_id', (string) $empleado->id_empleado)
                ->hoy()
                ->noDenegados()
                ->latest('fecha_hora')
                ->first();

            $tipoEvento = $this->determinarTipoEvento($ultimoEvento, $modoKiosko);

            if ($tipoEvento === null) {
                return response()->json(['success' => false, 'message' => $this->mensajeModoInvalido($ultimoEvento, $modoKiosko)]);
            }

            // 2. Validar continuidad de planta (sigue siendo tajante)
            $continuidad = $this->validarContinuidadPlanta(
                (string) $empleado->id_empleado,
                $tipoEvento,
                $plantaId
            );
            if ($continuidad !== null) {
                Bitacora::create([
                    'persona_id'     => (string) $empleado->id_empleado,
                    'id_guardia'     => $guardiaId,
                    'tipo_evento'    => $tipoEvento,
                    'estado_evento'  => 'denegado',
                    'motivo_denegacion' => 'planta_inconsistente',
                    'fecha_hora'     => now(),
                    'id_planta'      => $plantaId,
                ]);
                return response()->json(['success' => false, 'message' => $continuidad]);
            }

            // 3. Validar acceso a planta SOLO para ENTRADAS.
            // Si es SALIDA, no validar: la persona ya está adentro y siempre
            // debe poder salir (no podemos retenerla).
            $accesoService = app(AccesoService::class);

            if ($tipoEvento === 'entrada') {
                $validacion = $accesoService->validarAccesoPlanta($empleado, $plantaId);

                if (!$validacion['permitido']) {
                    Bitacora::create([
                        'persona_id'     => (string) $empleado->id_empleado,
                        'id_guardia'     => $guardiaId,
                        'tipo_evento'    => 'entrada',
                        'estado_evento'  => 'denegado',
                        'motivo_denegacion' => $validacion['motivo_codigo'],
                        'fecha_hora'     => now(),
                        'id_planta'      => $plantaId,
                    ]);
                    return response()->json(['success' => false, 'message' => $validacion['motivo']]);
                }
            } else {
                // Para SALIDAS: no validar acceso a planta.
                // Creamos $validacion vacío para que el resto del código
                // siga funcionando (datosPersonaEmpleado, mensaje, etc.).
                $validacion = [
                    'permitido'         => true,
                    'motivo'            => null,
                    'motivo_codigo'     => null,
                    'planta_origen'     => $empleado->plantaId(),
                    'es_interplantas'   => false,
                    'planta_destino_id' => null,
                ];
            }

            // V5: Buscar si hubo entrada hoy por persona_id
            $huboEntradaHoy = Bitacora::where('persona_id', (string) $empleado->id_empleado)
                ->where('tipo_evento', 'entrada')
                ->noDenegados()
                ->hoy()
                ->exists();
            $esPrimeraEntrada = ($tipoEvento === 'entrada' && !$huboEntradaHoy);

            $horario = $accesoService->validarHorario($empleado, $tipoEvento, $esPrimeraEntrada);

            if (!$horario['permitido']) {
                Bitacora::create([
                    'persona_id'     => (string) $empleado->id_empleado,
                    'id_guardia'     => $guardiaId,
                    'tipo_evento'    => $tipoEvento,
                    'estado_evento'  => 'denegado',
                    'motivo_denegacion' => $horario['motivo_codigo'],
                    'fecha_hora'     => now(),
                    'id_planta'      => $plantaId,
                ]);
                return response()->json(['success' => false, 'message' => $horario['motivo']]);
            }

            // ============================================================
            // CAMBIO V7 (N3): F10 + anomalía de horario combinadas.
            // ============================================================
            // Antes: si había anomalía de horario, se hacía RETURN antes de
            // sortear F10, así que nunca se combinaban ambas. Un empleado que
            // llegaba tarde Y le tocaba F10 solo se le justificaba el horario.
            //
            // Ahora: calculamos F10 PRIMERO. Si hay anomalía, seteamos
            // revision_exhaustiva=true en la bitácora de 'revision_horario'.
            // Así, cuando el guardia justifica el horario (justificarEscaneo),
            // el estado pasa a 'pendiente_revision' (gracias a la lógica
            // existente) y el guardia debe hacer también la revisión F10.
            //
            // Flujo del caso combinado:
            //   1. revision_horario + revision_exhaustiva=true
            //   2. guardia justifica horario → pendiente_revision
            //   3. guardia resuelve F10 → autorizado
            // ============================================================
            $revisionF10 = $accesoService->sortearRevisionExhaustiva($empleado);

            if ($horario['requiere_justificacion']) {
                $bitacora = Bitacora::create([
                    'persona_id'     => (string) $empleado->id_empleado,
                    'id_guardia'     => $guardiaId,
                    'tipo_evento'    => $tipoEvento,
                    'estado_evento'  => 'revision_horario',
                    'fecha_hora'     => now(),
                    'id_planta'      => $plantaId,
                    'revision_exhaustiva' => $revisionF10,  // ← CAMBIO: true si también toca F10
                ]);

                $this->notificarGuardiasPendiente($plantaId, $bitacora, $empleado, 'justificacion');

                // Mensaje según si también toca F10 o solo horario
                $message = $revisionF10
                    ? 'Acceso fuera de horario. Espere aquí, el guardia debe autorizar su acceso y realizar revisión.'
                    : 'Acceso fuera de horario. Espere aquí, el guardia debe autorizar su acceso.';

                return response()->json([
                    'success'               => true,
                    'autorizado'            => false,
                    'pendiente'             => true,
                    'tipo_pendiente'        => 'justificacion',
                    'bitacora_id'           => $bitacora->id_bitacora,
                    'tipo_evento'           => $tipoEvento,
                    'usos_restantes'        => $usosRestantes,
                    'persona'               => $this->datosPersonaEmpleado($empleado, $validacion),
                    'message'               => $message,
                ]);
            }

            if ($revisionF10) {
                $bitacora = Bitacora::create([
                    'persona_id'     => (string) $empleado->id_empleado,
                    'id_guardia'     => $guardiaId,
                    'tipo_evento'    => $tipoEvento,
                    'estado_evento'  => 'pendiente_revision',
                    'fecha_hora'     => now(),
                    'id_planta'      => $plantaId,
                    'revision_exhaustiva' => true,
                ]);

                $this->notificarGuardiasPendiente($plantaId, $bitacora, $empleado, 'revision');

                return response()->json([
                    'success'               => true,
                    'autorizado'            => false,
                    'pendiente'             => true,
                    'tipo_pendiente'        => 'revision',
                    'bitacora_id'           => $bitacora->id_bitacora,
                    'tipo_evento'           => $tipoEvento,
                    'usos_restantes'        => $usosRestantes,
                    'persona'               => $this->datosPersonaEmpleado($empleado, $validacion),
                    'message'               => 'Atención: Espere al guardia para revisión.',
                ]);
            }

            $bitacora = Bitacora::create([
                'persona_id'     => (string) $empleado->id_empleado,
                'id_guardia'     => $guardiaId,
                'tipo_evento'    => $tipoEvento,
                'estado_evento'  => 'autorizado',
                'fecha_hora'     => now(),
                'id_planta'      => $plantaId,
            ]);

            // ============================================================
            // CAMBIO V7 (N2): Mensaje según el tipo de permiso.
            // ============================================================
            // - Entrada a planta propia (sin permiso): "Entrada registrada correctamente."
            // - Entrada por interplantas: "Entrada autorizada con permiso INTERPLANTAS hacia {planta}."
            // - Salida en hora (sin permiso): "Salida registrada correctamente."
            // - Salida por pase_salida: "Salida autorizada con permiso de PASE DE SALIDA."
            // - Salida por interplantas: "Salida autorizada con permiso INTERPLANTAS hacia {planta}."
            // ============================================================

            $message = ucfirst($tipoEvento) . ' registrada correctamente.';

            // Si es ENTRADA por interplantas (planta distinta)
            if ($tipoEvento === 'entrada' && $validacion['es_interplantas']) {
                $plantaDestino = \App\Models\Planta::find($validacion['planta_destino_id'] ?? null);
                $nombrePlanta = $plantaDestino?->nombre ?? 'otra planta';
                $message = "Entrada autorizada con permiso INTERPLANTAS hacia {$nombrePlanta}.";
            }

            // Si es SALIDA antes de hora, verificar qué permiso lo autorizó.
            // validarHorario() ya validó que tenga permiso (si no, habría denegado).
            // Aquí solo construimos el mensaje informativo.
            if ($tipoEvento === 'salida' && $empleado->tipoEmpleado() === 'operativo') {
                $permiso = $accesoService->obtenerPermisoParaSalir($empleado->id_empleado);
                if ($permiso) {
                    if ($permiso['tipo'] === 'pase_salida') {
                        $message = 'Salida autorizada con permiso de PASE DE SALIDA.';
                    } elseif ($permiso['tipo'] === 'interplantas') {
                        $plantaDestino = \App\Models\Planta::find($permiso['planta_destino_id']);
                        $nombrePlanta = $plantaDestino?->nombre ?? 'otra planta';
                        $message = "Salida autorizada con permiso INTERPLANTAS hacia {$nombrePlanta}.";
                    }
                }
            }

            // Avisos de búsqueda manual (no se pierden)
            if ($origen === 'manual' && $usosRestantes <= 1 && $usosRestantes > 0) {
                $message .= " ¡URGE REPOSICIÓN DE CREDENCIAL! Le queda {$usosRestantes} uso manual.";
            } elseif ($origen === 'manual') {
                $message .= " (Le quedan {$usosRestantes} usos manuales.)";
            }

            return response()->json([
                'success'         => true,
                'autorizado'      => true,
                'pendiente'       => false,
                'bitacora_id'     => $bitacora->id_bitacora,
                'tipo_evento'     => $tipoEvento,
                'usos_restantes'  => $usosRestantes,
                'persona'         => $this->datosPersonaEmpleado($empleado, $validacion),
                'message'         => $message,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lógica para escaneo de QR de externos.
     * CAMBIO V5: Bitacora usa 'persona_id' con prefijo "E-" (ej: "E-5") en lugar de 'id_externos'.
     */
    private function procesarExterno($codigo, $origen, $turnoId, $plantaId, $guardiaId)
    {
        $detalle = SolicitudExternoDetalle::with(['solicitudExt', 'externo'])
            ->where('codigo_qr', $codigo)
            ->first();

        if (!$detalle || !$detalle->qrActivo()) {
            return response()->json(['success' => false, 'message' => 'QR no válido.']);
        }

        $solicitud = $detalle->solicitudExt;
        $externo = $detalle->externo;

        // FIX C-2: Antes solo se aceptaba estado='aprobado'. Cuando el aprobador
        // usaba aprobación parcial (algun externo cancelado), la solicitud quedaba
        // 'aprobado_parcial' y NINGUN externo podía entrar, ni siquiera los
        // autorizados. Ahora aceptamos ambos estados; la validación por-persona
        // (qrActivo) ya filtra a los cancelados.

        // V5: Buscar último evento por persona_id (con prefijo E-)
        $ultimoEvento = Bitacora::where('persona_id', 'E-' . $externo->id_externos)
            ->hoy()
            ->noDenegados()
            ->latest('fecha_hora')
            ->first();

        $tipoEvento = (!$ultimoEvento || $ultimoEvento->tipo_evento === 'salida')
            ? 'entrada' : 'salida';

        // ¿El externo está DENTRO? (última bitácora = entrada autorizada)
        $estaDentro = $ultimoEvento
            && $ultimoEvento->tipo_evento === 'entrada'
            && $ultimoEvento->estado_evento === 'autorizado';

        // ============================================================
        // CAMBIO V7 (Problema C - fix): si el externo está DENTRO y quiere
        // SALIR, no validar el estado de la solicitud. Ya está adentro,
        // tiene que poder salir aunque el permiso haya expirado.
        // Solo validar estado si quiere ENTRAR.
        // ============================================================
        if (!$estaDentro) {
            // Quiere entrar → validar estado de la solicitud
            if (!in_array($solicitud->estado_actual, ['aprobado', 'aprobado_parcial'], true)
                || !$solicitud->estaVigenteAprobada()) {
                Bitacora::create([
                    'persona_id'     => 'E-' . $externo->id_externos,
                    'id_guardia'     => $guardiaId,
                    'tipo_evento'    => 'entrada',
                    'estado_evento'  => 'denegado',
                    'motivo_denengacion' => 'credencial_expirada',
                    'fecha_hora'     => now(),
                    'id_planta'      => $plantaId,
                ]);
                return response()->json(['success' => false, 'message' => 'Credencial expirada o solicitud no aprobada.']);
            }

            if ($solicitud->id_planta_destino != $plantaId) {
                Bitacora::create([
                    'persona_id'     => 'E-' . $externo->id_ternos,
                    'id_guardia'     => $guardiaId,
                    'tipo_evento'    => 'entrada',
                    'estado_evento'  => 'denegado',
                    'motivo_denegacion' => 'planta_no_autorizada',
                    'fecha_hora'     => now(),
                    'id_planta'      => $plantaId,
                ]);
                return response()->json(['success' => false, 'message' => 'Planta no autorizada para esta credencial.']);
            }
        }

        // FIX D-2: Validar continuidad de planta para externos — igual que
        // empleados, no pueden operar en otra planta si su última bitácora
        // está en una distinta. REGLA TAJANTE: Sin excepciones.
        $continuidad = $this->validarContinuidadPlanta(
            'E-' . $externo->id_externos,
            $tipoEvento,
            $plantaId
        );
        if ($continuidad !== null) {
            Bitacora::create([
                'persona_id'     => 'E-' . $externo->id_externos,
                'id_guardia'     => $guardiaId,
                'tipo_evento'    => $tipoEvento,
                'estado_evento'  => 'denegado',
                'motivo_denegacion' => 'planta_inconsistente',
                'fecha_hora'     => now(),
                'id_planta'      => $plantaId,
            ]);
            return response()->json(['success' => false, 'message' => $continuidad]);
        }

        $bitacora = Bitacora::create([
            'persona_id'     => 'E-' . $externo->id_externos,
            'id_guardia'     => $guardiaId,
            'tipo_evento'    => $tipoEvento,
            'estado_evento'  => 'autorizado',
            'fecha_hora'     => now(),
            'id_planta'      => $plantaId,
        ]);

        $this->notificarSolicitanteExternoLlegada($solicitud, $externo, $plantaId);

        if ($tipoEvento === 'entrada') {
            $this->notificarGuardiasExternoLlegada($plantaId, $externo);
        }

        $fotoUrl = $externo->foto
            ? Storage::url($externo->foto)
            : 'https://ui-avatars.com/api/?name=' . urlencode($externo->nombre_completo) . '&background=0D8ABC&color=fff';

        return response()->json([
            'success'         => true,
            'autorizado'      => true,
            'pendiente'       => false,
            'bitacora_id'     => $bitacora->id_bitacora,
            'tipo_evento'     => $tipoEvento,
            'persona'         => [
                'nombre'        => $externo->nombre_completo,
                'puesto'        => $solicitud->empresa ?? 'Visitante',
                'tipo_empleado' => ucfirst($solicitud->tipo),
                'planta'        => $solicitud->plantaDestino?->nombre,
                'foto'          => $fotoUrl,
            ],
            'message'         => ucfirst($tipoEvento) . ' registrada.',
        ]);
    }

    // ============================================================
    // 3. JUSTIFICACIÓN DE ANOMALÍA DE HORARIO (guardia)
    // ============================================================

    /**
     * El guardia selecciona una justificación del catálogo para una
     * bitácora en estado 'revision_horario'.
     *
     * Ruta: POST /api/guardia/justificar
     *
     * Body:
     *   - bitacora_id: int
     *   - id_justificacion: int (FK a justificaciones_horario)
     *
     * Lógica:
     *   - Si la bitácora tenía F10 pendiente (revision_exhaustiva=true),
     *     el estado pasa a 'pendiente_revision' (el guardia debe hacer
     *     la revisión física después).
     *   - Si no tenía F10, el estado pasa a 'autorizado' directamente.
     */
    public function justificarEscaneo(Request $request)
    {
        $request->validate([
            'bitacora_id'      => ['required', 'integer', 'exists:bitacora,id_bitacora'],
            'id_justificacion' => ['required', 'integer', 'exists:justificaciones_horario,id_justificacion'],
        ]);

        $bitacora = Bitacora::find($request->bitacora_id);

        // Solo se pueden justificar bitácoras en estado 'revision_horario'
        if ($bitacora->estado_evento !== 'revision_horario') {
            return response()->json([
                'success' => false,
                'message' => 'Esta bitácora no está pendiente de justificación.',
            ], 400);
        }

        // Verificar que sea de la planta del guardia
        $user = Auth::user();
        $plantaId = $user->guardia?->id_planta;
        if ($bitacora->id_planta != $plantaId) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos sobre este registro.',
            ], 403);
        }

        // Asignar la justificación
        $bitacora->id_justificacion = $request->id_justificacion;

        // Si también había F10 pendiente, pasar a 'pendiente_revision'
        // Si no, pasar a 'autorizado'
        $bitacora->estado_evento = $bitacora->revision_exhaustiva
            ? 'pendiente_revision'
            : 'autorizado';

        $bitacora->save();

        return response()->json([
            'success'  => true,
            'estado'   => $bitacora->estado_evento,
            'autorizado' => $bitacora->estado_evento === 'autorizado',
        ]);
    }

    // ============================================================
    // 4. RESOLVER REVISIÓN F10 (guardia)
    // ============================================================

    /**
     * El guardia resuelve una revisión F10: marca todo_bien o anomalia.
     *
     * Ruta: POST /api/guardia/resolve
     *
     * Body:
     *   - bitacora_id: int
     *   - resultado: 'todo_bien' | 'anomalia'
     *   - descripcion: string|null (si es anomalia)
     *
     * Lógica:
     *   - todo_bien → estado='autorizado'
     *   - anomalia → estado='autorizado' + crea RevisionHallazgo con descripción
     *     (el externo pasa, pero se registra el hallazgo para auditoría)
     */
    public function resolverPendiente(Request $request)
    {
        $request->validate([
            'bitacora_id'  => ['required', 'integer', 'exists:bitacora,id_bitacora'],
            'resultado'    => ['required', 'in:todo_bien,anomalia'],
            'descripcion'  => ['nullable', 'string', 'max:1000'],
        ]);

        $bitacora = Bitacora::find($request->bitacora_id);

        // Solo se pueden resolver bitácoras en 'pendiente_revision'
        if ($bitacora->estado_evento !== 'pendiente_revision') {
            return response()->json([
                'success' => false,
                'message' => 'Esta bitácora no está pendiente de revisión F10.',
            ], 400);
        }

        // Verificar planta del guardia
        $user = Auth::user();
        $plantaId = $user->guardia?->id_planta;
        if ($bitacora->id_planta != $plantaId) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos sobre este registro.',
            ], 403);
        }

        // Resolver
        $bitacora->estado_evento = 'autorizado';
        $bitacora->revision_exhaustiva = true;
        $bitacora->save();

        // Si fue anomalia, registrar el hallazgo
        if ($request->resultado === 'anomalia') {
            RevisionHallazgo::create([
                'id_bitacora'          => $bitacora->id_bitacora,
                'resultado'            => 'anomalia',
                'descripcion_hallazgo' => $request->descripcion,
            ]);
        }

        return response()->json([
            'success'    => true,
            'estado'     => 'autorizado',
            'autorizado' => true,
        ]);
    }

    // ============================================================
    // 5. POLLING DE ESTADO (kiosko)
    // ============================================================

    /**
     * El kiosko pregunta si una bitácora ya fue autorizada o denegada.
     *
     * Ruta: GET /api/scan/status/{bitacora_id}
     *
     * El kiosko llama esto cada ~2s mientras el empleado espera.
     * Cuando retorna autorizado=true o denegado=true, el kiosko
     * muestra el mensaje correspondiente.
     */
    public function estadoRevision($bitacora_id)
    {
        $bitacora = Bitacora::find($bitacora_id);
        if (!$bitacora) {
            return response()->json(['estado' => 'invalido', 'autorizado' => false, 'denegado' => false]);
        }

        return response()->json([
            'estado'        => $bitacora->estado_evento,
            'autorizado'    => $bitacora->estado_evento === 'autorizado',
            'denegado'      => $bitacora->estado_evento === 'denegado',
            'pendiente'     => $bitacora->estaPendiente(),
            'tipo_pendiente' => $bitacora->estado_evento === 'revision_horario' ? 'justificacion' : 'revision',
        ]);
    }

    // ============================================================
    // 6. PANEL DEL GUARDIA (dashboard web)
    // ============================================================

    public function panelGuardia()
    {
        $user = Auth::user();
        if (!$user->esGuardia()) abort(403);

        $kioskosActivos = TurnoGuardia::where('id_guardia', $user->username)
            ->whereNull('fecha_hora_fin')
            ->with('planta')
            ->get();

        // El middleware global NoCacheResponses ya pone los headers anti-caché.
        return view('guardia.dashboard', compact('kioskosActivos'));
    } 

    /**
     * API: pendientes del guardia (F10 + justificaciones de horario).
     * Ruta: GET /api/guardia/pending
     *
     * V5: Usa persona_id unificado. Los empleados no tienen prefijo,
     * los externos tienen prefijo "E-".
     */
    public function pendientesGuardia()
    {
        $user = Auth::user();
        $plantaId = $user->guardia?->id_planta;

        // Pendientes de revisión F10
        $revisiones = Bitacora::pendientesRevision()
            ->dePlanta($plantaId)
            ->hoy()
            ->orderBy('fecha_hora', 'asc')
            ->get();

        // Pendientes de justificación de horario
        $justificaciones = Bitacora::revisionHorario()
            ->dePlanta($plantaId)
            ->hoy()
            ->orderBy('fecha_hora', 'asc')
            ->get();

        // V5: Extraer IDs de empleados desde persona_id (los que NO empiezan con "E-")
        $idsEmpleados = $revisiones->pluck('persona_id')
            ->merge($justificaciones->pluck('persona_id'))
            ->unique()
            ->filter(fn($id) => !str_starts_with($id, 'E-'))  // V5: sin prefijo = empleado
            ->map(fn($id) => (int) $id)  // Convertir a int para buscar en sitec
            ->toArray();

        // Buscar los nombres de los empleados en sitec (1 sola query)
        $empleados = Empleado::whereIn('id_empleado', $idsEmpleados)
            ->pluck('nombre_completo', 'id_empleado');

        // Función para mapear cada bitácora a un array para el frontend
        $mapearFn = function ($b) use ($empleados) {
            // V5: Si persona_id no empieza con "E-", es empleado
            $empId = (int) $b->persona_id;
            return [
                'bitacora_id' => $b->id_bitacora,
                'nombre'      => $empleados[$empId] ?? 'Desconocido',
                'hora'        => $b->fecha_hora->format('H:i'),
                'tipo_evento' => $b->tipo_evento,
                'motivo'      => $b->motivo_denegacion,
            ];
        };

        return response()->json([
            'revisiones'      => $revisiones->map($mapearFn),
            'justificaciones' => $justificaciones->map($mapearFn),
        ]);
    }

    /**
     * API: feed de actividad reciente (últimos 15 eventos de la planta hoy).
     * Ruta: GET /api/guardia/feed
     *
     * V5: Usa persona_id unificado con prefijo "E-" para externos.
     */
    public function feedActividad()
    {
        $user = Auth::user();
        $plantaId = $user->guardia?->id_planta;

        $eventos = Bitacora::where('id_planta', $plantaId)
            ->whereDate('fecha_hora', today())
            ->orderBy('fecha_hora', 'desc')
            ->limit(15)
            ->get();

        if ($eventos->isEmpty()) {
            return response()->json([]);
        }

        // V5: Separar empleados (sin prefijo "E-") y externos (con prefijo "E-")
        $idsEmpleados = $eventos->filter(fn($b) => !str_starts_with($b->persona_id, 'E-'))
            ->pluck('persona_id')->unique()
            ->map(fn($id) => (int) $id)->toArray();
        // FIX V7 (Problema 4): cargar el empleado con su planta (para mostrar de dónde viene)
        $empleados = Empleado::with('puesto', 'clasificacion')
            ->whereIn('id_empleado', $idsEmpleados)
            ->get()
            ->keyBy('id_empleado');

        // V5: Para externos, extraer el ID numérico quitando el prefijo "E-"
        $idsExternos = $eventos->filter(fn($b) => str_starts_with($b->persona_id, 'E-'))
            ->pluck('persona_id')->unique()
            ->map(fn($id) => (int) str_replace('E-', '', $id))->toArray();
        $externos = \App\Models\Externo::whereIn('id_externos', $idsExternos)
            ->pluck('nombre_completo', 'id_externos');

        // FIX V7 (Problema 3 + fix externos con 2 solicitudes): cargar TODOS los
        // detalles de solicitud_externo de estos externos, agrupados por id_externos.
        // Así podemos buscar cuál solicitud estaba vigente en el momento exacto
        // del escaneo (por fecha_hora), y mostrar el tipo correcto (Visita/Contratista)
        // incluso si la persona entró como Visita y luego como Contratista el mismo día.
        $detallesExt = \App\Models\SolicitudExternoDetalle::with('solicitudExt')
            ->whereIn('id_externos', $idsExternos)
            ->get()
            ->groupBy('id_externos');

        $resultado = $eventos->map(function ($b) use ($empleados, $externos, $detallesExt) {
            // V5: Determinar si es empleado o externo según el prefijo de persona_id
            if (!str_starts_with($b->persona_id, 'E-')) {
                // Es empleado: persona_id contiene el id_empleado numérico
                $empId = (int) $b->persona_id;
                $emp = $empleados[$empId] ?? null;
                $nombre = $emp?->nombre_completo ?? 'Desconocido';
                $tipoPersona = 'Emp';
                // FIX V7 (Problema 4): planta base del empleado (de dónde viene)
                $plantaBase = $emp?->plantaNombre() ?? '—';
            } else {
                // Es externo: persona_id contiene "E-" + id_externos
                $extId = (int) str_replace('E-', '', $b->persona_id);
                $nombre = $externos[$extId] ?? 'Visitante';

                // FIX V7 (Problema 3): distinguir visita de contratista.
                // Si el externo tiene varias solicitudes, buscar la que estaba
                // vigente en el momento exacto del escaneo (por fecha_hora).
                $detallesDeEsteExterno = $detallesExt[$extId] ?? collect();
                $detalleMatch = $detallesDeEsteExterno->first(function ($d) use ($b) {
                    $sol = $d->solicitudExt;
                    if (!$sol) return false;
                    return $sol->id_planta_destino == $b->id_planta
                        && $sol->fecha_inicio <= $b->fecha_hora
                        && $sol->fecha_fin >= $b->fecha_hora;
                });
                $tipoExt = $detalleMatch?->solicitudExt?->tipo ?? 'visitante';
                $tipoPersona = $tipoExt === 'contratista' ? 'Contratista' : 'Visita';
                $plantaBase = '—';  // los externos no tienen planta base
            }

            // Icono y color según el estado del evento
            $icono = match($b->estado_evento) {
                'autorizado'          => $b->tipo_evento === 'entrada' ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right',
                'denegado'            => 'bi-x-circle',
                'pendiente_revision'  => 'bi-shield-exclamation',
                'revision_horario'    => 'bi-clock-history',
                default               => 'bi-question-circle',
            };
            $color = match($b->estado_evento) {
                'autorizado'          => 'success',
                'denegado'            => 'danger',
                'pendiente_revision'  => 'warning',
                'revision_horario'    => 'warning',
                default               => 'secondary',
            };

            return [
                'hora'         => $b->fecha_hora->format('H:i'),
                'icono'        => $icono,
                'color'        => $color,
                'evento'       => $b->tipo_evento,
                'estado'       => $b->estado_evento,
                'nombre'       => $nombre,
                'tipo_persona' => $tipoPersona,
                'planta_base'  => $plantaBase ?? '—',  // ← NUEVO (Problema 4)
                'motivo'       => $b->motivo_denegacion,
            ];
        });

        return response()->json($resultado);
    }

    /**
     * API: personas actualmente dentro de la planta.
     * Ruta: GET /api/guardia/personas-dentro
     *
     * V5: Usa persona_id unificado con prefijo "E-" para distinguir
     * empleados de externos.
     */
    public function personasDentro()
    {
        $user = Auth::user();
        $plantaId = request()->query('planta_id');

        // Determinar la planta a consultar según el rol
        if ($user->esGuardia()) {
            $plantaId = $user->guardia?->id_planta;
        } elseif ($user->isAprobador()) {
            $plantaId = $user->planta_id;
        } elseif ($user->isAdmin() || $user->isSuperadmin()) {
            if (!$plantaId) {
                $plantaId = $user->planta_id ?? Planta::where('activa', true)->value('id_planta');
            }
        }

        if (!$plantaId) {
            return response()->json(['total' => 0, 'personas' => [], 'empleados' => 0, 'visitantes' => 0, 'planta_nombre' => 'Sin planta']);
        }

        $planta = Planta::find($plantaId);

        // Último evento de cada persona hoy (sin importar si es empleado o externo).
        // CAMBIO V7 (fix): solo contar bitácoras 'autorizado'. Las que están
        // en 'revision_horario' o 'pendiente_revision' NO cuentan como "dentro"
        // porque la persona está esperando al guardia, no realmente adentro.
        // Si el guardia justifica/resuelve, pasa a 'autorizado' y aparece aquí.
        $eventos = Bitacora::where('id_planta', $plantaId)
            ->whereDate('fecha_hora', today())
            ->where('estado_evento', 'autorizado')
            ->orderBy('fecha_hora', 'desc')
            ->get()
            // V5: Agrupar por persona_id (unificado)
            ->unique(fn($b) => $b->persona_id);
            
        // Solo los que tienen entrada como último evento
        $dentro = $eventos->filter(fn($b) => $b->tipo_evento === 'entrada')->values();

        if ($dentro->isEmpty()) {
            return response()->json([
                'total' => 0, 'personas' => [], 'empleados' => 0, 'visitantes' => 0,
                'planta_nombre' => $planta?->nombre ?? 'N/A'
            ]);
        }

        // V5: Separar IDs de empleados (sin prefijo "E-") y externos (con prefijo "E-")
        $idsEmpleados = $dentro->filter(fn($b) => !str_starts_with($b->persona_id, 'E-'))
            ->pluck('persona_id')->unique()
            ->map(fn($id) => (int) $id)->toArray();
        $empleados = Empleado::with('puesto')->whereIn('id_empleado', $idsEmpleados)->get()->keyBy('id_empleado');

        $idsExternos = $dentro->filter(fn($b) => str_starts_with($b->persona_id, 'E-'))
            ->pluck('persona_id')->unique()
            ->map(fn($id) => (int) str_replace('E-', '', $id))->toArray();
        $externos = \App\Models\Externo::whereIn('id_externos', $idsExternos)->get()->keyBy('id_externos');

        // FIX V7 (Problema A + fix externos con 2 solicitudes): cargar TODOS los
        // detalles de solicitud_externo de estos externos, agrupados por id_externos.
        // Así podemos buscar cuál solicitud estaba vigente en el momento exacto
        // de la entrada, y mostrar el tipo correcto (Visita/Contratista) aunque
        // la persona tenga 2 solicitudes el mismo día.
        $detallesExt = \App\Models\SolicitudExternoDetalle::with('solicitudExt')
            ->whereIn('id_externos', $idsExternos)
            ->get()
            ->groupBy('id_externos');

        // Construir la lista de personas para el frontend
        $lista = $dentro->map(function ($b) use ($empleados, $externos, $detallesExt) {
            // V5: Si persona_id no empieza con "E-", es empleado
            if (!str_starts_with($b->persona_id, 'E-')) {
                $empId = (int) $b->persona_id;
                $emp = $empleados->get($empId);
                return [
                    'tipo'        => 'empleado',
                    'id'          => $b->persona_id,
                    'nombre'      => $emp?->nombre_completo ?? 'Desconocido',
                    'puesto'      => $emp?->puesto?->nombre ?? '—',
                    'hora_entrada'=> $b->fecha_hora->format('H:i'),
                ];
            } else {
                // Es externo: extraer el ID numérico quitando el prefijo "E-"
                $extId = (int) str_replace('E-', '', $b->persona_id);
                $ext = $externos->get($extId);

                // FIX V7 (Problema A): distinguir visita de contratista.
                // Si el externo tiene varias solicitudes, buscar la que estaba
                // vigente en el momento exacto de la entrada (por fecha_hora).
                $detallesDeEsteExterno = $detallesExt[$extId] ?? collect();
                $detalleMatch = $detallesDeEsteExterno->first(function ($d) use ($b) {
                    $sol = $d->solicitudExt;
                    if (!$sol) return false;
                    return $sol->id_planta_destino == $b->id_planta
                        && $sol->fecha_inicio <= $b->fecha_hora
                        && $sol->fecha_fin >= $b->fecha_hora;
                });
                $tipoExt = $detalleMatch?->solicitudExt?->tipo ?? 'visitante';
                $tipoLabel = $tipoExt === 'contratista' ? 'Contratista' : 'Visita';

                return [
                    'tipo'        => 'visitante',  // se mantiene para el conteo
                    'id'          => $b->persona_id,
                    'nombre'      => $ext?->nombre_completo ?? 'Visitante',
                    'puesto'      => $tipoLabel,
                    'hora_entrada'=> $b->fecha_hora->format('H:i'),
                ];
            }
        });

        return response()->json([
            'total'        => $lista->count(),
            'empleados'    => $lista->where('tipo', 'empleado')->count(),
            'visitantes'   => $lista->where('tipo', 'visitante')->count(),
            'personas'     => $lista->sortBy('hora_entrada')->values(),
            'planta_nombre'=> $planta?->nombre ?? 'N/A',
            'planta_id'    => $plantaId,
        ]);
    }

    /**
     * API: denegados no vistos por el guardia.
     * Ruta: GET /api/guardia/denegados
     *
     * V5: Usa persona_id unificado con prefijo "E-" para externos.
     */
    public function denegadosGuardia()
    {
        $user = Auth::user();
        $plantaId = $user->guardia?->id_planta;

        $denegados = Bitacora::denegados()
            ->dePlanta($plantaId)
            ->hoy()
            ->orderBy('fecha_hora', 'desc')
            ->get();

        if ($denegados->isEmpty()) {
            return response()->json([]);
        }

        // V5: Separar IDs de empleados y externos usando el prefijo "E-"
        $idsEmpleados = $denegados->filter(fn($b) => !str_starts_with($b->persona_id, 'E-'))
            ->pluck('persona_id')->unique()
            ->map(fn($id) => (int) $id)->toArray();
        $empleados = Empleado::whereIn('id_empleado', $idsEmpleados)
            ->pluck('nombre_completo', 'id_empleado');

        $idsExternos = $denegados->filter(fn($b) => str_starts_with($b->persona_id, 'E-'))
            ->pluck('persona_id')->unique()
            ->map(fn($id) => (int) str_replace('E-', '', $id))->toArray();
        $externos = \App\Models\Externo::whereIn('id_externos', $idsExternos)
            ->pluck('nombre_completo', 'id_externos');

        $resultado = $denegados->map(function ($b) use ($empleados, $externos) {
            // Traducir el código de denegación a texto legible
            $motivoTexto = match($b->motivo_denegacion) {
                'sin_clasificar'        => 'Empleado sin clasificar (acuda a RRHH)',
                'sin_planta'            => 'Planta no configurada',
                'planta_no_autorizada'  => 'Planta no autorizada',
                'salida_temprana'       => 'Intentó salir antes de su hora',
                'empleado_inactivo'     => 'Empleado inactivo',
                'manual_bloqueado'      => 'Búsqueda manual bloqueada',
                'credencial_expirada'   => 'Credencial expirada',
                default                 => 'Motivo desconocido',
            };

            // V5: Obtener el nombre según el prefijo de persona_id
            if (!str_starts_with($b->persona_id, 'E-')) {
                // Es empleado
                $empId = (int) $b->persona_id;
                $nombre = $empleados[$empId] ?? 'Desconocido';
            } else {
                // Es externo
                $extId = (int) str_replace('E-', '', $b->persona_id);
                $nombre = $externos[$extId] ?? 'Visitante';
            }

            return [
                'bitacora_id' => $b->id_bitacora,
                'nombre'      => $nombre,
                'hora'        => $b->fecha_hora->format('H:i'),
                'motivo'      => $motivoTexto,
            ];
        });

        return response()->json($resultado);
    }

    /**
     * Cierra TODOS los kioskos activos del guardia + logout.
     * CAMBIO V5: Busca por $user->username (que es el id_guardia).
     */
    public function terminarTurno(Request $request)
    {
        $user = Auth::user();

        $turnosCerrados = TurnoGuardia::where('id_guardia', $user->username)
            ->whereNull('fecha_hora_fin')
            ->update(['fecha_hora_fin' => now()]);

        if ($turnosCerrados > 0) {
            LogSistema::registrar(
                'FIN_TURNO_GUARDIA',
                "Turno finalizado. {$turnosCerrados} kiosko(s) cerrado(s)."
            );
        }

        if ($user) {
            $user->remember_token = null;
            $user->save();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // M5 [ScaModal] — Soporte AJAX
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('login'),
            ]);
        }

        return redirect()->route('login');
    }

    /**
     * FIX D-1 / D-2: Valida que la persona no opere en otra planta si su
     * última bitácora (no denegada) está en una planta distinta.
     *
     * Lógica (REGLA TAJANTE, SIN EXCEPCIONES):
     *   - Última = entrada en A + intenta entrada en B → DENEGAR.
     *   - Última = entrada en A + intenta salida en B → DENEGAR.
     *   - Última = salida en A + intenta entrada en B → PERMITIR.
     *   - Última = salida en A + intenta salida en B → DENEGAR.
     *   - Sin bitácora previa → PERMITIR.
     *
     * Aplica para empleados y externos.
     *
     * @param string $personaId   ID en bitácora (id_empleado o "E-{id_externos}")
     * @param string $tipoEvento  'entrada' | 'salida'
     * @param int    $plantaId    Planta donde se intenta operar ahora
     * @return string|null  null=permitido, string=mensaje de denegación
     */
    private function validarContinuidadPlanta(string $personaId,string $tipoEvento,int $plantaId): ?string {
        // Buscar la última bitácora NO denegada (sin importar el día)
        $ultima = Bitacora::where('persona_id', $personaId) //correccion quitar empleado_id de sitec, no se usa aqui, por que se usa en persona_id de emplados_ext_sca_
            ->noDenegados()
            ->latest('fecha_hora')
            ->first();

        // Sin bitácora previa → primera vez → permitido
        if (!$ultima) {
            return null;
        }

        $plantaUltimaNombre = $ultima->planta?->nombre ?? "Planta #{$ultima->id_planta}";
        $plantaActualNombre = \App\Services\PlantaCacheService::nombrePlanta($plantaId)
            ?? "Planta #{$plantaId}";

        // Si la última bitácora fue en la misma planta actual → permitido
        if ((int) $ultima->id_planta === $plantaId) {
            return null;
        }

        // Si la planta es distinta, validar según tipo de evento
        if ($ultima->tipo_evento === 'entrada') {
            if ($tipoEvento === 'entrada') {
                // Caso: entrada en A + intenta entrada en B
                return "Su última entrada fue en {$plantaUltimaNombre}. Registre su salida allí antes de operar en {$plantaActualNombre}.";
            }
            // Caso: entrada en A + intenta salida en B
            return "No puede registrar salida en {$plantaActualNombre}: su entrada fue en {$plantaUltimaNombre}. Registre su salida allí.";
        }

        // tipo_evento de la última = 'salida'
        if ($tipoEvento === 'entrada') {
            // Caso: salida en A + intenta entrada en B → permitido
            return null;
        }

        // Caso: salida en A + intenta salida en B → denegar
        return "No tiene entrada registrada en {$plantaActualNombre}. Su última salida fue en {$plantaUltimaNombre}.";
    }

    /**
     * FIX E: Notifica a Aprobadores, Admins y Supers que un empleado
     * intentó acceder pero su clasificación en sitec cambió.
     */
    private function notificarCambioClasificacionSitec($empleado, $plantaVieja, $plantaNueva, $plantaIntento)
    {
        $mensaje = "El empleado {$empleado->nombre_completo} (ID: {$empleado->id_empleado}) intentó acceder a {$plantaIntento}, pero su clasificación en sitec cambió. Su planta fue actualizada automáticamente de {$plantaVieja} a {$plantaNueva}. Si esto es un error, ajuste su planta manualmente en el sistema.";
        
        $usuarios = \App\Models\UsuarioSca::whereHas('rol', function ($q) {
            $q->whereIn('nombre', ['Aprobador', 'Admin', 'Superadmin']);
        })->whereNull('fecha_baja')->get();

        foreach ($usuarios as $u) {
            \App\Models\Notificacion::create([
                'id_usuario_sca' => $u->id_usuario_sca,
                'titulo'         => 'Cambio de Clasificación Detectado',
                'mensaje'        => $mensaje,
                'url'            => route('empleados.edit', $empleado->id_empleado),
                'leida'          => false,
                'tipo'           => 'sistema',
            ]);
        }
        
        LogSistema::registrar(
            'CAMBIO_CLASIFICACION_SITEC',
            "Empleado {$empleado->id_empleado} ({$empleado->nombre_completo}) cambió de clasificación en sitec. Planta actualizada de {$plantaVieja} a {$plantaNueva}. Intento de acceso en {$plantaIntento}."
        );
    }

    // ============================================================
    // HELPERS PRIVADOS
    // ============================================================

    /**
     * Determina el tipo_evento (entrada/salida) según el último evento
     * y el modo del kiosko.
     *
     * Retorna:
     *   - 'entrada' | 'salida' si es válido
     *   - null si el modo del kiosko no permite este evento
     */
    private function determinarTipoEvento(?Bitacora $ultimoEvento, string $modoKiosko): ?string
    {
        if ($modoKiosko === 'entrada') {
            // Si ya tiene entrada → no puede entrar de nuevo
            if ($ultimoEvento && $ultimoEvento->tipo_evento === 'entrada') {
                return null;
            }
            return 'entrada';
        }

        if ($modoKiosko === 'salida') {
            // Si no tiene entrada → no puede salir
            if (!$ultimoEvento || $ultimoEvento->tipo_evento === 'salida') {
                return null;
            }
            return 'salida';
        }

        // Modo mixto: alterna
        return (!$ultimoEvento || $ultimoEvento->tipo_evento === 'salida')
            ? 'entrada' : 'salida';
    }

    /**
     * Mensaje de error cuando el modo del kiosko no permite el evento.
     */
    private function mensajeModoInvalido(?Bitacora $ultimoEvento, string $modoKiosko): string
    {
        if ($modoKiosko === 'entrada') {
            return 'Ya registró su entrada. Diríjase al escáner de SALIDA.';
        }
        if ($modoKiosko === 'salida') {
            return 'No tiene entrada registrada. Diríjase al escáner de ENTRADA.';
        }
        return 'No se puede registrar el evento en este momento.';
    }

    /**
     * Datos de la persona para la respuesta JSON (empleado).
     */
    private function datosPersonaEmpleado(Empleado $empleado, array $validacion): array
    {
        $plantaDisplay = $empleado->plantaNombre();
        if ($empleado->tipoEmpleado() === 'corporativo' && !$empleado->plantaId()) {
            $plantaDisplay = 'Corporativo (multiplanta)';
        }

        return [
            'nombre'        => $empleado->nombre_completo,
            'puesto'        => $empleado->puesto?->nombre,
            'tipo_empleado' => $empleado->tipoEmpleado() ? ucfirst($empleado->tipoEmpleado()) : 'No clasificado',
            'planta'        => $plantaDisplay,
            'foto'          => route('foto.empleado', ['id' => $empleado->id_empleado]),
        ];
    }

    /**
     * Notifica a los guardias con turno activo en la planta sobre un pendiente.
     *
     * V5: La columna en turnos_guardia ahora es 'id_guardia' (VARCHAR).
     *     En usuarios_sca ya no hay 'id_guardias'; el enlace es por 'username'.
     *
     * @param string $tipoPendiente 'justificacion' | 'revision'
     */
    private function notificarGuardiasPendiente(int $plantaId, Bitacora $bitacora, Empleado $empleado, string $tipoPendiente): void
    {
        // V5: Buscar turnos activos y extraer 'id_guardia' (que es el username del guardia)
        $guardiasIds = TurnoGuardia::where('id_planta', $plantaId)
            ->whereNull('fecha_hora_fin')
            ->pluck('id_guardia')  // V5: era 'id_guardias'
            ->unique();

        // V5: Buscar los usuarios_sca por 'username' (ya no por 'id_guardias')
        $usuariosIds = UsuarioSca::whereIn('username', $guardiasIds)->pluck('id_usuario_sca');

        $titulo = $tipoPendiente === 'justificacion'
            ? 'Acceso fuera de horario requiere justificación'
            : 'Revisión F10 pendiente';

        $mensaje = $tipoPendiente === 'justificacion'
            ? "El empleado {$empleado->nombre_completo} requiere justificación de horario."
            : "El empleado {$empleado->nombre_completo} fue seleccionado para revisión F10.";

        foreach ($usuariosIds as $usuarioId) {
            Notificacion::create([
                'id_usuario_sca' => $usuarioId,
                'tipo'           => $tipoPendiente === 'justificacion' ? 'justificacion_pendiente' : 'revision_pendiente',
                'titulo'         => $titulo,
                'mensaje'        => $mensaje,
                'url'            => route('guardia.dashboard'),
                'leida'          => false,
            ]);
        }
    }

    /**
     * Notifica al solicitante que su invitado llegó.
     */
    private function notificarSolicitanteExternoLlegada($solicitud, $externo, $plantaId): void
    {
        // Buscar el usuario_sca del solicitante (creador_empleado_id)
        $usuarioSolicitante = UsuarioSca::where('username', (string) $solicitud->creador_empleado_id)->first();

        if ($usuarioSolicitante) {
            $planta = Planta::find($plantaId);
            Notificacion::create([
                'id_usuario_sca' => $usuarioSolicitante->id_usuario_sca,
                'tipo'           => 'invitado_llego',
                'titulo'         => 'Tu invitado ha llegado',
                'mensaje'        => "{$externo->nombre_completo} acaba de llegar a planta {$planta?->nombre}.",
                // FIX C: Usar la ruta correcta para solicitudes externas
                'url' => route('solicitudes.show.ext', $solicitud->id_solicitud_ext),
                'leida'          => false,
            ]);
        }
    }

        /**
     * Notifica a los guardias en turno que llegó un externo.
     *
     * V5: Misma corrección que el método anterior.
     */
    private function notificarGuardiasExternoLlegada(int $plantaId, $externo): void
    {
        // V5: Buscar turnos activos y extraer 'id_guardia' (que es el username del guardia)
        $guardiasIds = TurnoGuardia::where('id_planta', $plantaId)
            ->whereNull('fecha_hora_fin')
            ->pluck('id_guardia')  // V5: era 'id_guardias'
            ->unique();

        // V5: Buscar los usuarios_sca por 'username'
        $usuariosIds = UsuarioSca::whereIn('username', $guardiasIds)->pluck('id_usuario_sca');

        foreach ($usuariosIds as $usuarioId) {
            Notificacion::create([
                'id_usuario_sca' => $usuarioId,
                'tipo'           => 'externo_llego',
                'titulo'         => 'Ha llegado un ' . ($externo->tipo === 'contratista' ? 'contratista' : 'visitante'),
                'mensaje'        => "{$externo->nombre_completo} acaba de ingresar.",
                'url'            => route('guardia.dashboard'),
                'leida'          => false,
            ]);
        }
    }

    /**
     * Proxy de fotos de empleados (desde sitec).
     * Ruta: GET /foto-empleado/{id}
     * Pública para que el kiosko pueda verla.
     */
    public function fotoEmpleado($id)
    {
        $empleado = Empleado::find($id);
        if (!$empleado || !$empleado->foto) {
            return response()->file(public_path('default-avatar.png'));
        }

        $url = config('sca_qr.url_fotos_sitec') . $empleado->foto;

        try {
            $imageContent = Http::get($url)->body();
            return response($imageContent, 200, ['Content-Type' => 'image/jpeg']);
        } catch (\Exception $e) {
            return response()->file(public_path('default-avatar.png'));
        }
    }
}