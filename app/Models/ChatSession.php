<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    protected $primaryKey = 'chat_session_id';

    protected $fillable = [
        'user_id',
        'session_type',
        'session_title',
        'context_data',
        'is_active',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'context_data' => 'array',
        'is_active' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_session_id');
    }
}
