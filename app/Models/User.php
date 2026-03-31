<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'fcm_token',  // ← FCM Push Notification token
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'fcm_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
