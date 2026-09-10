<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
        protected function schedule(Schedule $schedule)
    {
        $schedule->command('app:reports-tasks medicionesMediMEMOneWeek')
                ->cron('0 1,4,7,10 * * *')
                ->withoutOverlapping(370);

        $schedule->command('app:reports-tasks medicionesMediMEM')
                ->cron('0 13,17,21 * * *')
                ->withoutOverlapping(370);
        
        $schedule->command('app:reports-tasks simsaInvoicesCleanUp')
                ->dailyAt('05:45')
                ->withoutOverlapping(370);

        $schedule->command('app:reports-tasks simsaInvoicesSync')
                ->dailyAt('06:00')
                ->withoutOverlapping(370);

        $schedule->command('app:reports-tasks accionaInvoicesCleanUp')
                ->dailyAt('06:45')
                ->withoutOverlapping(370);

        $schedule->command('app:reports-tasks accionaInvoicesSync')
                ->dailyAt('07:00')
                ->withoutOverlapping(370);

        $schedule->command('sync:measurements')->daily();
        $schedule->command('sync:general-data')->daily();
        $schedule->command('sync:catalog-data')->daily();
        $schedule->command('sync:cloud-catalog-data')->daily();
        $schedule->command('sync:liquidaciones-ecd')->daily();
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
