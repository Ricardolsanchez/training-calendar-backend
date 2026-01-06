<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingSession extends Model
{
    protected $fillable = ['booking_id', 'class_session_id', 'attended'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
    public function session()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }
    public function bookingSessions()
    {
        return $this->hasMany(\App\Models\BookingSession::class, 'class_session_id');
    }
}
