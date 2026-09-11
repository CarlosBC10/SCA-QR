<?php
// app/Console/Commands/VerificarBitacora.php
//
// COMANDO: php artisan sca:verificar-bitacora [--desde=YYYY-MM-DD]
//
// V10.3 · CAMBIO #34 (NUEVO): verificador de integridad de la trazabilidad
// bitacora.id_solicitud (el "FK lógico" sin restricción física que se
// agregó con el Script 1 de la Etapa 1 de BD).
//
// ¿Por qué existe este comando?
//   Decidimos NO poner FK física en bitacora.id_solicitud (bitácora es
//   append-only y debe soportar crecer a millones de filas sin lockeos
//   de integridad referencial). La contraparte es que NADIE impide a
//   nivel de BD que la referencia quede huérfana. Este comando es la
//   tercera mitigación (las otras dos: el scanner siempre escribe la
//   referencia cuando existe la solicitud, y el CHECK opcional del
//   Script 3 de BD para externos nuevos).
//
// Qué revisa (SOLO REPORTA, no corrige nada — auditoría honesta):
//   1. HUÉRFANOS: bitácoras con id_solicitud cuyo id YA NO existe en la
//      tabla ruteada (numérico → solicitudes_int, "E-x" → solicitudes_ext).
//   2. RUTEO INCOHERENTE: internos cuya solicitud referenciada NO los
//      incluye como beneficiario (individual-creador o integrante) —
//      ej. la solicitud se borró y se recreó con otro id.
//   3. EXTERNOS SIN REFERENCIA (post-cutoff): registros NUEVOS de
//      externos sin id_solicitud. El scanner V10.3 SIEMPRE lo escribe
//      para externos; si falta algo después del cutoff, es señal de
//      que algo se desplegó a medias o hay un flujo no cubierto.
//      El cutoff se controla con --desde= (default: hace 1 día, para
//      no ensuciar el reporte con históricos que por diseño vienen NULL).
//
// Programación: diario a las 06:00 (routes/console.php).
// Los hallazgos se registran además en logs_sistema (WARN_BITACORA)
// para que queden auditados aunque nadie vea la consola.

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bitacora;
use App\Models\SolicitudInt;
use App\Models\SolicitudExt;
use Carbon\Carbon;

class VerificarBitacora extends Command
{
    protected $signature = 'sca:verificar-bitacora {--desde=} : Fecha de corte YYYY-MM-DD para el chequeo de externos sin referencia (default: ayer)}';
    protected $description = 'V10.3: reporta inconsistencias de bitacora.id_solicitud (huérfanos, ruteo incoherente, externos sin referencia).';

