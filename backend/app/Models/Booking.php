<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $casts = ['start_at' => 'datetime', 'end_at' => 'datetime'];

    protected $hidden = ['review_token'];

    protected $guarded = ['id'];

    public function salon()
    {
        return $this->belongsTo(Salon::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
