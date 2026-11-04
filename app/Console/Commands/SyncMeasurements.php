<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncMeasurements extends Command
{
    protected const TEAMS_WITH_PRESERVED_HISTORY = ['93', '339', '454'];

    protected $signature = 'sync:measurements {team?} {--historical} {--days=} {--cleanup-days=120}';
    protected $description = 'Sincroniza datos de mediciones CC y CE con mapeo correcto de columnas hacia BDs tenant';
    
    public function handle()
    {
        try {
            $days = $this->option('historical') ? 100 : 30;
            if ($this->option('days')) {
                $days = (int) $this->option('days');
            }
            $cleanupDays = (int) $this->option('cleanup-days');

            $desdeFecha = Carbon::now()->subDays($days)->toDateString();
            $limiteFecha = Carbon::now()->subDays($cleanupDays)->toDateString();
            
            $team = $this->argument('team');
            
            if ($team) {
                $this->info("Iniciando proceso para el equipo específico: $team. Sincronizando desde: $desdeFecha (días: $days). Limpiando registros anteriores a: $limiteFecha (días de retención: $cleanupDays)");
                $cacheKey = "sync_running:sync:measurements:{$team}";
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, 3600);
                try {
                    if ($this->setupDynamicConnection($team)) {
                        $this->info("Conectado a la BD del equipo $team.");
                        $this->executeSyncForTeam($desdeFecha, $limiteFecha, $team);
                    } else {
                        $this->error("No se pudo configurar la conexión para el equipo $team (¿Existe en team_databases y está activo?).");
                        return Command::FAILURE;
                    }
                } finally {
                    \Illuminate\Support\Facades\Cache::forget($cacheKey);
                }
            } else {
                $this->info("Iniciando proceso de mediciones en bucle para todos los equipos configurados...");
                $this->info("Rango de sincronización: $desdeFecha (días: $days). Límite de limpieza: $limiteFecha (días: $cleanupDays)");
                $activeTeams = \App\Models\Team::where('active', true)->where('sync_measurements', true)->get();

                if ($activeTeams->isEmpty()) {
                    $this->info("No hay equipos activos con la sincronización de mediciones automática habilitada.");
                    return Command::SUCCESS;
                }

                foreach ($activeTeams as $t) {
                    $teamId = $t->team_id;
                    $this->info("-------------------------------------------------------------");
                    $this->info("Sincronizando mediciones para equipo: $teamId...");

                    $cacheKey = "sync_running:sync:measurements:{$teamId}";
                    \Illuminate\Support\Facades\Cache::put($cacheKey, true, 3600);
                    try {
                        if ($this->setupDynamicConnection($teamId)) {
                            $this->executeSyncForTeam($desdeFecha, $limiteFecha, $teamId);
                        } else {
                            $this->error("No se pudo configurar la conexión para el equipo $teamId.");
                        }
                    } finally {
                        \Illuminate\Support\Facades\Cache::forget($cacheKey);
                    }
                }
            }

            $this->info('Proceso finalizado.');
            return Command::SUCCESS;
        } catch (\Throwable $th) {
            error_log(
                date("[Y-m-d H:i:s]") . " " . $th . PHP_EOL,
                3,
                storage_path('logs/TaskErrors.log')
            );
            try {
                $webhookUrl = 'https://chat.googleapis.com/v1/spaces/AAQA4PXYFE8/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=NFGS9XNCWesmgQgFIx_N0jeus9_NQZeuuuzj2KoJc_s';
                $tz = new \DateTimeZone('-0600');
                $fecha = (new \DateTime('now', $tz))->format('Y-m-d H:i:s');
                $payload = json_encode([
                    'text' => "🚨 *ERROR FATAL EN COMANDO (Sync)*\n*Comando:* `sync:measurements`\n*Error:* " . $th->getMessage() . "\n*Archivo:* " . basename($th->getFile()) . " línea " . $th->getLine() . "\n*Fecha:* " . $fecha
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
                // Ignore webhook errors
            }
            throw $th;
        }
    }

    private function executeSyncForTeam($desdeFecha, $limiteFecha, $team)
    {
        $this->syncCC($desdeFecha, $team);
        $this->syncCE($desdeFecha, $team);

        if (!in_array((string) $team, self::TEAMS_WITH_PRESERVED_HISTORY, true)) {
            $this->cleanupOldRecords($limiteFecha, $team);
        } else {
            $this->info("Se omite la depuración automática para el equipo $team; se conserva su histórico.");
        }
    }

    protected function setupDynamicConnection($teamId)
    {
        $teamConfig = DB::connection('mysql')->table('team_databases')->where('team_id', $teamId)->where('active', 1)->first();

        if (!$teamConfig) {
            return false;
        }

        $mysqlConfig = config('database.connections.mysql');
        $mysqlConfig['database'] = $teamConfig->database_name;
        
        config(['database.connections.tenant' => $mysqlConfig]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        return true;
    }

    protected function syncCC($desdeFecha, $team)
    {
        $this->info("Sincronizando CC para equipo $team...");

        $processRecords = function ($records) {
            $data = $records->map(function ($record) {
                return [
                    'rpu' => $record->rpu,
                    'rmu' => $record->rmu,
                    'fecha' => $record->date,
                    'hora' => $record->hour,
                    'energia' => $record->energy,
                    'energiaOriginal' => $record->ogKVARh,
                    'tipo' => $record->tipo,
                    'tipoOriginal' => $record->ogTipo,
                    'bloqueBIP' => $record->block,
                    'team_id' => $record->teamId,
                    'created_at' => $record->createdAt,
                ];
            })->unique(function ($item) {
                return $item['rpu'].$item['fecha'].$item['hora'];
            })->toArray();

            if (!empty($data)) {
                DB::connection('tenant')->table('medicionesHorariasCC')->upsert($data,
                    ['rpu', 'fecha', 'hora'],
                    ['rmu', 'energia', 'energiaOriginal', 'tipo', 'tipoOriginal', 'bloqueBIP', 'created_at']
                );
            }
        };

        if ($desdeFecha === null) {
            $this->syncHistoricalRows('measurements', 'date', $team, $processRecords, 'CC');
            return;
        }

        $query = DB::connection('mysql_primary')->table('measurements')
            ->where('teamId', $team);

        $query->where('date', '>=', $desdeFecha)
            ->orderBy('createdAt', 'desc')
            ->chunk(1000, $processRecords);
    }

    protected function syncCE($desdeFecha, $team)
    {
        $this->info("Sincronizando CE para equipo $team...");

        $processRecords = function ($records) {
            $data = $records->map(function ($record) {
                return [
                    'nombre' => $record->nombre,
                    'rmu' => $record->rmu,
                    'unidad' => $record->unidad,
                    'claveNodo' => $record->claveNodo,
                    'fecha' => $record->fecha,
                    'hora' => $record->hora,
                    'energia' => $record->energiakWh,
                    'bloqueBIP' => $record->blockCE,
                    'energiaOriginal' => $record->ogEnergy,
                    'tipo' => $record->tipo,
                    'tipoOriginal' => $record->ogTipo,
                    'team_id' => $record->teamId,
                    'created_at' => $record->createdAt,
                ];
            })->unique(function ($item) {
                return $item['nombre'].$item['unidad'].$item['fecha'].$item['hora'];
            })->toArray();

            if (!empty($data)) {
                DB::connection('tenant')->table('medicionesHorariasCE')->upsert($data,
                    ['nombre', 'unidad', 'fecha', 'hora'],
                    ['rmu', 'claveNodo', 'energia', 'bloqueBIP', 'energiaOriginal', 'tipo', 'tipoOriginal', 'created_at']
                );
            }
        };

        if ($desdeFecha === null) {
            $this->syncHistoricalRows('medicionesCentralElectrica', 'fecha', $team, $processRecords, 'CE');
            return;
        }

        $query = DB::connection('mysql_primary')->table('medicionesCentralElectrica')
            ->where('teamId', $team);

        $query->where('fecha', '>=', $desdeFecha)
            ->orderBy('createdAt', 'desc')
            ->chunk(1000, $processRecords);
    }

    private function syncHistoricalRows($table, $dateColumn, $team, $processRecords, $label)
    {
        $primaryKeys = DB::connection('mysql_primary')->select(
            "SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'"
        );

        if (count($primaryKeys) !== 1) {
            throw new \RuntimeException("La tabla {$table} debe tener una clave primaria de una sola columna para paginar el histórico de forma segura.");
        }

        $primaryKey = $primaryKeys[0]->Column_name;
        $lastDate = null;
        $lastId = null;
        $processed = 0;

        do {
            $query = DB::connection('mysql_primary')->table($table)
                ->where('teamId', $team)
                ->whereNotNull($dateColumn);

            if ($lastDate !== null) {
                $query->where(function ($query) use ($dateColumn, $primaryKey, $lastDate, $lastId) {
                    $query->where($dateColumn, '>', $lastDate)
                        ->orWhere(function ($query) use ($dateColumn, $primaryKey, $lastDate, $lastId) {
                            $query->where($dateColumn, '=', $lastDate)
                                ->where($primaryKey, '>', $lastId);
                        });
                });
            }

            $records = $query->orderBy($dateColumn)
                ->orderBy($primaryKey)
                ->limit(1000)
                ->get();

            if ($records->isEmpty()) {
                break;
            }

            $processRecords($records);
            $processed += $records->count();
            $lastRecord = $records->last();
            $lastDate = $lastRecord->{$dateColumn};
            $lastId = $lastRecord->{$primaryKey};

            if ($processed % 100000 === 0 || $records->count() < 1000) {
                $this->info("{$label}: {$processed} registros procesados; última fecha: {$lastDate}.");
            }
        } while ($records->count() === 1000);

        if ($processed === 0) {
            $this->info("{$label}: no se encontraron registros para el equipo {$team}.");
        } elseif ($processed % 100000 === 0) {
            $this->info("{$label}: carga completa de {$processed} registros; última fecha: {$lastDate}.");
        }
    }

    private function cleanupOldRecords($limiteFecha, $team)
    {
        $this->info("Limpiando datos antiguos para equipo $team...");
        DB::connection('tenant')->table('medicionesHorariasCC')->where('fecha', '<', $limiteFecha)->delete();
        DB::connection('tenant')->table('medicionesHorariasCE')->where('fecha', '<', $limiteFecha)->delete();
    }
}
