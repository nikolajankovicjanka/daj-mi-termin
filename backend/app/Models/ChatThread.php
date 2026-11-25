<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatThread extends Model
{
    protected $guarded = ['id'];

    public function salon()
    {
        return $this->belongsTo(Salon::class);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }
}
