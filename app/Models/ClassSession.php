<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSession extends Model
{
    // 👇 Nombre real de la tabla en Supabase
    protected $table = 'class_sessions';

    // 👇 Si tu tabla NO tiene created_at / updated_at
    public $timestamps = false;

    // 👇 Campos que se pueden escribir masivamente
    protected $fillable = [
        'title',
        'trainer_name',
        'date_iso',
        'end_date_iso',
        'time_range',
        'modality',
        'level',
        'spots_left',
        'description',
        'group_code'
    ];
}
