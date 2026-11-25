<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $guarded = ['id'];

    public function salon()
    {
        return $this->belongsTo(Salon::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
