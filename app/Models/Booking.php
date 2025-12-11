<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'notes',
        'start_date',
        'end_date',
        'trainer_name',
        'original_start_date',
        'original_end_date',
        'original_training_days',
        'new_training_days',
        'status',
        'class_id',
        'calendar_url',
        'attendedbutton', // 👈 NUEVO
    ];

    // 👇 ESTO ES LO QUE TE FALTABA (va justo después de $fillable)
    protected $casts = [
        'attendedbutton' => 'boolean', // 👈 NUEVO
    ];
}
