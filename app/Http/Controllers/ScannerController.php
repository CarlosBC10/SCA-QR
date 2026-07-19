<?php
// app/Http/Controllers/ScannerController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Bitacora;
use App\Models\Guardia;
use App\Models\Planta;
use App\Models\Sitec\Empleado;
use App\Models\EmpleadoExtSca;
use App\Models\Credencial;
use App\Models\TurnoGuardia;
use App\Models\RevisionHallazgo;
use App\Models\LogSistema;
use Illuminate\Support\Facades\Auth;

class ScannerController extends Controller
{
    /**
     * Muestra la vista del Kiosco.
     */
    public function kiosko(TurnoGuardia $turno)
    {
        if ($turno->fecha_hora_fin) {
            return redirect()->route('login')->withErrors('Este kiosko ha sido desactivado.');
        }
        return view('kiosko.index', compact('turno'));
    }

    /**
     * Activa el modo kiosko con PIN.
     */
    public function activarKiosko(Request $request)
    {
        $request->validate([
            'pin' => 'required',
            'planta_id' => 'required|exists:plantas,id',
            'modo' => 'required|in:entrada,salida,mixto',
        ]);

        if ($request->pin !== config('sca_qr.pin_kiosko')) {
            return back()->with('kiosko_error', 'PIN incorrecto.')->withInput();
        }

        $guardiaKiosko = Guardia::firstOrCreate(
            ['nombre_completo' => 'Kiosko Sistema'],
            ['planta_id' => $request->planta_id, 'activo' => true]
        );

        $turno = TurnoGuardia::create([
            'guardia_id' => $guardiaKiosko->id,
            'planta_id' => $request->planta_id,
            'modo' => $request->modo,
            'fecha_hora_inicio' => now(),
        ]);

        LogSistema::registrar('INICIO_TURNO_GUARDIA', "Kiosko activado en planta {$request->planta_id} (Modo: {$request->modo})");

        return redirect()->route('kiosko.show', $turno->id);
    }

    /**
     * Cierra el kiosko.
     */
    public function salirKiosko(Request $request, TurnoGuardia $turno)
    {
        if ($request->pin !== config('sca_qr.pin_kiosko')) {
            return back()->withErrors('PIN incorrecto para salir.');
        }

        $turno->fecha_hora_fin = now();
        $turno->save();

        return redirect()->route('login');
    }

    /**
     * Procesa el código escaneado.
     */
    public function procesarEscaneo(Request $request)
    {
        $codigo = trim($request->input('codigo'));
        $origen = $request->input('origen', 'kiosco');
        $turnoId = $request->input('turno_id');
        
        $turno = TurnoGuardia::find($turnoId);
        if (!$turno || $turno->fecha_hora_fin) {
            return response()->json(['success' => false, 'message' => 'Kiosko desactivado.']);
        }

        $plantaId = $turno->planta_id;
        $modoKiosko = $turno->modo;

        if (empty($codigo)) return response()->json(['success' => false, 'message' => 'Código vacío.'], 400);

        if (str_starts_with($codigo, config('sca_qr.prefijo_qr_externo'))) {
            return $this->procesarExterno($codigo, $origen, $turnoId, $plantaId);
        } else {
            return $this->procesarEmpleado($codigo, $origen, $turnoId, $plantaId, $modoKiosko);
        }
    }