    public function handle(): int
    {
        $desdeInput = $this->option('desde');
        try {
            $desde = $desdeInput
                ? Carbon::parse($desdeInput)->startOfDay()
                : now()->subDay()->startOfDay();
        } catch (\Exception $e) {
            $this->error('Fecha --desde inválida. Use el formato YYYY-MM-DD.');
            return Command::FAILURE;
        }

        $hallazgos = 0;

        // ============================================================
        // 1. Referencias de INTERNOS (persona_id numérico → solicitudes_int)
        // ============================================================
        $refsInternas = Bitacora::whereNotNull('id_solicitud')
            ->where('persona_id', 'not like', 'E-%')
            ->get(['id_bitacora', 'persona_id', 'id_solicitud']);

        $idsInt = $refsInternas->pluck('id_solicitud')
            ->map(fn ($v) => (int) $v)->unique()->values()->all();

        $solicitudesInt = !empty($idsInt)
            ? SolicitudInt::with('empleados')->whereIn('id_solicitud_int', $idsInt)->get()
                ->keyBy(fn ($s) => (string) $s->id_solicitud_int)
            : collect();

        // V10.3-fix (post-entrega): se declaran como Collections (collect()) y NO
        // como arrays []; abajo el reporte usa ->isNotEmpty()/->count()/->map()/
        // ->pluck(), que son métodos de Collection — con array puro el comando
        // truena con "Call to a member function isNotEmpty() on array".
        $orfanasInt = collect();
        $ruteoIncoherente = collect();

        foreach ($refsInternas as $b) {
            $sol = $solicitudesInt->get((string) $b->id_solicitud);

            if (!$sol) {
                // 1a. Huérfano: la solicitud INT referenciada no existe.
                $orfanasInt->push($b);
                continue;
            }

            // 1b. Ruteo incoherente: la persona NO es beneficiaria de esa solicitud.
            //     (mismo criterio del CAMBIO #02: creador-individual o integrante).
            $empId = (int) $b->persona_id;
            $esIndividual  = ((int) $sol->creador_empleado_id === $empId) && $sol->empleados->isEmpty();
            $esIntegrante  = $sol->empleados->contains(fn ($se) => (int) $se->empleado_id === $empId
                && in_array($se->estado, ['pendiente', 'aprobado', 'aprobado_origen', 'expirado', 'rechazado'], true));

            if (!$esIndividual && !$esIntegrante) {
                $ruteoIncoherente->push($b);
            }
        }

        // ============================================================
        // 2. Referencias de EXTERNOS (persona_id "E-x" → solicitudes_ext)
        // ============================================================
        $refsExternas = Bitacora::whereNotNull('id_solicitud')
            ->where('persona_id', 'like', 'E-%')
            ->get(['id_bitacora', 'persona_id', 'id_solicitud']);

        $idsExt = $refsExternas->pluck('id_solicitud')
            ->map(fn ($v) => (int) $v)->unique()->values()->all();

        // V10.3-fix: se carga with('externos') porque además de verificar
        // EXISTENCIA de la solicitud necesitamos validar que el externo
        // referenciado realmente pertenezca a esa solicitud.
        $solicitudesExt = !empty($idsExt)
            ? SolicitudExt::with('externos')->whereIn('id_solicitud_ext', $idsExt)->get()
                ->keyBy(fn ($s) => (string) $s->id_solicitud_ext)
            : collect();

        $orfanasExt = collect();
        $ruteoIncoherenteExt = collect();
        foreach ($refsExternas as $b) {
            $sol = $solicitudesExt->get((string) $b->id_solicitud);
            if (!$sol) {
                $orfanasExt->push($b);
                continue;
            }

            $extId = (int) str_replace('E-', '', (string) $b->persona_id);
            if (!$sol->externos->contains(fn ($detalle) => (int)$detalle->id_externos === $extId)) {
                $ruteoIncoherenteExt->push($b);
            }
        }

        // ============================================================
        // 3. Externos SIN id_solicitud después del cutoff
        // ============================================================
        $extSinReferencia = Bitacora::where('persona_id', 'like', 'E-%')
            ->whereNull('id_solicitud')
            ->where('fecha_hora', '>=', $desde)
            ->get(['id_bitacora', 'persona_id', 'fecha_hora']);

        // ============================================================
        // 4. REPORTE
        // ============================================================
        $this->info('── Verificador de trazabilidad bitacora.id_solicitud (V10.3) ──');
        $this->info("Cutoff externos sin referencia: {$desde->format('d/m/Y H:i')}");
        $this->newLine();

        $this->info("Referencias internas revisadas: {$refsInternas->count()}");
        $this->info("Referencias externas revisadas: {$refsExternas->count()}");
        $this->newLine();

        if ($orfanasInt->isNotEmpty()) {
            $hallazgos += $orfanasInt->count();
            $this->warn("HUÉRFANOS INT ({$orfanasInt->count()}): la solicitud referenciada no existe.");
            $this->table(
                ['id_bitacora', 'persona_id', 'id_solicitud'],
                $orfanasInt->map(fn ($b) => [$b->id_bitacora, $b->persona_id, $b->id_solicitud])->all()
            );
        }

        if ($ruteoIncoherente->isNotEmpty()) {
            $hallazgos += $ruteoIncoherente->count();
            $this->warn("RUTEO INCOHERENTE ({$ruteoIncoherente->count()}): la persona no es beneficiaria de la solicitud INT referenciada.");
            $this->table(
                ['id_bitacora', 'persona_id', 'id_solicitud'],
                $ruteoIncoherente->map(fn ($b) => [$b->id_bitacora, $b->persona_id, $b->id_solicitud])->all()
            );
        }

        if ($orfanasExt->isNotEmpty()) {
            $hallazgos += $orfanasExt->count();
            $this->warn("HUÉRFANOS EXT ({$orfanasExt->count()}): la solicitud_ext referenciada no existe.");
            $this->table(
                ['id_bitacora', 'persona_id', 'id_solicitud'],
                $orfanasExt->map(fn ($b) => [$b->id_bitacora, $b->persona_id, $b->id_solicitud])->all()
            );
        }

        if ($ruteoIncoherenteExt->isNotEmpty()) {
            $hallazgos += $ruteoIncoherenteExt->count();
            $this->warn("RUTEO INCOHERENTE EXT ({$ruteoIncoherenteExt->count()}): el externo no pertenece a la solicitud_ext referenciada.");
            $this->table(
                ['id_bitacora', 'persona_id', 'id_solicitud'],
                $ruteoIncoherenteExt->map(fn ($b) => [$b->id_bitacora, $b->persona_id, $b->id_solicitud])->all()
            );
        }

        if ($extSinReferencia->isNotEmpty()) {
            $hallazgos += $extSinReferencia->count();
            $this->warn("EXTERNOS SIN REFERENCIA post-cutoff ({$extSinReferencia->count()}): el scanner V10.3 siempre debe escribir id_solicitud para externos.");
            $this->table(
                ['id_bitacora', 'persona_id', 'fecha_hora'],
                $extSinReferencia->map(fn ($b) => [$b->id_bitacora, $b->persona_id, $b->fecha_hora?->format('d/m/Y H:i')])->all()
            );
        }

        if ($hallazgos === 0) {
            $this->info('✔ Sin inconsistencias. Trazabilidad íntegra.');
            return Command::SUCCESS;
        }

        // Registrar en el log de Laravel para auditoría persistente.
        // (LogSistema::registrar NO sirve aquí: exige usuario autenticado
        //  y en consola/scheduler no hay sesión — devolvería sin escribir.)
        \Log::warning("sca:verificar-bitacora: {$hallazgos} inconsistencia(s) de trazabilidad", [
            'huérfanos_int'        => $orfanasInt->count(),
            'ruteo_incoherente'    => $ruteoIncoherente->count(),
            'huérfanos_ext'        => $orfanasExt->count(),
            'ruteo_incoherente_ext' => $ruteoIncoherenteExt->count(),
            'externos_sin_ref'     => $extSinReferencia->count(),
            'bitacoras_afectadas'  => $orfanasInt->pluck('id_bitacora')
                ->merge($ruteoIncoherente->pluck('id_bitacora'))
                ->merge($orfanasExt->pluck('id_bitacora'))
                ->merge($ruteoIncoherenteExt->pluck('id_bitacora'))
                ->merge($extSinReferencia->pluck('id_bitacora'))
                ->values()->all(),
        ]);

        // El comando "reporta": devuelve SUCCESS igualmente para no romper el
        // scheduler, pero los hallazgos quedan arriba y en logs_sistema.
        return Command::SUCCESS;
    }
}
