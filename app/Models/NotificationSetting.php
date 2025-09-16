<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $primaryKey = 'setting_id';

    protected $fillable = [
        'user_id',
        'notification_type',
        'enabled',
        'email_enabled',
        'push_enabled',
        'preferences',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'preferences' => 'array',
    ];
}
