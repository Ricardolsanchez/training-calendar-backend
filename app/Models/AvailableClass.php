<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvailableClass extends Model
{
    // 👇 IMPORTANTÍSIMO: usar la tabla que SÍ existe
    protected $table = 'class_sessions';

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'title',
        'trainer_id',    // solo si esta columna existe en class_sessions
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'modality',
        'spots_left',
        // 'calendar_url', // descomenta SOLO si la columna existe en la tabla
    ];

    public $timestamps = true;
}
