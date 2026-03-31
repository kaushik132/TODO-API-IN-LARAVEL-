<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    use HasFactory;

    protected $table = 'otp_codes';

    protected $fillable = [
        'email',
        'otp',
        'expires_at',
        'is_verified',
        'reset_token',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'is_verified' => 'boolean',
    ];
}
