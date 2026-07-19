<?php
// app/Models/BitacoraActivo.php
//
// Representa la tabla bitacora_activos.
// Registra los objetos de valor, equipos o herramientas que visitantes
// y contratistas introducen o sacan de la planta.
//
// IMPORTANTE: esta tabla es SOLO para personas externas (visitantes
// y contratistas). Los empleados NO se registran aquí (su control de
// equipos vendrá en el futuro con la integración de la BD de equipos).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitacoraActivo extends Model
{
    protected $table = 'bitacora_activos';

    protected $fillable = ['bitacora_id', 'descripcion', 'cantidad', 'tipo_movimiento', 'observaciones'];

    public function bitacora() { return $this->belongsTo(Bitacora::class, 'bitacora_id'); }
}