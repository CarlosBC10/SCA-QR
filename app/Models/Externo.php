<?php
// app/Models/Externo.php
//
// CAMBIO V5:
//   - Eliminadas columnas 'tipo' y 'empresa'.
//   - Solo guarda datos personales: curp, pasaporte, nombre_completo, telefono, foto.
//   - La empresa ahora vive en solicitudes_ext.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Externo extends Model
{
    protected $table = 'externos';
    protected $primaryKey = 'id_externos';

    protected $fillable = [
        'curp', 'pasaporte', 'nombre_completo', 'telefono', 'foto',
    ];

    public function detallesSolicitud()
    {
        return $this->hasMany(SolicitudExternoDetalle::class, 'id_externos', 'id_externos');
    }

    /**
     * Busca un externo por CURP o Pasaporte; si no existe lo crea.
     *
     * FIX G-2: Normaliza el nombre a Title Case.
     * FIX: Si no hay identificación (ni CURP ni pasaporte), crea nuevo en limpio.
     */
    public static function buscarOcrear(array $datos): self
    {
        // Normalizar nombre a Title Case
        $nombreNormalizado = isset($datos['nombre_completo'])
            ? self::titleCaseNombre($datos['nombre_completo'])
            : null;
        
        $curpNormalizada = isset($datos['curp']) && $datos['curp'] !== ''
            ? strtoupper(trim($datos['curp']))
            : null;
        
        $pasaporteNormalizado = isset($datos['pasaporte']) && $datos['pasaporte'] !== ''
            ? strtoupper(trim($datos['pasaporte']))
            : null;

        // 1. Si hay CURP, dedup por CURP
        if ($curpNormalizada !== null) {
            return self::firstOrCreate(
                ['curp' => $curpNormalizada],
                [
                    'nombre_completo' => $nombreNormalizado,
                    'pasaporte'       => $pasaporteNormalizado,
                    'telefono'        => $datos['telefono'] ?? null,
                ]
            );
        }

        // 2. Si hay pasaporte, dedup por pasaporte
        if ($pasaporteNormalizado !== null) {
            return self::firstOrCreate(
                ['pasaporte' => $pasaporteNormalizado],
                [
                    'nombre_completo' => $nombreNormalizado,
                    'curp'            => null,
                    'telefono'        => $datos['telefono'] ?? null,
                ]
            );
        }

        // 3. Sin identificación: crear nuevo en limpio
        return self::create([
            'curp'            => null,
            'pasaporte'       => null,
            'nombre_completo' => $nombreNormalizado,
            'telefono'        => $datos['telefono'] ?? null,
        ]);
    }

    /**
     * Convierte un nombre a Title Case, preservando acentos.
     */
    public static function titleCaseNombre(?string $nombre): ?string
    {
        if (!$nombre) return null;
        $nombre = trim($nombre);
        $normalizado = mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8');
        $normalizado = preg_replace('/\s+/', ' ', $normalizado);
        return $normalizado ?: null;
    }
}