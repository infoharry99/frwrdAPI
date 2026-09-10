<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Expiry reminder emails at 09:00 AM daily
        $schedule->command('cron:package-expiry')->dailyAt('09:00');

        // TutorCruncher Sync Jobs
        $schedule->command('sync:tutors')->dailyAt('00:00');
        $schedule->command('sync:students')->dailyAt('01:00');
        $schedule->command('sync:appointments')->dailyAt('02:00');
        $schedule->command('sync:invoices')->dailyAt('03:00');
        $schedule->command('sync:clients')->dailyAt('04:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
