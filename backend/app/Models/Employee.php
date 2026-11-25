<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $casts = ['working_hours' => 'array'];

    protected $guarded = ['id'];

    public function salon()
    {
        return $this->belongsTo(Salon::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
