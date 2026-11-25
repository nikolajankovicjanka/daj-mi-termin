<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $guarded = ['id'];

    public function thread()
    {
        return $this->belongsTo(ChatThread::class, 'chat_thread_id');
    }
}
