<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    // =============================================
    // Scheduled Tasks
    // =============================================
    protected function schedule(Schedule $schedule): void
    {
        // ✅ Har roz subah 9:00 AM pe installment reminders bhejo
        $schedule->command('reminders:send-installment')
                 ->dailyAt('09:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/reminders.log'));


                 // Reminder notifications - har roz 9 AM
        $schedule->command('reminders:send-notifications')
                 ->dailyAt('09:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/reminder-notifications.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
