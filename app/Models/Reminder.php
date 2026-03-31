<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use HasFactory;

    protected $table = 'reminders';

    protected $fillable = [
        'user_id',
        'title',
        'amount',
        'reminder_date',
        'reminder_before', // 0=same day, 1=1 din pehle, 2=2 din, 3=3 din
        'note',
        'status',          // pending | complete
        'is_notified',     // notification bheji ya nahi
    ];

    protected $casts = [
        'amount'        => 'float',
        'reminder_date' => 'date:Y-m-d',
        'is_notified'   => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
