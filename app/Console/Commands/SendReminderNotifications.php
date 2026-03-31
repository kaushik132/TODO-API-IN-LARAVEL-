<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\FCMService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendReminderNotifications extends Command
{
    protected $signature   = 'reminders:send-notifications';
    protected $description = 'Reminder date se pehle notifications bhejo (0-3 din)';

    public function handle(FCMService $fcm): void
    {
        $today = Carbon::today();
        $this->info("Reminder notifications check: {$today->toDateString()}");

        $sent  = 0;
        $skipped = 0;

        // Saare pending reminders lo
        $reminders = Reminder::with('user')
            ->where('status', 'pending')
            ->where('is_notified', false)
            ->get();

        foreach ($reminders as $reminder) {
            $user = $reminder->user;

            if (!$user || !$user->fcm_token) {
                $skipped++;
                continue;
            }

            $reminderDate  = Carbon::parse($reminder->reminder_date);
            $notifyOnDate  = $reminderDate->copy()->subDays($reminder->reminder_before);

            // Aaj notification bhejni chahiye?
            if ($today->equalTo($notifyOnDate)) {
                $daysLeft = $today->diffInDays($reminderDate);
                $amount   = number_format($reminder->amount, 0);
                $dateStr  = $reminderDate->format('d M Y');

                // Title aur body set karo
                if ($reminder->reminder_before == 0) {
                    $title = "⏰ Aaj Reminder Hai!";
                    $body  = "{$reminder->title} - ₹{$amount} aaj ({$dateStr})";
                } elseif ($reminder->reminder_before == 1) {
                    $title = "🔔 Kal Reminder Hai!";
                    $body  = "{$reminder->title} - ₹{$amount} kal ({$dateStr})";
                } else {
                    $title = "🔔 {$daysLeft} Din Mein Reminder!";
                    $body  = "{$reminder->title} - ₹{$amount} ({$dateStr})";
                }

                $data = [
                    'reminder_id'   => $reminder->id,
                    'title'         => $reminder->title,
                    'amount'        => $reminder->amount,
                    'reminder_date' => $reminder->reminder_date,
                    'screen'        => 'reminder_detail',
                ];

                $result = $fcm->sendNotification($user->fcm_token, $title, $body, $data);

                if ($result) {
                    // Mark as notified
                    $reminder->update(['is_notified' => true]);
                    $this->info("✅ Notification bheji: [{$reminder->title}] → User #{$user->id}");
                    $sent++;
                } else {
                    $this->warn("❌ Notification fail: [{$reminder->title}] → User #{$user->id}");
                }
            }
        }

        $this->info("Total {$sent} notifications bheji. {$skipped} skip (no FCM token).");
        Log::info("Reminder notifications: {$sent} sent on {$today->toDateString()}");
    }
}
