<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncMeasurementsHistory extends SyncMeasurements
{
    protected $signature = 'sync:measurements-history {team : ID del equipo (93, 339 o 454)}';
    protected $description = 'Sincroniza manualmente el histórico completo de mediciones CC y CE de un equipo';

    public function handle()
    {
        $team = (string) $this->argument('team');

        if (!in_array($team, self::TEAMS_WITH_PRESERVED_HISTORY, true)) {
            $this->error('Este comando solo admite los equipos 93, 339 y 454.');
            return Command::FAILURE;
        }

        $cacheKey = "sync_running:sync:measurements:{$team}";
        Cache::put($cacheKey, true, 3600);

        try {
            if (!$this->setupDynamicConnection($team)) {
                $this->error("No se pudo configurar la conexión para el equipo $team (¿Existe en team_databases y está activo?).");
                return Command::FAILURE;
            }

            $this->info("Sincronizando el histórico completo de CC y CE para el equipo $team. No se eliminarán registros.");
            $this->syncCC(null, $team);
            $this->syncCE(null, $team);
            $this->info('Proceso finalizado.');

            return Command::SUCCESS;
        } finally {
            Cache::forget($cacheKey);
        }
    }
}
