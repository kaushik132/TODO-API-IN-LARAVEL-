<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $table = 'departments';

    protected $fillable = [
        'name',        // Admin, BDE, IT, etc.
        'description', // Optional description
        'is_active',   // Active/Inactive
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Department ke saare users
    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }
}
