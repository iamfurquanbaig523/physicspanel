<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\CustomAutoTranslate::class,
        \App\Console\Commands\CustomTranslateMissing::class,
    ];
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('notify:expiring-items')->daily();
        $schedule->command('notify:expiring-packages')->daily();
        
        // Process queue jobs every minute
        // Using custom command that wraps queue:work for better reliability
        $schedule->command('queue:process --tries=3 --timeout=300 --max-jobs=50')
            ->everyMinute();
        
        // $schedule->command('inspire')->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');

    }



}
