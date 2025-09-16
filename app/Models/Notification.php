<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $primaryKey = 'notification_id';

    protected $fillable = [
        'user_id',
        'notification_type',
        'title',
        'message',
        'action_data',
        'is_read',
        'is_sent',
        'email_sent',
        'scheduled_time',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'action_data' => 'array',
        'is_read' => 'boolean',
        'is_sent' => 'boolean',
        'email_sent' => 'boolean',
        'scheduled_time' => 'datetime',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];
}
