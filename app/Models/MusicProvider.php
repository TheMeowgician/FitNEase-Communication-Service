<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MusicProvider extends Model
{
    protected $primaryKey = 'provider_id';

    protected $fillable = [
        'provider_name',
        'display_name',
        'oauth_authorize_url',
        'oauth_token_url',
        'client_id',
        'client_secret',
        'supported_scopes',
        'is_active',
    ];

    protected $casts = [
        'supported_scopes' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'client_secret',
    ];

    public function integrations(): HasMany
    {
        return $this->hasMany(MusicIntegration::class, 'service_name', 'provider_name');
    }
}
