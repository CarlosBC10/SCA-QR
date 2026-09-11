<?php
// app/Models/RevisionHallazgo.php
//
// MODELO: RevisionHallazgo
// TABLA:  revision_hallazgos  (1:1 con bitacora)
//
// PROPÓSITO:
//   Cuando un empleado es sorteado para revisión aleatoria (F10), el guardia
//   lo revisa físicamente. Si encuentra algo (una anomalía), registra aquí:
//     - resultado:            'todo_bien' | 'anomalia'
//     - descripcion_hallazgo: texto libre describiendo qué encontró
//
//   Mantenemos esto en una tabla separada (1:1 con bitácora) para que la
//   bitácora principal quede limpia (solo registra que *hubo* revisión) y
//   los detalles del hallazgo vivan aquí. La mayoría de las revisiones serán
//   'todo_bien', así que no tendrán fila en esta tabla.
//
// RELACIÓN CON EL SCHEMA (dbV4.sql):
//   CREATE TABLE revision_hallazgos (
//     id_hallazgo            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
//     id_bitacora            BIGINT UNSIGNED NOT NULL,   -- FK a bitacora.id_bitacora
//     resultado              ENUM('todo_bien','anomalia') NOT NULL,
//     descripcion_hallazgo   TEXT NULL,
//     created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP
//   );
//
// ⚠️ FIX CRÍTICO C1 (auditoría V6):
//   Antes este archivo tenía DOS bloques <?php y DOS declaraciones de clase
//   (la versión vieja con 'bitacora_id' y la nueva V4 con 'id_bitacora').
//   PHP no puede cargar un archivo con dos <?php — fatal error al autoload.
//   Esto rompía ScannerController::resolverPendiente() cuando un guardia
//   marcaba 'anomalia' en una revisión F10: la bitácora ya había quedado
//   en estado 'autorizado', pero RevisionHallazgo::create() crasheaba y el
//   hallazgo NUNCA se registraba (pérdida de auditoría).
//   Se eliminó el bloque viejo y se conserva únicamente la versión V4.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionHallazgo extends Model
{
    protected $table      = 'revision_hallazgos';
    protected $primaryKey = 'id_hallazgo';

    // Los hallazgos son inmutables: solo se registran, no se modifican.
    // Por eso desactivamos updated_at (solo created_at).
    public const UPDATED_AT = null;

    // Campos asignables masivamente.
    // Notar que usamos 'id_bitacora' (no 'bitacora_id') para coincidir
    // con el schema V4 y con ScannerController::resolverPendiente().
    protected $fillable = [
        'id_bitacora',
        'resultado',            // 'revision_completada' | 'hallazgo'
        'descripcion_hallazgo', // texto libre, solo si resultado='hallazgo'
    ];

    // Relación 1:1 inversa con Bitacora.
    // Un hallazgo pertenece a exactamente un evento de bitácora.
    public function bitacora()
    {
        return $this->belongsTo(Bitacora::class, 'id_bitacora', 'id_bitacora');
    }
}