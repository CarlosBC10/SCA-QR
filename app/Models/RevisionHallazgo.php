<?php
// app/Models/RevisionHallazgo.php
//
// MODELO: RevisionHallazgo
// TABLA: revision_hallazgos
//
// PROPÓSITO:
// Cuando un empleado es sorteado para revisión aleatoria (F10), el guardia lo revisa.
// Si le encuentra algo (una anomalía), se debe registrar qué encontró.
// 
// Mantenemos esto en una tabla separada (1:1 con bitácora) para que la bitácora
// principal quede limpia (solo registra que *hubo* revisión) y los detalles
// del hallazgo vivan aquí. Esto evita llenar de texto la tabla de bitácora
// cuando la mayoría de las revisiones serán "todo_bien".

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionHallazgo extends Model
{
    protected $table = 'revision_hallazgos';

    // resultado: 'todo_bien' o 'anomalia'
    // descripcion_hallazgo: texto libre si se encontró algo
    protected $fillable = [
        'bitacora_id', 'resultado', 'descripcion_hallazgo',
    ];

    // Relación: Un hallazgo pertenece a un evento de bitácora específico
    public function bitacora()
    {
        return $this->belongsTo(Bitacora::class, 'bitacora_id');
    }
}

<?php
// app/Models/RevisionHallazgo.php
//
// Hallazgos de la revisión exhaustiva F10.
// Cuando el guardia revisa a un empleado (10% aleatorio), registra aquí
// el resultado: todo_bien | anomalia, con descripción del hallazgo.
//
// CAMBIO V4: 'bitacora_id' → 'id_bitacora' (FK a bitacora).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionHallazgo extends Model
{
    protected $table = 'revision_hallazgos';
    protected $primaryKey = 'id_hallazgo';

    // Solo created_at (los hallazgos no se modifican)
    public const UPDATED_AT = null;

    protected $fillable = ['id_bitacora', 'resultado', 'descripcion_hallazgo'];

    public function bitacora()
    {
        return $this->belongsTo(Bitacora::class, 'id_bitacora', 'id_bitacora');
    }
}