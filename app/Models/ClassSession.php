<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClassSession extends Model
{
    use HasFactory;

    protected $table = 'class_sessions';

    protected $fillable = [
        'title',
        'trainer_name',
        'date_iso',
        'time_range',
        'modality',
        'level',
        'spots_left',
        'calendar_url'
    ];
}