<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('nirab:dispatch-process')->everyMinute()->withoutOverlapping();
        $schedule->command('quotations:expire')->hourly()->withoutOverlapping();
        $schedule->command('nirab:communications-drain')->everyMinute()->withoutOverlapping();
        $schedule->command('nirab:quality-reminders')->hourly()->withoutOverlapping();
        $schedule->command('customer-subscription:expiry-check')->hourly()->withoutOverlapping()->onOneServer();
        $schedule->command('nirab:personal-data-prune')->dailyAt('03:00')->timezone('Asia/Riyadh')->withoutOverlapping();
        if (config('nirab_quality.backup.enabled')) {
            $schedule->command('database:backup')->dailyAt(config('nirab_quality.backup.time'))
                ->timezone(config('nirab_quality.backup.timezone'))->withoutOverlapping()->onOneServer();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
