<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicIntegration extends Model
{
    protected $primaryKey = 'integration_id';

    protected $fillable = [
        'user_id',
        'service_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'user_profile_data',
        'is_active',
        'last_sync_at',
    ];

    protected $casts = [
        'user_profile_data' => 'array',
        'is_active' => 'boolean',
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(MusicProvider::class, 'service_name', 'provider_name');
    }
}
