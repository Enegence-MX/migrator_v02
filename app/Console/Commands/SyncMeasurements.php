<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncMeasurements extends Command
{
    protected $signature = 'sync:measurements {team?} {--historical} {--days=} {--cleanup-days=120}';
    protected $description = 'Sincroniza datos de mediciones CC y CE con mapeo correcto de columnas hacia BDs tenant';
    
    public function handle()
    {
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
    }

    private function executeSyncForTeam($desdeFecha, $limiteFecha, $team)
    {
        $this->syncCC($desdeFecha, $team);
        $this->syncCE($desdeFecha, $team);
        $this->cleanupOldRecords($limiteFecha, $team);
    }

    private function setupDynamicConnection($teamId)
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

    private function syncCC($desdeFecha, $team)
    {
        $this->info("Sincronizando CC para equipo $team...");

        DB::connection('mysql_primary')->table('measurements')
            ->where('teamId', $team)
            ->where('date', '>=', $desdeFecha)
            ->orderBy('createdAt', 'desc')
            ->chunk(1000, function ($records) {
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
            });
    }

    private function syncCE($desdeFecha, $team)
    {
        $this->info("Sincronizando CE para equipo $team...");

        DB::connection('mysql_primary')->table('medicionesCentralElectrica')
            ->where('teamId', $team)
            ->where('fecha', '>=', $desdeFecha)
            ->orderBy('createdAt', 'desc')
            ->chunk(1000, function ($records) {
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
            });
    }

    private function cleanupOldRecords($limiteFecha, $team)
    {
        $this->info("Limpiando datos antiguos para equipo $team...");
        DB::connection('tenant')->table('medicionesHorariasCC')->where('fecha', '<', $limiteFecha)->delete();
        DB::connection('tenant')->table('medicionesHorariasCE')->where('fecha', '<', $limiteFecha)->delete();
    }
}
