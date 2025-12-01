<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trainer extends Model
{
    protected $fillable = ['name'];

    public $timestamps = false; // no necesitamos created_at / updated_at

    public function classes()
    {
        return $this->hasMany(AvailableClass::class);
    }
}