<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    // Connect to auth database for shared authentication
    protected $connection = 'auth_db';
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'age',
        'gender',
        'fitness_level',
        'activity_level',
        'is_active',
        'email_verified_at',
        'last_login'
    ];

    protected $hidden = [
        'password_hash',
        'email_verification_token',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login' => 'datetime',
            'is_active' => 'boolean',
            'age' => 'integer',
            'target_muscle_groups' => 'array',
            'fitness_goals' => 'array',
        ];
    }

    // Override the password column name since auth service uses password_hash
    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}
