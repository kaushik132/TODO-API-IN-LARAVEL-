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
        'fcm_token',
        'department_id', // ← Department assign
        'role',          // ← super_admin | admin | member
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

    // =============================================
    // Relationships
    // =============================================
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // =============================================
    // Role Check Helpers
    // =============================================
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isMember(): bool
    {
        return $this->role === 'member';
    }

    // Same department ke users
    public function departmentMembers()
    {
        if (!$this->department_id) return collect();

        return User::where('department_id', $this->department_id)
                   ->where('id', '!=', $this->id)
                   ->get();
    }
}
