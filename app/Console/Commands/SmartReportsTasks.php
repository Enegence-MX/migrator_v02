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
            try {
                $webhookUrl = 'https://chat.googleapis.com/v1/spaces/AAQA4PXYFE8/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=NFGS9XNCWesmgQgFIx_N0jeus9_NQZeuuuzj2KoJc_s';
                $tz = new \DateTimeZone('-0600');
                $fecha = (new \DateTime('now', $tz))->format('Y-m-d H:i:s');
                $payload = json_encode([
                    'text' => "🚨 *ERROR FATAL EN COMANDO (Fuera de Sincronización)*\n*Comando:* `app:reports-tasks`\n*Error:* " . $th->getMessage() . "\n*Archivo:* " . basename($th->getFile()) . " línea " . $th->getLine() . "\n*Fecha:* " . $fecha
                ]);
                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_exec($ch);
                curl_close($ch);
            } catch (\Throwable $e) {
                // No romper la ejecucion en caso de error en el envio de notificacion
            }
        }
    }
}
