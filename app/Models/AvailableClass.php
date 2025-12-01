<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvailableClass extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'title',
        'trainer_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'modality',
        'spots_left',
    ];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    // 👇 Esto es para que en JSON salga también "trainer_name"
    protected $appends = ['trainer_name'];

    public function getTrainerNameAttribute()
    {
        return $this->trainer ? $this->trainer->name : null;
    }
}