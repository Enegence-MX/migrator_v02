<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class SyncLiquidacionesEcd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:liquidaciones-ecd {team?} {--historical} {--days=} {--cleanup-days=100}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza datos de liquidaciones ECD (Diarias y Horarias) desde la BD origen hacia BDs tenant';

    /**
     * Execute the console command.
     */
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
            $this->info("Iniciando sincronización de liquidaciones ECD para el equipo específico: $team. Sincronizando desde: $desdeFecha (días: $days). Limpiando registros anteriores a: $limiteFecha (días de retención: $cleanupDays)...");
            $cacheKey = "sync_running:sync:liquidaciones-ecd:{$team}";
            \Illuminate\Support\Facades\Cache::put($cacheKey, true, 3600);
            try {
                if ($this->setupDynamicConnection($team)) {
                    $this->info("Conectado a la BD del equipo $team.");
                    $this->executeSyncForTeam($team, $desdeFecha, $limiteFecha);
                } else {
                    $this->error("No se pudo configurar la conexión para el equipo $team (¿Existe en team_databases y está activo?).");
                    return Command::FAILURE;
                }
            } finally {
                \Illuminate\Support\Facades\Cache::forget($cacheKey);
            }
        } else {
            $this->info("Iniciando sincronización de liquidaciones ECD en bucle para todos los equipos configurados...");
            $this->info("Rango de sincronización: $desdeFecha (días: $days). Límite de limpieza: $limiteFecha (días: $cleanupDays)");
            $activeTeams = \App\Models\Team::where('active', true)->where('sync_liquidaciones_ecd', true)->get();

            if ($activeTeams->isEmpty()) {
                $this->info("No hay equipos activos con la sincronización de liquidaciones ECD automática habilitada.");
                return Command::SUCCESS;
            }

            foreach ($activeTeams as $t) {
                $teamId = $t->team_id;
                $this->info("-------------------------------------------------------------");
                $this->info("Sincronizando liquidaciones ECD para equipo: $teamId...");

                $cacheKey = "sync_running:sync:liquidaciones-ecd:{$teamId}";
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, 3600);
                try {
                    if ($this->setupDynamicConnection($teamId)) {
                        $this->executeSyncForTeam($teamId, $desdeFecha, $limiteFecha);
                    } else {
                        $this->error("No se pudo configurar la conexión para el equipo $teamId.");
                    }
                } finally {
                    \Illuminate\Support\Facades\Cache::forget($cacheKey);
                }
            }
        }

        $this->info('Sincronización finalizada correctamente.');
        return Command::SUCCESS;
    }

    private function executeSyncForTeam($team, $desdeFecha, $limiteFecha)
    {
        $this->syncLiquidacionesDiariasECD($team, $desdeFecha);
        $this->syncLiquidacionesHorariasECD($team, $desdeFecha);

        $this->info("Limpiando registros de liquidacionesDiariasECD y liquidacionesHorariasECD más antiguos que $limiteFecha...");
        DB::connection('tenant')->table('liquidacionesDiariasECD')->where('fechaOper', '<', $limiteFecha)->delete();
        DB::connection('tenant')->table('liquidacionesHorariasECD')->where('fechaOper', '<', $limiteFecha)->delete();
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

    private function syncLiquidacionesDiariasECD($team, $desdeFecha)
    {
        $this->info("Sincronizando liquidacionesDiariasECD para equipo $team (desde $desdeFecha)...");

        DB::connection('tenant')->transaction(function () use ($team, $desdeFecha) {
            DB::connection('mysql_primary')->table('ecd_montos_diarios')
                ->where('teamId', $team)
                ->where('fecha_oper', '>=', $desdeFecha)
                ->orderBy('ful', 'asc')
                ->orderBy('fuecd', 'asc')
                ->chunk(2000, function ($records) {
                    $data = $records->map(function ($record) {
                        return [
                            'teamId' => $record->teamId,
                            'cuentaDeOrden' => $record->cuenta_de_orden,
                            'fechaOper' => $record->fecha_oper,
                            'fechaFUF' => $record->fecha_fuf,
                            'fuecd' => $record->fuecd,
                            'fuf' => $record->fuf,
                            'folio' => $record->folio,
                            'liquidacion' => $record->liquidacion,
                            'ful' => $record->ful,
                            'mes' => $record->mes,
                            'semana' => $record->semana,
                            'montoTotal' => $record->monto_total,
                            'iva' => $record->iva,
                            'totalNeto' => $record->total_neto,
                            'montoTotalDif' => $record->monto_total_dif,
                            'ivaDif' => $record->iva_dif,
                            'totalNetoDif' => $record->total_neto_dif,
                            'montoTotalRaw' => $record->monto_total_raw,
                            'ivaRaw' => $record->iva_raw,
                            'totalNetoRaw' => $record->total_neto_raw,
                            'montoTotalDifRaw' => $record->monto_total_dif_raw,
                            'ivaDifRaw' => $record->iva_dif_raw,
                            'totalNetoDifRaw' => $record->total_neto_dif_raw,
                            'createdAt' => $record->createdAt,
                            'isAnexoElementoNull' => $record->isAnexoElementoNull,
                        ];
                    })->toArray();

                    if (!empty($data)) {
                        DB::connection('tenant')->table('liquidacionesDiariasECD')->upsert($data, ['teamId', 'ful', 'fuecd'], [
                            'cuentaDeOrden', 'fechaOper', 'fechaFUF', 'fuf', 'folio', 'liquidacion', 'mes', 'semana',
                            'montoTotal', 'iva', 'totalNeto', 'montoTotalDif', 'ivaDif', 'totalNetoDif',
                            'montoTotalRaw', 'ivaRaw', 'totalNetoRaw', 'montoTotalDifRaw', 'ivaDifRaw', 'totalNetoDifRaw',
                            'createdAt', 'isAnexoElementoNull'
                        ]);
                    }
                });
        });
    }

    private function syncLiquidacionesHorariasECD($team, $desdeFecha)
    {
        $this->info("Sincronizando liquidacionesHorariasECD para equipo $team (desde $desdeFecha)...");

        $t1 = microtime(true);
        $minDate = DB::connection('mysql_primary')->table('ecd_registros_horarios')
            ->where('teamId', $team)
            ->where('fecha_oper', '>=', $desdeFecha)
            ->min('fecha_oper');

        $maxDate = DB::connection('mysql_primary')->table('ecd_registros_horarios')
            ->where('teamId', $team)
            ->where('fecha_oper', '>=', $desdeFecha)
            ->max('fecha_oper');

        if (!$minDate || !$maxDate) {
            $this->info("No hay registros horarios para el equipo $team.");
            return;
        }

        $timeTaken = round(microtime(true) - $t1, 2);
        $this->info("Rango de fechas detectado: $minDate a $maxDate (Obtenido en {$timeTaken}s).");

        $currentDate = Carbon::parse($minDate)->startOfMonth();
        $endDate = Carbon::parse($maxDate)->endOfMonth();
        $totalProcessed = 0;

        while ($currentDate->lte($endDate)) {
            $startOfMonth = $currentDate->toDateString();
            $endOfMonth = $currentDate->copy()->endOfMonth()->toDateString();

            $this->info("  -> Procesando mes: " . $currentDate->format('Y-m') . " ($startOfMonth a $endOfMonth)...");

            DB::connection('mysql_primary')->table('ecd_registros_horarios')
                ->where('teamId', $team)
                ->where('fecha_oper', '>=', $desdeFecha)
                ->whereBetween('fecha_oper', [$startOfMonth, $endOfMonth])
                ->chunkById(2000, function ($records) use (&$totalProcessed) {
                    $data = $records->map(function ($record) {
                        return [
                            'id' => $record->id,
                            'teamId' => $record->teamId,
                            'cuentaDeOrden' => $record->cuenta_de_orden,
                            'fuf' => $record->fuf,
                            'folio' => $record->folio,
                            'liquidacion' => $record->liquidacion,
                            'ful' => $record->ful,
                            'anexoElemento' => $record->anexo_elemento,
                            'nodo' => $record->nodo,
                            'fechaOper' => $record->fecha_oper,
                            'hora' => $record->hora,
                            'montoHorario' => $record->monto_horario,
                            'precio' => $record->precio,
                            'potenciaMDA' => $record->potencia_mda,
                            'potenciaMTR' => $record->potencia_mtr,
                            'montoDiario' => $record->monto_diario,
                            'precioGSI' => $record->precio_gsi,
                            'fdp' => $record->fdp,
                            'precioRSUP' => $record->precio_rsup,
                            'prcioRNR10' => $record->precio_rnr10,
                            'precioRR10' => $record->precio_rr10,
                            'precioRREG' => $record->precio_rreg,
                            'potenciaERCMDA' => $record->potencia_erc_mda,
                            'potenciaERCMTR' => $record->potencia_erc_mtr,
                            'capProgRSUPMDA' => $record->cap_prog_rsup_mda,
                            'capProgRSUPMTR' => $record->cap_prog_rsup_mtr,
                            'capProgRNR10MDA' => $record->cap_prog_rnr10_mda,
                            'capProgRNR10MTR' => $record->cap_prog_rnr10_mtr,
                            'capProgRR10MDA' => $record->cap_prog_rr10_mda,
                            'capProgRR10MTR' => $record->cap_prog_rr10_mtr,
                            'capProgRREGMDA' => $record->cap_prog_rreg_mda,
                            'capProgRREGMTR' => $record->cap_prog_rreg_mtr,
                            'zonaReserva' => $record->zona_reserva,
                            'monto' => $record->monto,
                            'potencia' => $record->potencia,
                            'factor' => $record->factor,
                            'claveElementoTBF' => $record->clv_elemento_tbf,
                            'divisionDistribucion' => $record->division_distribucion,
                            'tipoTarifa' => $record->tipo_tarifa,
                            'precioTarifa' => $record->precio_tarifa,
                            'cantidad' => $record->cantidad,
                            'elemento' => $record->elemento,
                            'claveNodoOrigen' => $record->clv_nodo_origen,
                            'claveNodoRetiro' => $record->clv_nodo_retiro,
                            'pmlCongestionOrigen' => $record->pml_cng_origen,
                            'pmlCongestionRetiro' => $record->pml_cng_retiro,
                            'factorPondRetiro' => $record->factor_pond_retiro,
                            'factorPondOrigen' => $record->factor_pond_origen,
                            'energia' => $record->energia,
                            'energiaFisica' => $record->energia_fisica,
                            'precioSobrecobro' => $record->precio_sobrecobro,
                            'fuecd' => $record->fuecd,
                            'createdAt' => $record->createdAt,
                            'fechaFUF' => $record->fecha_fuf,
                        ];
                    })->toArray();

                    if (!empty($data)) {
                        DB::connection('tenant')->transaction(function () use ($data) {
                            $subChunks = array_chunk($data, 1000);
                            foreach ($subChunks as $subChunk) {
                                DB::connection('tenant')->table('liquidacionesHorariasECD')->upsert($subChunk, ['id'], [
                                    'teamId', 'cuentaDeOrden', 'fuf', 'folio', 'liquidacion', 'ful', 'anexoElemento', 'nodo', 'fechaOper', 'hora',
                                    'montoHorario', 'precio', 'potenciaMDA', 'potenciaMTR', 'montoDiario', 'precioGSI', 'fdp', 'precioRSUP',
                                    'prcioRNR10', 'precioRR10', 'precioRREG', 'potenciaERCMDA', 'potenciaERCMTR', 'capProgRSUPMDA', 'capProgRSUPMTR',
                                    'capProgRNR10MDA', 'capProgRNR10MTR', 'capProgRR10MDA', 'capProgRR10MTR', 'capProgRREGMDA', 'capProgRREGMTR',
                                    'zonaReserva', 'monto', 'potencia', 'factor', 'claveElementoTBF', 'divisionDistribucion', 'tipoTarifa',
                                    'precioTarifa', 'cantidad', 'elemento', 'claveNodoOrigen', 'claveNodoRetiro', 'pmlCongestionOrigen',
                                    'pmlCongestionRetiro', 'factorPondRetiro', 'factorPondOrigen', 'energia', 'energiaFisica', 'precioSobrecobro',
                                    'fuecd', 'createdAt', 'fechaFUF'
                                ]);
                            }
                        });
                        $totalProcessed += count($data);
                    }
                }, 'id');

            $currentDate->addMonth();
        }

        $this->info("Sincronización de liquidacionesHorariasECD finalizada para el equipo $team. Total registros procesados/actualizados: $totalProcessed");
    }
}