    /**
     * Lógica principal de Empleados.
     */
    private function procesarEmpleado($codigo, $origen, $turnoId, $plantaId, $modoKiosko = 'mixto')
    {
        try {
            if ($origen === 'manual') {
                $empleado = Empleado::with('turno')->where('id_empleado', $codigo)->first();
            } else {
                $empleado = Empleado::with('turno')->where('semilla', $codigo)->first();
            }

            if (!$empleado) return response()->json(['success' => false, 'message' => 'QR o número de empleado no válido.']);
            if (!$empleado->estaActivo()) return response()->json(['success' => false, 'message' => 'Empleado no activo.']);

            $ext = EmpleadoExtSca::firstOrNew(['empleado_id' => $empleado->id_empleado]);
            if (!$ext->exists) {
                $ext->usos_manuales_restantes = 4;
                $ext->bloqueo_manual = false;
                $ext->save();
            }
            
            if ($ext->planta_id === null && $plantaId) {
                $ext->planta_id = $plantaId;
                $ext->save();
            }

            $usosRestantes = $ext->usos_manuales_restantes;

            // LÓGICA DE BÚSQUEDA MANUAL Y REINICIO POR QR
            if ($origen === 'manual') {
                if ($ext->bloqueo_manual || $usosRestantes <= 0) {
                    return response()->json(['success' => false, 'message' => 'Búsqueda bloqueada. URGE REPOSICIÓN DE CREDENCIAL.']);
                }

                $usoManualHoy = Bitacora::where('persona_tipo', 'empleado')
                    ->where('persona_id', $empleado->id_empleado)
                    ->where('origen_escaneo', 'manual')
                    ->whereDate('fecha_hora', today())
                    ->exists();

                if (!$usoManualHoy) {
                    $ext->usos_manuales_restantes--;
                    if ($ext->usos_manuales_restantes <= 0) $ext->bloqueo_manual = true;
                    $ext->save();
                    $usosRestantes = $ext->usos_manuales_restantes;
                }
            } elseif ($origen === 'kiosco' || $origen === 'guardia') {
                if ($ext->usos_manuales_restantes < 4 || $ext->bloqueo_manual) {
                    $ext->usos_manuales_restantes = 4;
                    $ext->bloqueo_manual = false;
                    $ext->save();
                    $usosRestantes = 4;
                }
            }

            if ($ext->tipo_empleado !== 'corporativo' && $ext->planta_id && $plantaId && $ext->planta_id != $plantaId) {
                return response()->json(['success' => false, 'message' => 'Planta no autorizada. Diríjase a su planta base.']);
            }

            $ultimoEvento = Bitacora::where('persona_tipo', 'empleado')
                ->where('persona_id', $empleado->id_empleado)
                ->whereDate('fecha_hora', today())
                ->latest('fecha_hora')
                ->first();

            $tipoEvento = null;

            if ($modoKiosko === 'entrada') {
                if ($ultimoEvento && $ultimoEvento->tipo_evento === 'entrada') {
                    return response()->json(['success' => false, 'message' => 'Ya registró su entrada. Diríjase al escáner de SALIDA.']);
                }
                $tipoEvento = 'entrada';
            } elseif ($modoKiosko === 'salida') {
                if (!$ultimoEvento || $ultimoEvento->tipo_evento === 'salida') {
                    return response()->json(['success' => false, 'message' => 'No tiene entrada registrada. Diríjase al escáner de ENTRADA.']);
                }
                $tipoEvento = 'salida';
            } else {
                $tipoEvento = (!$ultimoEvento || $ultimoEvento->tipo_evento === 'salida') ? 'entrada' : 'salida';
            }

            // VALIDACIÓN DE TURNO Y ANOMALÍAS
            $requiereJustificacion = false;
            if ($ext->tipo_empleado === 'operativo' && $empleado->turno) {
                $horaActual = now()->format('H:i:s');
                $entradaTurno = $empleado->turno->entrada;
                $salidaTurno = $empleado->turno->salida;
                
                if ($entradaTurno && $salidaTurno) {
                    $tolerancia = 15 * 60;
                    $ahoraTs = strtotime($horaActual);
                    $entradaTs = strtotime($entradaTurno) - $tolerancia;
                    $salidaTs = strtotime($salidaTurno) + $tolerancia;
                    $cruzaMedianoche = $salidaTs < $entradaTs;
                    $dentroDeTurno = false;
                    if ($cruzaMedianoche) {
                        $dentroDeTurno = ($ahoraTs >= $entradaTs || $ahoraTs <= $salidaTs);
                    } else {
                        $dentroDeTurno = ($ahoraTs >= $entradaTs && $ahoraTs <= $salidaTs);
                    }
                    if (!$dentroDeTurno) $requiereJustificacion = true;
                }
            }

            // REVISIÓN ALEATORIA
            $revisionRequerida = false;
            if ($origen === 'kiosco' && $ext->tipo_empleado && in_array($ext->tipo_empleado, ['operativo', 'administrativo'])) {
                if (!in_array($empleado->id_empleado, config('sca_qr.exentos_revision'))) {
                    if (rand(1, 100) <= config('sca_qr.probabilidad_revision')) {
                        $revisionRequerida = true;
                    }
                }
            }

            $estadoEvento = $revisionRequerida ? 'pendiente_revision' : 'autorizado';
            
            $guardiaId = 1;
            if ($turnoId) {
                $turnoTmp = TurnoGuardia::find($turnoId);
                if ($turnoTmp) $guardiaId = $turnoTmp->guardia_id;
            }
            
            // REGISTRO EN BITÁCORA
            // Si hay anomalía, el estado_anomalia queda 'pendiente' para que el guardia lo revise
            $bitacora = Bitacora::create([
                'persona_tipo' => 'empleado',
                'persona_id' => $empleado->id_empleado,
                'guardia_id' => $guardiaId,
                'turno_guardia_id' => $turnoId,
                'tipo_evento' => $tipoEvento,
                'estado_evento' => $estadoEvento,
                'origen_escaneo' => $origen,
                'fecha_hora' => now(),
                'planta_id' => $plantaId,
                'anomalia_horario' => $requiereJustificacion,
                'estado_anomalia' => $requiereJustificacion ? 'pendiente' : null,
            ]);

            $message = ucfirst($tipoEvento) . ' registrada correctamente.';
            if ($revisionRequerida) $message = 'Atención: Espere al guardia para revisión.';
            if ($requiereJustificacion) $message = 'Acceso fuera de horario. Justifique su acceso.';
            
            if ($origen === 'manual' && $usosRestantes <= 1 && $usosRestantes > 0) {
                $message .= " ¡URGE REPOSICIÓN DE CREDENCIAL! Le queda {$usosRestantes} uso manual.";
            } elseif ($origen === 'manual') {
                $message .= " (Le quedan {$usosRestantes} usos manuales.)";
            }

            return response()->json([
                'success' => true,
                'autorizado' => true,
                'revision_pendiente' => $revisionRequerida,
                'requiere_justificacion' => $requiereJustificacion,
                'bitacora_id' => $bitacora->id,
                'tipo_evento' => $tipoEvento,
                'usos_restantes' => $usosRestantes,
                'persona' => [
                    'nombre' => $empleado->nombre_completo,
                    'puesto' => $empleado->puesto?->nombre,
                    'tipo_empleado' => $ext->tipo_empleado ? ucfirst($ext->tipo_empleado) : 'No clasificado',
                    'foto' => route('foto.empleado', ['id' => $empleado->id_empleado]),
                ],
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lógica para Externos.
     */
    private function procesarExterno($codigo, $origen, $turnoId, $plantaId)
    {
        $credencial = Credencial::where('codigo_qr', $codigo)->first();

        if (!$credencial || !$credencial->activa) return response()->json(['success' => false, 'message' => 'QR no válido.']);
        if ($credencial->fecha_expiracion < now()) return response()->json(['success' => false, 'message' => 'Credencial expirada.']);

        $solicitud = $credencial->solicitud;
        if ($solicitud->planta_destino_id != $plantaId) return response()->json(['success' => false, 'message' => 'Planta no autorizada.']);

        $persona = $solicitud->persona;
        $ultimoEvento = Bitacora::where('persona_tipo', 'persona')
            ->where('persona_id', $persona->id)
            ->whereDate('fecha_hora', today())
            ->latest('fecha_hora')->first();

        $tipoEvento = (!$ultimoEvento || $ultimoEvento->tipo_evento === 'salida') ? 'entrada' : 'salida';

        $bitacora = Bitacora::create([
            'persona_tipo' => 'persona',
            'persona_id' => $persona->id,
            'guardia_id' => $turnoId ? TurnoGuardia::find($turnoId)->guardia_id : 1,
            'turno_guardia_id' => $turnoId,
            'tipo_evento' => $tipoEvento,
            'estado_evento' => 'autorizado',
            'origen_escaneo' => $origen,
            'fecha_hora' => now(),
            'planta_id' => $plantaId,
        ]);

        $fotoUrl = $persona->foto ? Storage::url($persona->foto) : 'https://ui-avatars.com/api/?name='.urlencode($persona->nombre_completo).'&background=0D8ABC&color=fff';

        return response()->json([
            'success' => true,
            'autorizado' => true,
            'revision_pendiente' => false,
            'bitacora_id' => $bitacora->id,
            'tipo_evento' => $tipoEvento,
            'persona' => [
                'nombre' => $persona->nombre_completo,
                'puesto' => $persona->empresa ?? 'Visitante',
                'tipo_empleado' => ucfirst($credencial->tipo),
                'foto' => $fotoUrl,
            ],
            'message' => ucfirst($tipoEvento) . ' registrada.'
        ]);
    }

    /**
     * Guarda la justificación del usuario en la bitácora.
     */
    public function justificarEscaneo(Request $request)
    {
        $bitacora = Bitacora::find($request->bitacora_id);
        if ($bitacora) {
            $bitacora->observaciones = "Justificación usuario: " . $request->justificacion;
            $bitacora->save();
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false], 404);
    }

    /**
     * Consulta el estado de una revisión (Polling del Kiosco).
     */
    public function estadoRevision($bitacora_id)
    {
        $bitacora = Bitacora::find($bitacora_id);
        if (!$bitacora) return response()->json(['estado' => 'invalido']);

        return response()->json([
            'estado' => $bitacora->estado_evento,
            'autorizado' => $bitacora->estado_evento === 'autorizado' || $bitacora->estado_evento === 'revision_completada',
        ]);
    }

    /**
     * Panel del Guardia.
     */
    public function panelGuardia()
    {
        $user = Auth::user();
        if (!$user->isGuardia()) abort(403);

        $turnoActivo = TurnoGuardia::where('guardia_id', $user->guardia_id)
            ->whereNull('fecha_hora_fin')
            ->first();

        return view('guardia.dashboard', compact('turnoActivo'));
    }

    /**
     * API para que el guardia vea sus pendientes (Revisiones Y Anomalías).
     */
    public function pendientesGuardia()
    {
        $user = Auth::user();
        $plantaId = $user->guardia?->planta_id;
        
        // Buscamos eventos que sean revisión pendiente O anomalía de horario pendiente
        $pendientes = Bitacora::where(function($q) {
                $q->where('estado_evento', 'pendiente_revision')
                  ->orWhere('estado_anomalia', 'pendiente');
            })
            ->where('planta_id', $plantaId)
            ->whereDate('fecha_hora', today())
            ->get()
            ->map(function($b) {
                $emp = Empleado::find($b->persona_id);
                // Determinamos el tipo de alerta para mostrar el color y texto correcto en la vista
                $tipo = $b->estado_evento === 'pendiente_revision' ? 'revision' : 'anomalia';
                return [
                    'tipo' => $tipo,
                    'bitacora_id' => $b->id,
                    'nombre' => $emp?->nombre_completo ?? 'Desconocido',
                    'hora' => $b->fecha_hora->format('H:i'),
                    'justificacion' => $b->observaciones, // Traemos la justificación si la escribió
                ];
            });

        return response()->json($pendientes);
    }

    /**
     * El guardia resuelve un pendiente (Revisión o Anomalía).
     */
    public function resolverPendiente(Request $request)
    {
        $bitacora = Bitacora::find($request->bitacora_id);
        if (!$bitacora) return response()->json(['success' => false], 404);

        // Si era revisión aleatoria
        if ($bitacora->estado_evento === 'pendiente_revision') {
            $bitacora->estado_evento = 'revision_completada';
            $bitacora->revision_exhaustiva = true;
        }
        
        // Si era anomalía de horario
        if ($bitacora->estado_anomalia === 'pendiente') {
            $bitacora->estado_anomalia = 'revisada';
            // Guardamos la acción del guardia en observaciones
            $bitacora->observaciones = ($bitacora->observaciones ? $bitacora->observaciones . "\n" : "") . "Acción guardia: " . $request->descripcion;
        }
        
        $bitacora->save();

        if ($request->resultado === 'anomalia') {
            RevisionHallazgo::create([
                'bitacora_id' => $bitacora->id,
                'resultado' => 'anomalia',
                'descripcion_hallazgo' => $request->descripcion,
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Terminar turno de guardia.
     */
    public function terminarTurno(Request $request)
    {
        $user = Auth::user();
        $turno = TurnoGuardia::where('guardia_id', $user->guardia_id)
            ->whereNull('fecha_hora_fin')
            ->first();
        
        if ($turno) {
            $turno->fecha_hora_fin = now();
            $turno->save();
            LogSistema::registrar('FIN_TURNO_GUARDIA', "Turno finalizado");
        }
        
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Proxy de fotos de sitec.
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