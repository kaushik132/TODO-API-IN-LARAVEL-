<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\User;
use App\Services\FCMService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendInstallmentReminders extends Command
{
    protected $signature   = 'reminders:send-installment';
    protected $description = 'Installment date ke 1 din pehle aur same din reminder bhejo';

    public function handle(FCMService $fcm): void
    {
        $today    = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $this->info("Reminders check: {$today->toDateString()}");

        // ✅ Case 1: Same din (installment_date = aaj)
        $todayTransactions = Transaction::with('user')
            ->where('is_recurring', true)
            ->whereDate('installment_date', $today)
            ->where('pending_amount', '>', 0)
            ->get();

        foreach ($todayTransactions as $transaction) {
            $this->sendReminder($fcm, $transaction, 'today');
        }

        // ✅ Case 2: 1 din pehle (installment_date = kal)
        $tomorrowTransactions = Transaction::with('user')
            ->where('is_recurring', true)
            ->whereDate('installment_date', $tomorrow)
            ->where('pending_amount', '>', 0)
            ->get();

        foreach ($tomorrowTransactions as $transaction) {
            $this->sendReminder($fcm, $transaction, 'tomorrow');
        }

        $total = $todayTransactions->count() + $tomorrowTransactions->count();
        $this->info("Total {$total} reminders bheje.");
        Log::info("Installment reminders: {$total} sent on {$today->toDateString()}");
    }

    private function sendReminder(FCMService $fcm, Transaction $transaction, string $when): void
    {
        $user = $transaction->user;

        // User ke paas FCM token hona chahiye
        if (!$user || !$user->fcm_token) {
            return;
        }

        $amount      = number_format($transaction->installment_amount, 0);
        $date        = Carbon::parse($transaction->installment_date)->format('d M Y');
        $paymentDir  = $transaction->payment_type === 'to_pay' ? 'dena hai' : 'lena hai';
        $type        = ucfirst($transaction->type);

        if ($when === 'today') {
            $title = "⏰ Aaj Installment Date Hai!";
            $body  = "{$type}: ₹{$amount} aaj {$paymentDir} ({$date})";
        } else {
            $title = "🔔 Kal Installment Date Hai!";
            $body  = "{$type}: ₹{$amount} kal {$paymentDir} ({$date})";
        }

        $data = [
            'transaction_id'     => $transaction->id,
            'type'               => $transaction->type,
            'payment_type'       => $transaction->payment_type,
            'installment_amount' => $transaction->installment_amount,
            'installment_date'   => $transaction->installment_date,
            'screen'             => 'transaction_detail', // Flutter screen navigate karne ke liye
        ];

        $sent = $fcm->sendNotification($user->fcm_token, $title, $body, $data);

        if ($sent) {
            $this->info("✅ Reminder bheja: User #{$user->id} - {$title}");
        } else {
            $this->warn("❌ Reminder fail: User #{$user->id}");
        }
    }
}
