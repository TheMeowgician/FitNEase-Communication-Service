<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $primaryKey = 'device_token_id';

    protected $fillable = [
        'user_id',
        'expo_push_token',
        'platform',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Scope to get only active tokens
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get tokens for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get all active tokens for a user
     */
    public static function getActiveTokensForUser(int $userId): array
    {
        return self::active()
            ->forUser($userId)
            ->pluck('expo_push_token')
            ->toArray();
    }

    /**
     * Update the last_used_at timestamp
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Deactivate this token
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
