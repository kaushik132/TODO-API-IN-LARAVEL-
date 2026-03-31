<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'address',           // ← Contact address
        'avatar',            // ← Contact profile image path
        'person_type',
        'transaction_type',
        'total_amount',
        'pending_amount',
        'payment_type',
        'is_recurring',
        'installment_amount',
        'installment_date',
        'date',
        'note',
    ];

    protected $casts = [
        'is_recurring'       => 'boolean',
        'total_amount'       => 'float',
        'pending_amount'     => 'float',
        'installment_amount' => 'float',
        'installment_date'   => 'date:Y-m-d',
        'date'               => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
