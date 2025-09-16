<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $primaryKey = 'message_id';

    protected $fillable = [
        'chat_session_id',
        'sender_type',
        'message_content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
