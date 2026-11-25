<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Salon extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['expires_at' => 'date', 'free_until' => 'date', 'latitude' => 'float', 'longitude' => 'float'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function owner()
    {
        return $this->hasOne(User::class)->where('role', 'owner');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function chatThreads()
    {
        return $this->hasMany(ChatThread::class);
    }

    public function scopeListed(Builder $query): Builder
    {
        $today = now(config('booking.timezone'))->toDateString();

        return $query->where(function ($q) use ($today) {
            $q->whereDate('expires_at', '>=', $today)->orWhereDate('free_until', '>=', $today)
                ->orWhere(function ($noEnd) {
                    $noEnd->whereNull('expires_at')->whereNull('free_until');
                });
        });
    }
}
