<?php
// app/Console/Commands/ExpirarSolicitudes.php
//
// COMANDO: php artisan sca:expirar-solicitudes
//
// Marca como 'expirado' las solicitudes (int y ext) cuya fecha_fin ya pasó
// y que están en estado pendiente* o aprobado*.
// También invalida los QRs de externos (excepto si están dentro de planta).
//
// Se ejecuta automáticamente cada 5 min vía el scheduler de Laravel.

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SolicitudInt;
use App\Models\SolicitudExt;
use App\Models\SolicitudEmpleado;
use App\Models\SolicitudExternoDetalle;
use App\Models\Bitacora;

class ExpirarSolicitudes extends Command
{
    protected $signature = 'sca:expirar-solicitudes';
    protected $description = 'Marca como expirado las solicitudes cuya fecha_fin ya pasó.';

    public function handle(): int
    {
        $ahora = now();
        $totalInt = 0;
        $totalExt = 0;
        $totalEmpleados = 0;
        $totalQrs = 0;

        // ============================================================
        // 1. EXPIRAR SOLICITUDES INTERNAS (pase_salida + interplantas)
        // ============================================================
        $solicitudesInt = SolicitudInt::whereIn('estado', [
                'pendiente_origen',
                'pendiente_destino',
                'aprobado',
                'aprobado_parcial',
            ])
            ->where('fecha_fin', '<', $ahora)
            ->get();

        foreach ($solicitudesInt as $sol) {
            $sol->estado = 'expirado';
            $sol->saveQuietly();
            $totalInt++;

            // Expirar también los solicitud_empleados
            $empleadosActualizados = SolicitudEmpleado::where('id_solicitud_int', $sol->id_solicitud_int)
                ->whereIn('estado', ['pendiente', 'aprobado', 'aprobado_origen'])
                ->update(['estado' => 'expirado']);
            $totalEmpleados += $empleadosActualizados;
        }

        // ============================================================
        // 2. EXPIRAR SOLICITUDES EXTERNAS (visita + contratista)
        // ============================================================
        $solicitudesExt = SolicitudExt::whereIn('estado', [
                'pendiente',
                'aprobado',
                'aprobado_parcial',
            ])
            ->where('fecha_fin', '<', $ahora)
            ->get();

        foreach ($solicitudesExt as $sol) {
            $sol->estado = 'expirado';
            $sol->saveQuietly();
            $totalExt++;

            // Invalidar QRs de los externos.
            // FIX V7 (Problema C): NO invalidar QRs de externos que estén dentro
            // de planta (podrán escanear para salir).
            foreach ($sol->externos as $detalle) {
                // ────────────────────────────────────────────────────────
                // V10.3 · CAMBIO #11 (bugfix): $personaId NO existía en este
                // ámbito (variable nunca definida en el foreach). PHP lanza
                // "Undefined variable $personaId" (Warning/ErrorException
                // según config) y el comando programado abortaba en cuanto
                // expiraba una solicitud externa — los QR nunca llegaban a
                // invalidarse persistente. Se construye el persona_id con el
                // mismo prefijo "E-" que usa la bitácora para externos.
                // ────────────────────────────────────────────────────────
                $personaId = 'E-' . $detalle->id_externos;
                $estaDentro = \App\Services\PresenciaService::estaDentro($personaId);

                // Solo invalidar QRs 'autorizado' (no los 'pendiente' o 'cancelado')
                // y solo si el externo NO está dentro de planta.
                if ($detalle->estado_qr === 'autorizado' && !$estaDentro) {
                    $detalle->estado_qr = 'expirado';
                    $detalle->codigo_qr = null;
                    $detalle->saveQuietly();
                    $totalQrs++;
                }
            }
        }

        // ============================================================
        // 3. RESUMEN (solo consola, no escribe a logs_sistema)
        // ============================================================
        if ($totalInt > 0 || $totalExt > 0) {
            $this->info("Solicitudes INT expiradas: {$totalInt}");
            $this->info("Solicitudes EXT expiradas: {$totalExt}");
            $this->info("Empleados expirados: {$totalEmpleados}");
            $this->info("QRs invalidados: {$totalQrs}");
        } else {
            $this->info('No hay solicitudes por expirar.');
        }

        return Command::SUCCESS;
    }
}