<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvailableClass extends Model
{
    // Esta tabla SÍ existe
    protected $table = 'class_sessions';

    // Solo columnas reales de class_sessions
    protected $fillable = [
        'title',
        'trainer_name',   // 👈 guardamos el nombre, no el id
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'modality',
        'spots_left',
        'calendar_url',   // si tienes esta columna
    ];

    public $timestamps = true;
}
