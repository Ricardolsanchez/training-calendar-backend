<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable =  [
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
        'status'
    ];
}