<?php

namespace App\Console\Commands;

use DateTime;
use App\Http\Repositories\MeasurementsRepo;
use App\Http\Repositories\MediMEMRepo;
use App\Http\Services\MediMEMService;
use Illuminate\Console\Command;

class SmartReportsTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reports-tasks {task}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $task = $this->argument('task');
        try {
            switch ($task) {
                case 'medicionesMediMEMOneWeek':
                    $mediMEMRepo = new MediMEMRepo(
                        app(MediMEMService::class),
                        app(MeasurementsRepo::class),
                    );

                    $yesterday = new DateTime();
                    $yesterday->modify('-1 day');
                    $yesterdayMinus7 = new DateTime();
                    $yesterdayMinus7->modify('-7 days');
                    $fechaInicio = $yesterdayMinus7->format('Y-m-d');
                    $fechaFin = $yesterday->format('Y-m-d');
                    $mediMEMRepo->syncronizeMeasurements(
                        [],
                        [],
                        $fechaInicio,
                        $fechaFin
                    );
                    break;
                case 'medicionesMediMEM':
                    $mediMEMRepo = new MediMEMRepo(
                        app(MediMEMService::class),
                        app(MeasurementsRepo::class),
                    );
                    $mediMEMRepo->syncronizeMeasurements();
                    break;
                $this->info("El proceso $task ha finalizado correctamente.");
            }
        } catch (\Throwable $th) {
            error_log(
                date("[Y-m-d H:i:s]") . $th . PHP_EOL,
                3,
                storage_path('logs/TaskErrors.log')
            );
        }
    }
}
