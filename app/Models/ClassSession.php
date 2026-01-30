<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSession extends Model
{
    protected $table = 'class_sessions';

    // ✅ Pon esto SOLO si tu tabla realmente NO tiene timestamps
    public $timestamps = false;

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
        'group_code',
        'workday_url',
    ];

    // Opcional (recomendado):
    protected $casts = [
        'spots_left' => 'integer',
    ];

    public function bookings()
    {
        return $this->belongsToMany(\App\Models\Booking::class, 'booking_sessions', 'class_session_id', 'booking_id')
            ->withPivot(['attended'])
            ->withTimestamps();
    }
}
