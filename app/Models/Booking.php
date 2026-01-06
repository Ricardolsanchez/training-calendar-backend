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
        'attendedbutton',
        'group_code', // ✅ IMPORTANTÍSIMO si ya estás mandando group_code desde el FE
    ];

    protected $casts = [
        'attendedbutton' => 'boolean',
        // opcional:
        // 'start_date' => 'date',
        // 'end_date' => 'date',
        // 'original_start_date' => 'date',
        // 'original_end_date' => 'date',
    ];

    public function bookingSessions()
    {
        return $this->hasMany(\App\Models\BookingSession::class);
    }
}
