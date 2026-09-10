<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncCatalogData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:catalog-data {team?} {--historical} {--days=} {--cleanup-days=100}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza datos de catálogos (centralElectrica y centrosCarga) desde la BD origen hacia BDs tenant';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('historical') ? 100 : 3;
        if ($this->option('days')) {
            $days = (int) $this->option('days');
        }
        $cleanupDays = (int) $this->option('cleanup-days');

        $desdeFecha = Carbon::now()->subDays($days)->toDateString();
        $limiteFecha = Carbon::now()->subDays($cleanupDays)->toDateString();

        $team = $this->argument('team');

        if ($team) {
            $this->info("Iniciando sincronización de catálogos para el equipo específico: $team. Sincronizando desde: $desdeFecha (días: $days). Limpiando registros anteriores a: $limiteFecha (días de retención: $cleanupDays)...");
            $cacheKey = "sync_running:sync:catalog-data:{$team}";
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
            $this->info("Iniciando sincronización de catálogos en bucle para todos los equipos configurados...");
            $this->info("Rango de sincronización: $desdeFecha (días: $days). Límite de limpieza: $limiteFecha (días: $cleanupDays)");
            $activeTeams = \App\Models\Team::where('active', true)->where('sync_catalog_data', true)->get();

            if ($activeTeams->isEmpty()) {
                $this->info("No hay equipos activos con la sincronización de catálogos automática habilitada.");
                return Command::SUCCESS;
            }

            foreach ($activeTeams as $t) {
                $teamId = $t->team_id;
                $this->info("-------------------------------------------------------------");
                $this->info("Sincronizando catálogos para equipo: $teamId...");

                $cacheKey = "sync_running:sync:catalog-data:{$teamId}";
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
        $this->syncPlantasGeneracion($team);
        $this->syncCentrosCarga($team);
        $this->syncProyeccionesEnergia($team, $desdeFecha);
        $this->syncOfertasDeCompraDeEnergia($team, $desdeFecha);
        $this->syncOfertasDeVentaDeEnergia($team, $desdeFecha);
        $this->syncCalculosDeContratos($team);
        $this->syncPreciosGas($team, $desdeFecha);
        $this->cleanupOldRecords($limiteFecha);
    }

    private function setupDynamicConnection($teamId)
    {
        $teamConfig = DB::connection('mysql')->table('team_databases')->where('team_id', $teamId)->where('active', 1)->first();

        if (!$teamConfig) {
            return false;
        }
        $mysqlConfig = config('database.connections.mysql');
        $mysqlConfig['database'] = $teamConfig->database_name;

        $credential = DB::connection('mysql')
            ->table('team_credentials')
            ->where('team_id', $teamId)
            ->first();

        if ($credential) {
            $mysqlConfig['username'] = $credential->db_username;
            $mysqlConfig['password'] = $credential->db_password;
        }

        config(['database.connections.tenant' => $mysqlConfig]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        return true;
    }

    private function syncPlantasGeneracion($team)
    {
        $this->info("Sincronizando plantasGeneracion (desde centralElectrica) para equipo $team...");

        DB::connection('mysql_primary')->table('centralElectrica')
            ->where('teamId', $team)
            ->orderBy('id', 'asc')
            ->chunk(500, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'teamId' => $record->teamId,
                        'name' => $record->name,
                        'nivelTension' => $record->nivelTension,
                        'nodoP' => $record->nodoP,
                        'zonaCarga' => $record->zonaCarga,
                        'anexoElemento' => $record->anexoElementoDelECD,
                        'aliasCliente' => $record->aliasDeCliente,
                        'grupoTarifario' => $record->grupoTarifario,
                        'cuentaDeOrdenDelECD' => $record->cuentaDeOrdenDelECD,
                        'fechaInicioDeOperacion' => $record->fechaInicioDeOperacion,
                        'rmu' => $record->rmu,
                        'ccAsociado' => $record->ccAsociado,
                        'created_at' => $record->created_at,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('plantasGeneracion')->upsert($data, ['id'], [
                        'teamId', 'name', 'nivelTension', 'nodoP', 'zonaCarga', 
                        'anexoElemento', 'aliasCliente', 'grupoTarifario', 
                        'cuentaDeOrdenDelECD', 'fechaInicioDeOperacion', 'rmu', 'ccAsociado', 'created_at'
                    ]);
                }
            });
    }

    private function syncCentrosCarga($team)
    {
        $this->info("Sincronizando centrosCarga para equipo $team...");

        DB::connection('mysql_primary')->table('centrosCarga')
            ->where('teamId', $team)
            ->orderBy('id', 'asc')
            ->chunk(500, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'teamId' => $record->teamId,
                        'cuentaDeOrdenDelECD' => $record->cuentaDeOrdenDelECD,
                        'rpu' => $record->rpu,
                        'rmu' => $record->rmu,
                        'grupoTarifario' => $record->grupoTarifario,
                        'nivelTension' => $record->nivelTension,
                        'nivelTensionGroup' => $record->nivelTensionGroup,
                        'nodoP' => $record->nodoP,
                        'zonaCarga' => $record->zonaCarga,
                        'divisionDistribucion' => $record->divisionDistribucion,
                        'factorCarga' => $record->factorCarga,
                        'created_at' => $record->created_at,
                        'aliasDeCliente' => $record->aliasDeCliente,
                        'fechaInicioDeOperacion' => $record->fechaInicioDeOperacion,
                        'ceAsociada' => $record->ceAsociada,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('centrosCarga')->upsert($data, ['id'], [
                        'teamId', 'cuentaDeOrdenDelECD', 'rpu', 'rmu', 'grupoTarifario',
                        'nivelTension', 'nivelTensionGroup', 'nodoP', 'zonaCarga',
                        'divisionDistribucion', 'factorCarga', 'created_at',
                        'aliasDeCliente', 'fechaInicioDeOperacion', 'ceAsociada'
                    ]);
                }
            });
    }

    private function syncProyeccionesEnergia($team, $desdeFecha)
    {
        $this->info("Sincronizando proyeccionesEnergia para equipo $team (desde $desdeFecha)...");

        DB::connection('mysql_primary')->table('proyecciones')
            ->where('teamId', $team)
            ->where('date', '>=', $desdeFecha)
            ->orderBy('date', 'asc')
            ->orderBy('hour', 'asc')
            ->chunk(1000, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'rpu' => $record->rpu,
                        'teamId' => $record->teamId,
                        'claveNodo' => $record->claveNodo,
                        'fecha' => $record->date,
                        'hora' => $record->hour,
                        'energia' => $record->energy,
                        'KVARh' => $record->KVARh,
                        'block' => $record->block,
                        'created_at' => $record->createdAt,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('proyeccionesEnergia')->upsert($data, ['rpu', 'fecha', 'hora'], [
                        'teamId', 'claveNodo', 'energia', 'KVARh', 'block', 'created_at'
                    ]);
                }
            });
    }

    private function syncOfertasDeCompraDeEnergia($team, $desdeFecha)
    {
        $this->info("Sincronizando ofertasDeCompraDeEnergia para equipo $team (desde $desdeFecha)...");

        DB::connection('mysql_primary')->table('ofertasGeneradasCompra')
            ->where('teamId', $team)
            ->where('fecha', '>=', $desdeFecha)
            ->orderBy('id', 'asc')
            ->chunk(1000, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'teamId' => $record->teamId,
                        'sistema' => $record->sistema,
                        'proceso' => $record->proceso,
                        'anexoElementoDelECD' => $record->anexoElementoDelECD,
                        'nodo' => $record->nodo,
                        'fecha' => $record->fecha,
                        'hora' => $record->hora,
                        'idSubInt' => $record->idSubInt,
                        'demandaFijaMw' => $record->demandaFijaMw,
                        'bloquePotencia01' => $record->bloquePotencia01,
                        'precioBloque01' => $record->precioBloque01,
                        'bloquePotencia02' => $record->bloquePotencia02,
                        'precioBloque02' => $record->precioBloque02,
                        'bloquePotencia03' => $record->bloquePotencia03,
                        'precioBloque03' => $record->precioBloque03,
                        'fechaCreacion' => $record->fechaCreacion,
                        'codigo' => $record->codigo,
                        'created_at' => $record->created_at,
                        'pronostico' => $record->pronostico,
                        'ofertaCompraCabeceraId' => $record->ofertaCompraCabeceraId,
                        'ofertaOptima' => $record->ofertaOptima,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('ofertasDeCompraDeEnergia')->upsert($data, ['id'], [
                        'teamId', 'sistema', 'proceso', 'anexoElementoDelECD', 'nodo', 'fecha', 'hora', 'idSubInt',
                        'demandaFijaMw', 'bloquePotencia01', 'precioBloque01', 'bloquePotencia02', 'precioBloque02',
                        'bloquePotencia03', 'precioBloque03', 'fechaCreacion', 'codigo', 'created_at', 'pronostico',
                        'ofertaCompraCabeceraId', 'ofertaOptima'
                    ]);
                }
            });
    }

    private function syncOfertasDeVentaDeEnergia($team, $desdeFecha)
    {
        $this->info("Sincronizando ofertasDeVentaDeEnergia para equipo $team (desde $desdeFecha)...");

        DB::connection('mysql_primary')->table('ofertasGeneradasVenta')
            ->where('teamId', $team)
            ->where('fecha', '>=', $desdeFecha)
            ->orderBy('id', 'asc')
            ->chunk(1000, function ($records) {
                $data = $records->map(function ($record) {
                    $item = [
                        'id' => $record->id,
                        'teamId' => $record->teamId,
                        'sistema' => $record->sistema,
                        'proceso' => $record->proceso,
                        'clvCentral' => $record->clvCentral,
                        'clvUnidad' => $record->clvUnidad,
                        'nodo' => $record->nodo,
                        'fecha' => $record->fecha,
                        'fechaFinal' => $record->fechaFinal,
                        'hora' => $record->hora,
                        'idSubInt' => $record->idSubInt,
                        'tipoOferta' => $record->tipoOferta,
                        'estatusAsignacion' => $record->estatusAsignacion,
                        'costoArranqueCaliente' => $record->costoArranqueCaliente,
                        'costoArranqueTibio' => $record->costoArranqueTibio,
                        'costoArranqueFrio' => $record->costoArranqueFrio,
                        'tiempoParoCaliente' => $record->tiempoParoCaliente,
                        'tiempoParoTibio' => $record->tiempoParoTibio,
                        'tiempoParoFrio' => $record->tiempoParoFrio,
                        'oaMezclaCalienteGas' => $record->oaMezclaCalienteGas,
                        'oaMezclaCalienteCombustoleo' => $record->oaMezclaCalienteCombustoleo,
                        'oaMezclaCalienteDiesel' => $record->oaMezclaCalienteDiesel,
                        'oaMezclaCalienteCa' => $record->oaMezclaCalienteCa,
                        'oaMezclaCalienteCap' => $record->oaMezclaCalienteCap,
                        'oaMezclaCalienteCas' => $record->oaMezclaCalienteCas,
                        'oaMezclaTibioGas' => $record->oaMezclaTibioGas,
                        'oaMezclaTibioCombustoleo' => $record->oaMezclaTibioCombustoleo,
                        'oaMezclaTibioDiesel' => $record->oaMezclaTibioDiesel,
                        'oaMezclaTibioCa' => $record->oaMezclaTibioCa,
                        'oaMezclaTibioCap' => $record->oaMezclaTibioCap,
                        'oaMezclaTibioCas' => $record->oaMezclaTibioCas,
                        'oaMezclaFrioGas' => $record->oaMezclaFrioGas,
                        'oaMezclaFrioCombustoleo' => $record->oaMezclaFrioCombustoleo,
                        'oaMezclaFrioDiesel' => $record->oaMezclaFrioDiesel,
                        'oaMezclaFrioCa' => $record->oaMezclaFrioCa,
                        'oaMezclaFrioCap' => $record->oaMezclaFrioCap,
                        'oaMezclaFrioCas' => $record->oaMezclaFrioCas,
                        'costoOportunidad' => $record->costoOportunidad,
                        'limDespEcoMax' => $record->limDespEcoMax,
                        'limDespEcoMin' => $record->limDespEcoMin,
                        'limDespEmerMax' => $record->limDespEmerMax,
                        'limDespEmerMin' => $record->limDespEmerMin,
                        'limRegMin' => $record->limRegMin,
                        'limRegMax' => $record->limRegMax,
                        'indiceComb' => $record->indiceComb,
                        'mezclaCombustible' => $record->mezclaCombustible,
                        'combPrincipal' => $record->combPrincipal,
                        'combAlterno' => $record->combAlterno,
                        'oiMezcla1Gas' => $record->oiMezcla1Gas,
                        'oiMezcla1Combustoleo' => $record->oiMezcla1Combustoleo,
                        'oiMezcla1Diesel' => $record->oiMezcla1Diesel,
                        'oiMezcla1Ca' => $record->oiMezcla1Ca,
                        'oiMezcla1Cap' => $record->oiMezcla1Cap,
                        'oiMezcla1Cas' => $record->oiMezcla1Cas,
                        'oiMezcla2Gas' => $record->oiMezcla2Gas,
                        'oiMezcla2Combustoleo' => $record->oiMezcla2Combustoleo,
                        'oiMezcla2Diesel' => $record->oiMezcla2Diesel,
                        'oiMezcla2Ca' => $record->oiMezcla2Ca,
                        'oiMezcla2Cap' => $record->oiMezcla2Cap,
                        'oiMezcla2Cas' => $record->oiMezcla2Cas,
                        'oiMezcla3Gas' => $record->oiMezcla3Gas,
                        'oiMezcla3Combustoleo' => $record->oiMezcla3Combustoleo,
                        'oiMezcla3Diesel' => $record->oiMezcla3Diesel,
                        'oiMezcla3Ca' => $record->oiMezcla3Ca,
                        'oiMezcla3Cap' => $record->oiMezcla3Cap,
                        'oiMezcla3Cas' => $record->oiMezcla3Cas,
                        'costoOpePotenciaMin' => $record->costoOpePotenciaMin,
                    ];

                    for ($i = 1; $i <= 11; $i++) {
                        $idxStr = str_pad($i, 2, '0', STR_PAD_LEFT);
                        $item["oiMw{$idxStr}"] = $record->{"oiMw{$idxStr}"};
                        $item["oiPrecio{$idxStr}"] = $record->{"oiPrecio{$idxStr}"};
                    }

                    $item['orgMwRR10'] = $record->orgMwRR10;
                    $item['orgPrecioRR10'] = $record->orgPrecioRR10;
                    $item['orgMwRNR10'] = $record->orgMwRNR10;
                    $item['orgPrecioRNR10'] = $record->orgPrecioRNR10;
                    $item['orgMwRRS'] = $record->orgMwRRS;
                    $item['orgPrecioRRS'] = $record->orgPrecioRRS;
                    $item['orgMwRNRS'] = $record->orgMwRNRS;
                    $item['orgPrecioRNRS'] = $record->orgPrecioRNRS;
                    $item['orgMwRREG'] = $record->orgMwRREG;
                    $item['orgPrecioRREG'] = $record->orgPrecioRREG;
                    $item['pronosticoMW'] = $record->pronosticoMW;

                    for ($i = 1; $i <= 3; $i++) {
                        $idxStr = str_pad($i, 2, '0', STR_PAD_LEFT);
                        $item["percPronostico{$idxStr}"] = $record->{"percPronostico{$idxStr}"};
                        $item["percPrecio{$idxStr}"] = $record->{"percPrecio{$idxStr}"};
                    }

                    $item['fechaCreacion'] = $record->fechaCreacion;
                    $item['vigencia'] = $record->vigencia;
                    $item['estatusEnvio'] = $record->estatusEnvio;
                    $item['codigo'] = $record->codigo;
                    $item['created_at'] = $record->created_at;

                    return $item;
                })->toArray();

                if (!empty($data)) {
                    $keysToUpdate = array_keys(current($data));
                    $keysToUpdate = array_filter($keysToUpdate, function($k) { return $k !== 'id'; });
                    DB::connection('tenant')->table('ofertasDeVentaDeEnergia')->upsert($data, ['id'], array_values($keysToUpdate));
                }
            });
    }

    private function syncCalculosDeContratos($team)
    {
        $this->info("Sincronizando calculosDeContratos para equipo $team...");

        DB::connection('mysql_primary')->table('calculationsResults')
            ->where('teamId', $team)
            ->orderBy('id', 'asc')
            ->chunk(1000, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'teamId' => $record->teamId,
                        'contractId' => $record->contractId,
                        'contractCalculationNumber' => $record->contractCalculationNumber,
                        'startDateParam' => $record->startDateParam,
                        'endDateParam' => $record->endDateParam,
                        'centrosDeCargaParam' => $record->centrosDeCargaParam,
                        'centralesElectricasParam' => $record->centralesElectricasParam,
                        'quantityEnergySectionSum' => $record->quantityEnergySectionSum,
                        'CFE_SSB' => $record->CFE_SSB,
                        'energyAmount' => $record->energyAmount,
                        'capacityAmount' => $record->capacityAmount,
                        'cleanEnergyCertificateAmount' => $record->cleanEnergyCertificateAmount,
                        'regulatedTariffAmount' => $record->regulatedTariffAmount,
                        'marketCostAmount' => $record->marketCostAmount,
                        'associatedProductsAmount' => $record->associatedProductsAmount,
                        'othersAmount' => $record->othersAmount,
                        'receptor' => $record->receptor,
                        'tipoComprobante' => $record->tipoComprobante,
                        'fechaEmision' => $record->fechaEmision,
                        'fechaEmisionComplemento' => $record->fechaEmisionComplemento,
                        'moneda' => $record->moneda,
                        'subtotal' => $record->subtotal,
                        'iva' => $record->iva,
                        'total' => $record->total,
                        'uuid' => $record->uuid,
                        'fechaPago' => $record->fechaPago,
                        'metodoPago' => $record->metodoPago,
                        'uuidComplemento' => $record->uuidComplemento,
                        'descuento' => $record->descuento,
                        'ajuste' => $record->ajuste,
                        'importeNota' => $record->importeNota,
                        'fechaParcial' => $record->fechaParcial,
                        'metodoParcial' => $record->metodoParcial,
                        'pagoParcial' => $record->pagoParcial,
                        'uuidParcial' => $record->uuidParcial,
                        'created_at' => $record->created_at,
                        'contractType' => $record->contractType,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('calculosDeContratos')->upsert($data, ['id'], [
                        'teamId', 'contractId', 'contractCalculationNumber', 'startDateParam', 'endDateParam',
                        'centrosDeCargaParam', 'centralesElectricasParam', 'quantityEnergySectionSum', 'CFE_SSB',
                        'energyAmount', 'capacityAmount', 'cleanEnergyCertificateAmount', 'regulatedTariffAmount',
                        'marketCostAmount', 'associatedProductsAmount', 'othersAmount', 'receptor', 'tipoComprobante',
                        'fechaEmision', 'fechaEmisionComplemento', 'moneda', 'subtotal', 'iva', 'total', 'uuid',
                        'fechaPago', 'metodoPago', 'uuidComplemento', 'descuento', 'ajuste', 'importeNota',
                        'fechaParcial', 'metodoParcial', 'pagoParcial', 'uuidParcial', 'created_at', 'contractType'
                    ]);
                }
            });
    }

    private function syncPreciosGas($team, $desdeFecha)
    {
        $this->info("Sincronizando preciosGasHB (desde indexed_gas_prices) para equipo $team (desde $desdeFecha)...");

        DB::connection('mysql_primary')->table('indexed_gas_prices')
            ->where('team_id', $team)
            ->where('date', '>=', $desdeFecha)
            ->orderBy('id', 'asc')
            ->chunk(1000, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'team_id' => $record->team_id,
                        'file_name' => $record->file_name,
                        'fecha' => $record->date,
                        'precio' => $record->price,
                        'nombreDeSerie' => $record->index,
                        'created_at' => $record->created_at,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('preciosGasHB')->upsert($data, ['id'], [
                        'team_id', 'file_name', 'fecha', 'precio', 'nombreDeSerie', 'created_at'
                    ]);
                }
            });
    }

    private function cleanupOldRecords($limiteFecha)
    {
        $this->info("Limpiando datos antiguos de catálogo/ofertas anteriores a $limiteFecha...");
        DB::connection('tenant')->table('proyeccionesEnergia')->where('fecha', '<', $limiteFecha)->delete();
        DB::connection('tenant')->table('ofertasDeCompraDeEnergia')->where('fecha', '<', $limiteFecha)->delete();
        DB::connection('tenant')->table('ofertasDeVentaDeEnergia')->where('fecha', '<', $limiteFecha)->delete();
        DB::connection('tenant')->table('preciosGasHB')->where('fecha', '<', $limiteFecha)->delete();
        DB::connection('tenant')->table('calculosDeContratos')->where('startDateParam', '<', $limiteFecha)->delete();
    }
}
