<?php
// app/Models/Externo.php
//
// MODELO: Externo
// TABLA:  externos  (BD sca)
//
// Representa a una persona externa a Fruitex (visitante, contratista, etc.)
// que va a ingresar a una planta y necesita una solicitud + QR.
//
// ⚠️ CAMBIO V7 (unificación de identificación oficial):
//   Antes tenía dos columnas separadas: 'curp' (UNIQUE) y 'pasaporte'.
//   Ahora tiene un campo unificado:
//     - tipo_identificacion: enum('curp','credencial_votar','cedula_profesional','pasaporte')
//     - identificacion:      varchar(50) con el valor real del documento
//   UNIQUE compuesta en (tipo_identificacion, identificacion).
//   Esto simplifica el formulario (un solo campo + select de tipo) y
//   permite aceptar 4 tipos de identificación oficial.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Externo extends Model
{
    protected $table      = 'externos';
    protected $primaryKey = 'id_externos';

    // Campos asignables masivamente.
    // Ya NO hay 'curp' ni 'pasaporte' como columnas separadas.
    protected $fillable = [
        'tipo_identificacion',  // 'curp' | 'credencial_votar' | 'cedula_profesional' | 'pasaporte'
        'identificacion',       // valor del documento
        'nombre_completo',
        'telefono',
        'foto',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ============================================================
    // RELACIONES
    // ============================================================

    public function solicitudesDetalles()
    {
        return $this->hasMany(SolicitudExternoDetalle::class, 'id_externos', 'id_externos');
    }

    // Accessor: nombre completo en Title Case
    public function getNombreCompletoFormateadoAttribute(): string
    {
        return Str::title(mb_strtolower($this->nombre_completo));
    }

    // ============================================================
    // HELPERS ESTÁTICOS
    // ============================================================

    /**
     * Etiquetas legibles para los tipos de identificación.
     * Se usa en vistas (formularios y show) para mostrar al usuario.
     */
    public static function tiposIdentificacion(): array
    {
        return [
            'curp'                => 'CURP',
            'credencial_votar'    => 'Credencial para votar (INE/IFE)',
            'cedula_profesional'  => 'Cédula profesional',
            'pasaporte'           => 'Pasaporte (extranjero)',
        ];
    }

    /**
     * Devuelve la etiqueta legible del tipo de identificación del externo.
     */
    public function getTipoIdentificacionLabelAttribute(): string
    {
        return self::tiposIdentificacion()[$this->tipo_identificacion]
            ?? ucfirst(str_replace('_', ' ', $this->tipo_identificacion));
    }

    /**
     * Busca un externo por tipo + valor de identificación, o lo crea si no existe.
     *
     * @param array $data  Debe traer: tipo_identificacion, identificacion,
     *                     nombre_completo, telefono (opcional).
     * @return self
     */
    public static function buscarOcrear(array $data): self
    {
        $tipo  = strtolower(trim($data['tipo_identificacion'] ?? ''));
        $valor = trim($data['identificacion'] ?? '');

        if (!$tipo || !$valor) {
            throw new \InvalidArgumentException(
                'buscarOcrear requiere tipo_identificacion e identificacion.'
            );
        }

        // Si es CURP, normalizar a MAYÚSCULAS (las CURPs siempre van en mayúsculas)
        if ($tipo === 'curp') {
            $valor = strtoupper($valor);
        }

        return self::firstOrCreate(
            [
                'tipo_identificacion' => $tipo,
                'identificacion'      => $valor,
            ],
            [
                'nombre_completo' => $data['nombre_completo'] ?? '',
                'telefono'         => $data['telefono'] ?? null,
            ]
        );
    }
}