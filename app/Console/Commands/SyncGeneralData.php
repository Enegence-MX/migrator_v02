<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncGeneralData extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sync:general-data {team?} {--historical} {--days=} {--cleanup-days=100}';

    /**
     * @var string
     */
    protected $description = 'Sincroniza datos generales e información del mercado eléctrico (PML y PND) desde la BD enegence_dev hacia las BDs de los tenants';

    public function handle()
    {
        try {
            if (!$this->setupDevConnection()) {
                $this->error("No se pudo configurar la conexión de origen (mysql_dev) a la base de datos 'enegence_dev'.");
                return Command::FAILURE;
            }

            $isHistorical = $this->option('historical');
            $customDays = $this->option('days') ? (int) $this->option('days') : null;
            $cleanupDays = (int) $this->option('cleanup-days');

            $limiteFecha = Carbon::now()->subDays($cleanupDays)->toDateString();

            if ($isHistorical || $customDays !== null) {
                $hDays = $customDays !== null ? $customDays : 100;
                $desde3d = Carbon::now()->subDays($hDays)->toDateString();
                $desde5d = $desde3d;
                $desde7d = $desde3d;
            } else {
                $desde3d = Carbon::now()->subDays(3)->toDateString();
                $desde5d = Carbon::now()->subDays(5)->toDateString();
                $desde7d = Carbon::now()->subDays(7)->toDateString();
            }

            $team = $this->argument('team');

            if ($team) {
                $this->info("Iniciando sincronización de datos generales para el equipo específico: $team...");
                $cacheKey = "sync_running:sync:general-data:{$team}";
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, 3600);
                try {
                    if ($this->setupDynamicConnection($team)) {
                        $this->info("Conectado a la BD del equipo $team.");
                        $this->executeSyncForTeam($team, $desde3d, $desde5d, $desde7d, $limiteFecha);
                    } else {
                        $this->error("No se pudo configurar la conexión para el equipo $team (¿Existe en team_databases y está activo?).");
                        return Command::FAILURE;
                    }
                } finally {
                    \Illuminate\Support\Facades\Cache::forget($cacheKey);
                }
            } else {
                $this->info("Iniciando sincronización de datos generales en bucle para todos los equipos configurados...");
                $this->info("Rango 3 días: $desde3d | 5 días: $desde5d | 7 días: $desde7d | Limpieza: $limiteFecha");
                $activeTeams = \App\Models\Team::where('active', true)->where('sync_general_data', true)->get();

                if ($activeTeams->isEmpty()) {
                    $this->info("No hay equipos activos con la sincronización general automática habilitada.");
                    return Command::SUCCESS;
                }

                $processedDbs = [];
                foreach ($activeTeams as $t) {
                    $teamId = $t->team_id;
                    $teamConfig = DB::connection('mysql')->table('team_databases')->where('team_id', $teamId)->where('active', 1)->first();
                    if (!$teamConfig) {
                        $this->warn("No se encontró base de datos activa para el equipo $teamId. Saltando...");
                        continue;
                    }

                    $dbName = $teamConfig->database_name;
                    if (in_array($dbName, $processedDbs)) {
                        $this->info("La base de datos '$dbName' ya fue sincronizada en esta corrida (Equipo: $teamId). Saltando redundancia...");
                        continue;
                    }

                    $this->info("-------------------------------------------------------------");
                    $this->info("Sincronizando equipo: $teamId (BD: $dbName)...");

                    $cacheKey = "sync_running:sync:general-data:{$teamId}";
                    \Illuminate\Support\Facades\Cache::put($cacheKey, true, 3600);
                    try {
                        if ($this->setupDynamicConnection($teamId)) {
                            $this->executeSyncForTeam($teamId, $desde3d, $desde5d, $desde7d, $limiteFecha);
                            $processedDbs[] = $dbName;
                        } else {
                            $this->error("No se pudo configurar la conexión para el equipo $teamId.");
                        }
                    } finally {
                        \Illuminate\Support\Facades\Cache::forget($cacheKey);
                    }
                }
            }

            $this->info("-------------------------------------------------------------");
            $this->info('Sincronización de datos generales finalizada correctamente.');
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
                    'text' => "🚨 *ERROR FATAL EN COMANDO (Sync)*\n*Comando:* `sync:general-data`\n*Error:* " . $th->getMessage() . "\n*Archivo:* " . basename($th->getFile()) . " línea " . $th->getLine() . "\n*Fecha:* " . $fecha
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

    private function executeSyncForTeam($team, $desde3d, $desde5d, $desde7d, $limiteFecha)
    {
        $this->syncEnergiaAsignadaZonadeCarga($desde3d);
        $this->syncOfertasDeCompraPorTipo($desde3d);
        $this->syncOfertasDeVentaPorTipo($desde3d);

        $this->syncPmlMda($desde3d);
        $this->syncPmlMtr($desde5d);
        $this->syncPndMda($desde7d);
        $this->syncPndMtr($desde7d);

        $this->syncTipoCambioLiquidacion($desde3d);
        $this->syncTipoCambioFix($desde3d);
        $this->syncEnergiaTecnologia($desde3d);
        $this->syncErcot($desde3d);

        $rpus = DB::connection('tenant')->table('centrosCarga')->pluck('rpu')->filter()->unique()->toArray();

        $this->syncConceptosCalculoContrato($rpus, $desde5d);
        $this->syncVariablesContrato($rpus, $desde5d);

        $this->syncCatalogosDeNodosP();
        $this->syncDiccionarioFoliosLiquidacion();

        $this->cleanupOldRecords($limiteFecha);
    }

    private function setupDevConnection()
    {
        $primaryConfig = config('database.connections.mysql_primary');
        if (!$primaryConfig) {
            return false;
        }

        $devConfig = $primaryConfig;
        $devConfig['database'] = 'enegence_dev';

        config(['database.connections.mysql_dev' => $devConfig]);
        DB::purge('mysql_dev');
        DB::reconnect('mysql_dev');

        return true;
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

    private function syncPmlMda($desdeFecha)
    {
        $this->info("Sincronizando preciosPML_MDA (desde pmlMda) desde $desdeFecha...");

        $maxFecha = DB::connection('tenant')->table('preciosPML_MDA')->max('fecha');
        $desdeFechaFinal = $desdeFecha;
        if ($maxFecha) {
            $bufferFecha = Carbon::parse($maxFecha)->subDays(3)->toDateString();
            if ($bufferFecha > $desdeFecha) {
                $desdeFechaFinal = $bufferFecha;
            }
        }

        $this->info("Filtrando desde: $desdeFechaFinal");

        $currentDate = Carbon::parse($desdeFechaFinal);
        $endDate = Carbon::now();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->toDateString();
            $this->info("  -> Procesando fecha: $dateStr");

            DB::connection('tenant')->transaction(function () use ($dateStr) {
                $records = DB::connection('mysql_dev')->table('pmlMda')
                    ->where('Fecha', $dateStr)
                    ->get();

                if ($records->isNotEmpty()) {
                    $chunks = $records->chunk(1000);
                    foreach ($chunks as $chunk) {
                        $data = $chunk->map(function ($record) {
                            return [
                                'proceso' => strtoupper($record->Proceso),
                                'sistema' => strtoupper($record->Sistema),
                                'nodoP' => strtoupper($record->Clv_Nodo),
                                'fecha' => $record->Fecha,
                                'hora' => $record->Hora,
                                'PML' => $record->PML,
                                'componenteEnergia' => $record->PML_ENE,
                                'componentePerdidas' => $record->PML_PER,
                                'componenteCongestion' => $record->PML_CNG,
                            ];
                        })->unique(function ($item) {
                            return $item['sistema'] . '|' . $item['nodoP'] . '|' . $item['fecha'] . '|' . $item['hora'];
                        })->toArray();

                        DB::connection('tenant')->table('preciosPML_MDA')->upsert($data, ['sistema', 'nodoP', 'fecha', 'hora'], [
                            'proceso', 'PML', 'componenteEnergia', 'componentePerdidas', 'componenteCongestion'
                        ]);
                    }
                }
            });

            $currentDate->addDay();
        }
    }

    private function syncPmlMtr($desdeFecha)
    {
        $this->info("Sincronizando preciosPML_MTR (desde pmlMtr) desde $desdeFecha...");

        $maxFecha = DB::connection('tenant')->table('preciosPML_MTR')->max('fecha');
        $desdeFechaFinal = $desdeFecha;
        if ($maxFecha) {
            $bufferFecha = Carbon::parse($maxFecha)->subDays(3)->toDateString();
            if ($bufferFecha > $desdeFecha) {
                $desdeFechaFinal = $bufferFecha;
            }
        }

        $this->info("Filtrando desde: $desdeFechaFinal");

        $currentDate = Carbon::parse($desdeFechaFinal);
        $endDate = Carbon::now();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->toDateString();
            $this->info("  -> Procesando fecha: $dateStr");

            DB::connection('tenant')->transaction(function () use ($dateStr) {
                $records = DB::connection('mysql_dev')->table('pmlMtr')
                    ->where('Fecha', $dateStr)
                    ->get();

                if ($records->isNotEmpty()) {
                    $chunks = $records->chunk(1000);
                    foreach ($chunks as $chunk) {
                        $data = $chunk->map(function ($record) {
                            return [
                                'proceso' => strtoupper($record->Proceso),
                                'sistema' => strtoupper($record->Sistema),
                                'nodoP' => strtoupper($record->Clv_Nodo),
                                'fecha' => $record->Fecha,
                                'hora' => $record->Hora,
                                'PML' => $record->PML,
                                'componenteEnergia' => $record->PML_ENE,
                                'componentePerdidas' => $record->PML_PER,
                                'componenteCongestion' => $record->PML_CNG,
                            ];
                        })->unique(function ($item) {
                            return $item['sistema'] . '|' . $item['nodoP'] . '|' . $item['fecha'] . '|' . $item['hora'];
                        })->toArray();

                        DB::connection('tenant')->table('preciosPML_MTR')->upsert($data, ['sistema', 'nodoP', 'fecha', 'hora'], [
                            'proceso', 'PML', 'componenteEnergia', 'componentePerdidas', 'componenteCongestion'
                        ]);
                    }
                }
            });

            $currentDate->addDay();
        }
    }

    private function syncPndMda($desdeFecha)
    {
        $this->info("Sincronizando preciosPND_MDA (desde precioEnergiaNodoDistribuidoMda) desde $desdeFecha...");

        $maxFecha = DB::connection('tenant')->table('preciosPND_MDA')->max('fecha');
        $desdeFechaFinal = $desdeFecha;
        if ($maxFecha) {
            $bufferFecha = Carbon::parse($maxFecha)->subDays(3)->toDateString();
            if ($bufferFecha > $desdeFecha) {
                $desdeFechaFinal = $bufferFecha;
            }
        }

        $this->info("Filtrando desde: $desdeFechaFinal");

        $currentDate = Carbon::parse($desdeFechaFinal);
        $endDate = Carbon::now();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->toDateString();
            $this->info("  -> Procesando fecha: $dateStr");

            DB::connection('tenant')->transaction(function () use ($dateStr) {
                $records = DB::connection('mysql_dev')->table('precioEnergiaNodoDistribuidoMda')
                    ->where('Fecha', $dateStr)
                    ->get();

                if ($records->isNotEmpty()) {
                    $chunks = $records->chunk(1000);
                    foreach ($chunks as $chunk) {
                        $data = $chunk->map(function ($record) {
                            return [
                                'proceso' => strtoupper($record->Proceso),
                                'sistema' => strtoupper($record->Sistema),
                                'zonaCarga' => strtoupper($record->ZonaCarga),
                                'fecha' => $record->Fecha,
                                'hora' => $record->Hora,
                                'precioZonal' => $record->Precio_Zonal,
                                'componenteEnergia' => $record->Componente_Energia,
                                'componentePerdida' => $record->Componente_Perdida,
                                'componenteCongestion' => $record->Componente_Congestion,
                            ];
                        })->unique(function ($item) {
                            return $item['sistema'] . '|' . $item['zonaCarga'] . '|' . $item['fecha'] . '|' . $item['hora'];
                        })->toArray();

                        DB::connection('tenant')->table('preciosPND_MDA')->upsert($data, ['sistema', 'zonaCarga', 'fecha', 'hora'], [
                            'proceso', 'precioZonal', 'componenteEnergia', 'componentePerdida', 'componenteCongestion'
                        ]);
                    }
                }
            });

            $currentDate->addDay();
        }
    }

    private function syncPndMtr($desdeFecha)
    {
        $this->info("Sincronizando preciosPND_MTR (desde precioEnergiaNodoDistribuidoMtr) desde $desdeFecha...");

        $maxFecha = DB::connection('tenant')->table('preciosPND_MTR')->max('fecha');
        $desdeFechaFinal = $desdeFecha;
        if ($maxFecha) {
            $bufferFecha = Carbon::parse($maxFecha)->subDays(3)->toDateString();
            if ($bufferFecha > $desdeFecha) {
                $desdeFechaFinal = $bufferFecha;
            }
        }

        $this->info("Filtrando desde: $desdeFechaFinal");

        $currentDate = Carbon::parse($desdeFechaFinal);
        $endDate = Carbon::now();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->toDateString();
            $this->info("  -> Procesando fecha: $dateStr");

            DB::connection('tenant')->transaction(function () use ($dateStr) {
                $records = DB::connection('mysql_dev')->table('precioEnergiaNodoDistribuidoMtr')
                    ->where('Fecha', $dateStr)
                    ->get();

                if ($records->isNotEmpty()) {
                    $chunks = $records->chunk(1000);
                    foreach ($chunks as $chunk) {
                        $data = $chunk->map(function ($record) {
                            return [
                                'proceso' => strtoupper($record->Proceso),
                                'sistema' => strtoupper($record->Sistema),
                                'zonaCarga' => strtoupper($record->ZonaCarga),
                                'fecha' => $record->Fecha,
                                'hora' => $record->Hora,
                                'precioZonal' => $record->Precio_Zonal,
                                'componenteEnergia' => $record->Componente_Energia,
                                'componentePerdida' => $record->Componente_Perdida,
                                'componenteCongestion' => $record->Componente_Congestion,
                            ];
                        })->unique(function ($item) {
                            return $item['sistema'] . '|' . $item['zonaCarga'] . '|' . $item['fecha'] . '|' . $item['hora'];
                        })->toArray();

                        DB::connection('tenant')->table('preciosPND_MTR')->upsert($data, ['sistema', 'zonaCarga', 'fecha', 'hora'], [
                            'proceso', 'precioZonal', 'componenteEnergia', 'componentePerdida', 'componenteCongestion'
                        ]);
                    }
                }
            });

            $currentDate->addDay();
        }
    }

    private function syncTipoCambioLiquidacion($desdeFecha)
    {
        $this->info("Sincronizando tipoCambioLiquidacion (desde tipoDeCambioLiquidacion) desde $desdeFecha...");

        DB::connection('mysql_dev')->table('tipoDeCambioLiquidacion')
            ->where('Period', '>=', $desdeFecha)
            ->orderBy('Id', 'asc')
            ->chunk(500, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'Id' => $record->Id,
                        'nombreDeSerie' => $record->Serie_Name,
                        'auditDate' => $record->auditDate,
                        'fecha' => $record->Period,
                        'formated_date' => $record->formated_date,
                        'Year' => $record->Year,
                        'Frequency' => $record->Frequency,
                        'tipoCambio' => $record->Value,
                        'unidades' => $record->Units,
                        'UpdateDate' => $record->UpdateDate,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('tipoCambioLiquidacion')->upsert($data, ['Id'], [
                        'nombreDeSerie', 'auditDate', 'fecha', 'formated_date', 'Year', 'Frequency', 'tipoCambio', 'unidades', 'UpdateDate'
                    ]);
                }
            });
    }

    private function syncTipoCambioFix($desdeFecha)
    {
        $this->info("Sincronizando tipoCambioFIX (desde tipocambio_fix) desde $desdeFecha...");

        DB::connection('mysql_dev')->table('tipocambio_fix')
            ->where('Period', '>=', $desdeFecha)
            ->orderBy('Id', 'asc')
            ->chunk(500, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'Id' => $record->Id,
                        'nombreDeSerie' => $record->Serie_Name,
                        'auditDate' => $record->auditDate,
                        'fecha' => $record->Period,
                        'formated_date' => $record->formated_date,
                        'Year' => $record->Year,
                        'Frequency' => $record->Frequency,
                        'originalValue' => $record->originalValue,
                        'tipoCambio' => $record->Value,
                        'unidades' => $record->Units,
                        'UpdateDate' => $record->UpdateDate,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('tipoCambioFIX')->upsert($data, ['Id'], [
                        'nombreDeSerie', 'auditDate', 'fecha', 'formated_date', 'Year', 'Frequency', 'originalValue', 'tipoCambio', 'unidades', 'UpdateDate'
                    ]);
                }
            });
    }

    private function syncEnergiaTecnologia($desdeFecha)
    {
        $this->info("Sincronizando EnergiaGeneradaporTipodeTecnologia (desde energiaGeneradaTipoTecnologia) desde $desdeFecha...");

        DB::connection('mysql_dev')->table('energiaGeneradaTipoTecnologia')
            ->where('Dia', '>=', $desdeFecha)
            ->orderBy('Dia', 'asc')
            ->orderBy('Hora', 'asc')
            ->chunk(500, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'Sistema' => $record->Sistema,
                        'Fecha' => $record->Dia,
                        'Hora' => $record->Hora,
                        'Eolica' => $record->Eolica,
                        'Fotovoltaica' => $record->Fotovoltaica,
                        'Biomasa' => $record->Biomasa,
                        'Carboelectrica' => $record->Carboelectrica,
                        'CicloCombinado' => $record->CicloCombinado,
                        'CombustionInterna' => $record->CombustionInterna,
                        'Geotermoelectrica' => $record->Geotermoelectrica,
                        'Hidroelectrica' => $record->Hidroelectrica,
                        'Nucleoelectrica' => $record->Nucleoelectrica,
                        'TermicaConvencional' => $record->TermicaConvencional,
                        'TurboGas' => $record->TurboGas,
                        'totalOfEnergies' => $record->totalOfEnergies,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('EnergiaGeneradaporTipodeTecnologia')->upsert($data, ['Sistema', 'Fecha', 'Hora'], [
                        'Eolica', 'Fotovoltaica', 'Biomasa', 'Carboelectrica', 'CicloCombinado', 'CombustionInterna', 'Geotermoelectrica', 'Hidroelectrica', 'Nucleoelectrica', 'TermicaConvencional', 'TurboGas', 'totalOfEnergies'
                    ]);
                }
            });
    }

    private function syncErcot($desdeFecha)
    {
        $this->info("Sincronizando preciosSPOTERCOT (desde ercot) desde $desdeFecha...");

        DB::connection('mysql_dev')->table('ercot')
            ->where('Fecha', '>=', $desdeFecha)
            ->orderBy('Fecha', 'asc')
            ->orderBy('Hora', 'asc')
            ->chunk(500, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'Fecha' => $record->Fecha,
                        'Hora' => $record->Hora,
                        'Enlace' => $record->Enlace,
                        'NodoP' => $record->NodoP,
                        'NodoPNombre' => $record->NodoPNombre,
                        'PrecioNodoP' => $record->PrecioNodoP,
                        'exchangeRate' => $record->exchangeRate,
                        'PrecioNodoPDollars' => $record->PrecioNodoPDollars,
                        'PrecioEnlace' => $record->PrecioEnlace,
                        'Diferencial' => $record->Diferencial,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('preciosSPOTERCOT')->upsert($data, ['Enlace', 'Fecha', 'Hora'], [
                        'id', 'NodoP', 'NodoPNombre', 'PrecioNodoP', 'exchangeRate', 'PrecioNodoPDollars', 'PrecioEnlace', 'Diferencial'
                    ]);
                }
            });
    }



    private function syncConceptosCalculoContrato(array $rpus, $desdeFecha)
    {
        $this->info("Sincronizando conceptosDeCalculoDeContrato (desde reporteDeConceptosDeCaluloDeContrato) desde $desdeFecha...");

        $query = DB::connection('mysql_dev')->table('reporteDeConceptosDeCaluloDeContrato')
            ->where('fechaInicio', '>=', $desdeFecha);

        if (!empty($rpus)) {
            $query->where(function ($q) use ($rpus) {
                foreach ($rpus as $rpu) {
                    $q->orWhere('centrosDeCarga', 'like', "%{$rpu}%");
                }
            });
        } else {
            $this->warn("No se encontraron RPUs en el destino. Omitiendo registros para conceptosDeCalculoDeContrato.");
            return;
        }

        $query->orderBy('contrato', 'asc')
            ->orderBy('idDeCalculo', 'asc')
            ->chunk(1000, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'contrato' => $record->contrato,
                        'idDeCalculo' => $record->idDeCalculo,
                        'fechaInicio' => $record->fechaInicio,
                        'fechaFin' => $record->fechaFin,
                        'centrosDeCarga' => $record->centrosDeCarga,
                        'centralesElectricas' => $record->centralesElectricas,
                        'NombreDelCalculo' => $record->NombreDelCalculo,
                        'CategoriaOSeccion' => $record->CategoriaOSeccion,
                        'InstrumentoOProducto' => $record->InstrumentoOProducto,
                        'componentId' => $record->componentId,
                        'componentType' => $record->componentType,
                        'UnidadComponente' => $record->UnidadComponente,
                        'Cantidad' => $record->Cantidad,
                        'Precio' => $record->Precio,
                        'UnidadFact' => $record->UnidadFact,
                        'Monto' => $record->Monto,
                        'Divisa' => $record->Divisa,
                        'IVA' => $record->IVA,
                        'created_at' => $record->created_at,
                        'updated_at' => $record->updated_at,
                        'report_created_at' => $record->report_created_at,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('conceptosDeCalculoDeContrato')->upsert($data, 
                        ['contrato', 'idDeCalculo', 'fechaInicio', 'fechaFin', 'CategoriaOSeccion', 'componentId'], 
                        [
                            'centrosDeCarga', 'centralesElectricas', 'NombreDelCalculo', 'InstrumentoOProducto', 
                            'componentType', 'UnidadComponente', 'Cantidad', 'Precio', 'UnidadFact', 'Monto', 
                            'Divisa', 'IVA', 'created_at', 'updated_at', 'report_created_at'
                        ]
                    );
                }
            });
    }

    private function syncVariablesContrato(array $rpus, $desdeFecha)
    {
        $this->info("Sincronizando variablesDeContrato (desde reporteDeVariablesDeCalculoDeContrato) desde $desdeFecha...");

        $query = DB::connection('mysql_dev')->table('reporteDeVariablesDeCalculoDeContrato')
            ->where('fechaInicio', '>=', $desdeFecha);

        if (!empty($rpus)) {
            $query->where(function ($q) use ($rpus) {
                foreach ($rpus as $rpu) {
                    $q->orWhere('centrosDeCarga', 'like', "%{$rpu}%");
                }
            });
        } else {
            $this->warn("No se encontraron RPUs en el destino. Omitiendo registros para variablesDeContrato.");
            return;
        }

        $query->orderBy('contrato', 'asc')
            ->orderBy('idDeCalculo', 'asc')
            ->chunk(1000, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'contrato' => $record->contrato,
                        'idDeCalculo' => $record->idDeCalculo,
                        'centrosDeCarga' => $record->centrosDeCarga,
                        'centralesElectricas' => $record->centralesElectricas,
                        'nombreDeVariable' => $record->nombreDeVariable,
                        'idDeVariable' => $record->idDeVariable,
                        'fechaInicio' => $record->fechaInicio,
                        'valor' => $record->valor,
                        'unidades' => $record->unidades,
                        'created_at' => $record->created_at,
                        'updated_at' => $record->updated_at,
                        'report_created_at' => $record->report_created_at,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('variablesDeContrato')->upsert($data, 
                        ['contrato', 'idDeCalculo', 'idDeVariable'], 
                        [
                            'centrosDeCarga', 'centralesElectricas', 'nombreDeVariable', 'fechaInicio', 
                            'valor', 'unidades', 'created_at', 'updated_at', 'report_created_at'
                        ]
                    );
                }
            });
    }



    private function syncCatalogosDeNodosP()
    {
        $this->info("Sincronizando catalogosDeNodosP (desde nodosPAccumulative)...");

        DB::connection('mysql_dev')->table('nodosPAccumulative')
            ->orderBy('Sistema', 'asc')
            ->orderBy('Clave', 'asc')
            ->orderBy('FechaDelArchivo', 'asc')
            ->chunk(500, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'Sistema' => $record->Sistema,
                        'CentroControlRegional' => $record->CentroControlRegional,
                        'ZonaCarga' => $record->ZonaCarga,
                        'Clave' => $record->Clave,
                        'NombreNodo' => $record->NombreNodo,
                        'NivelTension' => $record->NivelTension,
                        'TipoCargaDM' => $record->TipoCargaDM,
                        'TipoCargaIM' => $record->TipoCargaIM,
                        'TipoGeneracionDM' => $record->TipoGeneracionDM,
                        'TipoGeneracionIM' => $record->TipoGeneracionIM,
                        'ZonaOpeTrans' => $record->ZonaOpeTrans,
                        'GerenciaRegTrans' => $record->GerenciaRegTrans,
                        'ZonaDistribucion' => $record->ZonaDistribucion,
                        'GerenciaDivDist' => $record->GerenciaDivDist,
                        'ClaveEntidadInegi' => $record->ClaveEntidadInegi,
                        'EntidadInegi' => $record->EntidadInegi,
                        'ClaveMunicipio' => $record->ClaveMunicipio,
                        'Municipio' => $record->Municipio,
                        'RegionTransmision' => $record->RegionTransmision,
                        'FechaDelArchivo' => $record->FechaDelArchivo,
                        'spanishDate' => $record->spanishDate,
                        'updatedAt' => $record->updatedAt,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('catalogosDeNodosP')->upsert($data, 
                        ['Sistema', 'Clave', 'FechaDelArchivo'], 
                        [
                            'CentroControlRegional', 'ZonaCarga', 'NombreNodo', 'NivelTension', 
                            'TipoCargaDM', 'TipoCargaIM', 'TipoGeneracionDM', 'TipoGeneracionIM', 
                            'ZonaOpeTrans', 'GerenciaRegTrans', 'ZonaDistribucion', 'GerenciaDivDist', 
                            'ClaveEntidadInegi', 'EntidadInegi', 'ClaveMunicipio', 'Municipio', 
                            'RegionTransmision', 'spanishDate', 'updatedAt'
                        ]
                    );
                }
            });
    }

    private function syncDiccionarioFoliosLiquidacion()
    {
        $this->info("Sincronizando diccionarioFoliosLiquidacion (desde ecd_conceptos)...");

        DB::connection('mysql_dev')->table('ecd_conceptos')
            ->orderBy('folio', 'asc')
            ->chunk(1000, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'folio' => $record->folio,
                        'concepto' => $record->concepto,
                        'mercado' => $record->mercado,
                        'clasificacion' => $record->clasificacion,
                        'descripcion' => $record->descripcion,
                        'grupo' => $record->grupo,
                        'tipo_PM' => $record->tipo_PM,
                    ];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('diccionarioFoliosLiquidacion')->upsert($data, 
                        ['folio'], 
                        [
                            'concepto', 'mercado', 'clasificacion', 'descripcion', 'grupo', 'tipo_PM'
                        ]
                    );
                }
            });
    }

    private function syncEnergiaAsignadaZonadeCarga($desdeFecha)
    {
        $this->info("Sincronizando energiaAsignadaZonadeCarga (desde energiaasignadazonascarga_historico) desde $desdeFecha...");

        $maxFecha = DB::connection('tenant')->table('energiaAsignadaZonadeCarga')->max('fecha');
        $desdeFechaFinal = $desdeFecha;
        if ($maxFecha) {
            $bufferFecha = Carbon::parse($maxFecha)->subDays(3)->toDateString();
            if ($bufferFecha > $desdeFecha) {
                $desdeFechaFinal = $bufferFecha;
            }
        }

        $this->info("Filtrando desde: $desdeFechaFinal");

        DB::connection('mysql_dev')->table('energiaasignadazonascarga_historico')
            ->where('Fecha', '>=', $desdeFechaFinal)
            ->orderBy('Fecha', 'asc')
            ->orderBy('Hora', 'asc')
            ->chunk(500, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'proceso' => strtoupper($record->Proceso),
                        'sistema' => strtoupper($record->Sistema),
                        'zonadeCarga' => strtoupper($record->Zona_Carga),
                        'fecha' => $record->Fecha,
                        'formated_date' => $record->formated_date,
                        'hora' => $record->Hora,
                        'demandaMdoNodales' => $record->Demanda_Mdo_Nodales,
                        'demandaPmlZonales' => $record->Demanda_Pml_Zonales,
                        'totalCargas' => $record->Total_Cargas,
                    ];
                })->unique(function ($item) {
                    return $item['proceso'] . '|' . $item['sistema'] . '|' . $item['zonadeCarga'] . '|' . $item['fecha'] . '|' . $item['hora'];
                })->toArray();

                if (!empty($data)) {
                    DB::connection('tenant')->table('energiaAsignadaZonadeCarga')->upsert($data, 
                        ['proceso', 'sistema', 'zonadeCarga', 'fecha', 'hora'], 
                        ['formated_date', 'demandaMdoNodales', 'demandaPmlZonales', 'totalCargas']
                    );
                }
            });
    }

    private function syncOfertasDeCompraPorTipo($desdeFecha)
    {
        $this->info("Sincronizando ofertasDeCompraPorTipo desde $desdeFecha...");

        $maxFecha = DB::connection('tenant')->table('ofertasDeCompraPorTipo')->max('fecha');
        $desdeFechaFinal = $desdeFecha;
        if ($maxFecha) {
            $bufferFecha = Carbon::parse($maxFecha)->subDays(3)->toDateString();
            if ($bufferFecha > $desdeFecha) {
                $desdeFechaFinal = $bufferFecha;
            }
        }

        $this->info("Filtrando desde: $desdeFechaFinal");
        $hoy = Carbon::now()->toDateString();

        $sql = "
            SELECT
                dates.Fecha,
                dates.Sistema,
                COALESCE(ofertaCompra.ofertaCompraValue, 0) AS ofertaCompraValue,
                COALESCE(ofertasDelGIProgramaDeConsumo.ofertasDelGIProgramaDeConsumoValue, 0) AS ofertasDelGIProgramaDeConsumoValue,
                COALESCE(ofertasDeImportacion.ImportacionValue, 0) AS ImportacionValue
            FROM (
                SELECT DISTINCT Fecha, Sistema FROM (
                    SELECT Fecha, Sistema FROM ofertaCompra WHERE Fecha >= ? AND Fecha <= ?
                    UNION
                    SELECT FechaOperacion AS Fecha, Sistema FROM ofertasDelGIProgramaDeConsumo WHERE FechaOperacion >= ? AND FechaOperacion <= ?
                    UNION
                    SELECT FechaOperacion AS Fecha, Sistema FROM ofertasDeImportacion WHERE FechaOperacion >= ? AND FechaOperacion <= ?
                ) AS all_dates
            ) AS dates
            LEFT JOIN (
                SELECT Fecha, Sistema, SUM(DemandaFija) AS ofertaCompraValue
                FROM ofertaCompra
                WHERE Fecha >= ? AND Fecha <= ?
                GROUP BY Fecha, Sistema
            ) AS ofertaCompra ON dates.Fecha = ofertaCompra.Fecha AND dates.Sistema = ofertaCompra.Sistema
            LEFT JOIN (
                SELECT FechaOperacion AS Fecha, Sistema, SUM(PotenciaMedia) AS ofertasDelGIProgramaDeConsumoValue
                FROM ofertasDelGIProgramaDeConsumo
                WHERE FechaOperacion >= ? AND FechaOperacion <= ?
                GROUP BY FechaOperacion, Sistema
            ) AS ofertasDelGIProgramaDeConsumo ON dates.Fecha = ofertasDelGIProgramaDeConsumo.Fecha AND dates.Sistema = ofertasDelGIProgramaDeConsumo.Sistema
            LEFT JOIN (
                SELECT FechaOperacion AS Fecha, Sistema, SUM(ImportacionFija) + SUM(BloquePotencia01) + SUM(BloquePotencia02) + SUM(BloquePotencia03) AS ImportacionValue
                FROM ofertasDeImportacion
                WHERE FechaOperacion >= ? AND FechaOperacion <= ?
                GROUP BY FechaOperacion, Sistema
            ) AS ofertasDeImportacion ON dates.Fecha = ofertasDeImportacion.Fecha AND dates.Sistema = ofertasDeImportacion.Sistema
        ";

        $bindings = [];
        for ($i = 0; $i < 6; $i++) {
            $bindings[] = $desdeFechaFinal;
            $bindings[] = $hoy;
        }

        $records = DB::connection('mysql_dev')->select($sql, $bindings);

        if (!empty($records)) {
            $data = collect($records)->map(function ($record) {
                return [
                    'fecha' => $record->Fecha,
                    'sistema' => strtoupper($record->Sistema),
                    'ofertaCompraValue' => $record->ofertaCompraValue,
                    'ofertasDelGIProgramaDeConsumoValue' => $record->ofertasDelGIProgramaDeConsumoValue,
                    'ImportacionValue' => $record->ImportacionValue,
                ];
            })->unique(function ($item) {
                return $item['fecha'] . '|' . $item['sistema'];
            })->toArray();

            DB::connection('tenant')->table('ofertasDeCompraPorTipo')->upsert($data, ['fecha', 'sistema'], [
                'ofertaCompraValue', 'ofertasDelGIProgramaDeConsumoValue', 'ImportacionValue'
            ]);
        }
    }

    private function syncOfertasDeVentaPorTipo($desdeFecha)
    {
        $this->info("Sincronizando ofertasDeVentaPorTipo desde $desdeFecha...");

        $maxFecha = DB::connection('tenant')->table('ofertasDeVentaPorTipo')->max('fecha');
        $desdeFechaFinal = $desdeFecha;
        if ($maxFecha) {
            $bufferFecha = Carbon::parse($maxFecha)->subDays(3)->toDateString();
            if ($bufferFecha > $desdeFecha) {
                $desdeFechaFinal = $bufferFecha;
            }
        }

        $this->info("Filtrando desde: $desdeFechaFinal");
        $hoy = Carbon::now()->toDateString();

        $sql = "
            SELECT
                dates.Fecha,
                dates.Sistema,
                COALESCE(ofertaVentaTermica.ofertaVentaTermicaValue, 0) AS ofertaVentaTermicaValue,
                COALESCE(ofertaVentaHidroelectrica.ofertaVentaHidroelectricaValue, 0) AS ofertaVentaHidroelectricaValue,
                COALESCE(ofertaVentaRecursoIntermitenteDespachable.ofertaVentaRecursoIntermitenteDespachableValue, 0) AS ofertaVentaRecursoIntermitenteDespachableValue,
                COALESCE(ofertaVentaNoDespachable.ofertaVentaNoDespachableValue, 0) AS ofertaVentaNoDespachableValue,
                COALESCE(ofertasDelGIProgramaDeGeneracion.ofertasDelGIProgramaDeGeneracionValue, 0) AS ofertasDelGIProgramaDeGeneracionValue,
                COALESCE(ofertasDeExportacion.ofertasDeExportacionValue, 0) AS ofertasDeExportacionValue
            FROM (
                SELECT DISTINCT Fecha, Sistema FROM (
                    SELECT Fecha, Sistema FROM ofertaVentaTermica WHERE Fecha >= ? AND Fecha <= ?
                    UNION
                    SELECT Fecha, Sistema FROM ofertaVentaHidroelectrica WHERE Fecha >= ? AND Fecha <= ?
                    UNION
                    SELECT Fecha, Sistema FROM ofertaVentaRecursoIntermitenteDespachable WHERE Fecha >= ? AND Fecha <= ?
                    UNION
                    SELECT Fecha, Sistema FROM ofertaVentaNoDespachable WHERE Fecha >= ? AND Fecha <= ?
                    UNION
                    SELECT FechaOperacion AS Fecha, Sistema FROM ofertasDelGIProgramaDeGeneracion WHERE FechaOperacion >= ? AND FechaOperacion <= ?
                    UNION
                    SELECT FechaOperacion AS Fecha, Sistema FROM ofertasDeExportacion WHERE FechaOperacion >= ? AND FechaOperacion <= ?
                ) AS all_dates
            ) AS dates
            LEFT JOIN (
                SELECT Fecha, Sistema, SUM(LimiteDespacho_Max) AS ofertaVentaTermicaValue
                FROM ofertaVentaTermica
                WHERE Fecha >= ? AND Fecha <= ?
                GROUP BY Fecha, Sistema
            ) AS ofertaVentaTermica ON dates.Fecha = ofertaVentaTermica.Fecha AND dates.Sistema = ofertaVentaTermica.Sistema
            LEFT JOIN (
                SELECT Fecha, Sistema, SUM(LimiteDespacho_Max) AS ofertaVentaHidroelectricaValue
                FROM ofertaVentaHidroelectrica
                WHERE Fecha >= ? AND Fecha <= ?
                GROUP BY Fecha, Sistema
            ) AS ofertaVentaHidroelectrica ON dates.Fecha = ofertaVentaHidroelectrica.Fecha AND dates.Sistema = ofertaVentaHidroelectrica.Sistema
            LEFT JOIN (
                SELECT Fecha, Sistema, SUM(PronosticoMW) AS ofertaVentaRecursoIntermitenteDespachableValue
                FROM ofertaVentaRecursoIntermitenteDespachable
                WHERE Fecha >= ? AND Fecha <= ?
                GROUP BY Fecha, Sistema
            ) AS ofertaVentaRecursoIntermitenteDespachable ON dates.Fecha = ofertaVentaRecursoIntermitenteDespachable.Fecha AND dates.Sistema = ofertaVentaRecursoIntermitenteDespachable.Sistema
            LEFT JOIN (
                SELECT Fecha, Sistema, SUM(PotenciaMedia) AS ofertaVentaNoDespachableValue
                FROM ofertaVentaNoDespachable
                WHERE Fecha >= ? AND Fecha <= ?
                GROUP BY Fecha, Sistema
            ) AS ofertaVentaNoDespachable ON dates.Fecha = ofertaVentaNoDespachable.Fecha AND dates.Sistema = ofertaVentaNoDespachable.Sistema
            LEFT JOIN (
                SELECT FechaOperacion AS Fecha, Sistema, SUM(PotenciaMedia) AS ofertasDelGIProgramaDeGeneracionValue
                FROM ofertasDelGIProgramaDeGeneracion
                WHERE FechaOperacion >= ? AND FechaOperacion <= ?
                GROUP BY FechaOperacion, Sistema
            ) AS ofertasDelGIProgramaDeGeneracion ON dates.Fecha = ofertasDelGIProgramaDeGeneracion.Fecha AND dates.Sistema = ofertasDelGIProgramaDeGeneracion.Sistema
            LEFT JOIN (
                SELECT FechaOperacion AS Fecha, Sistema, SUM(ExportacionFija) + SUM(BloquePotencia01) + SUM(BloquePotencia02) + SUM(BloquePotencia03) AS ofertasDeExportacionValue
                FROM ofertasDeExportacion
                WHERE FechaOperacion >= ? AND FechaOperacion <= ?
                GROUP BY FechaOperacion, Sistema
            ) AS ofertasDeExportacion ON dates.Fecha = ofertasDeExportacion.Fecha AND dates.Sistema = ofertasDeExportacion.Sistema
        ";

        $bindings = [];
        for ($i = 0; $i < 12; $i++) {
            $bindings[] = $desdeFechaFinal;
            $bindings[] = $hoy;
        }

        $records = DB::connection('mysql_dev')->select($sql, $bindings);

        if (!empty($records)) {
            $data = collect($records)->map(function ($record) {
                return [
                    'fecha' => $record->Fecha,
                    'sistema' => strtoupper($record->Sistema),
                    'ofertaVentaTermicaValue' => $record->ofertaVentaTermicaValue,
                    'ofertaVentaHidroelectricaValue' => $record->ofertaVentaHidroelectricaValue,
                    'ofertaVentaRecursoIntermitenteDespachableValue' => $record->ofertaVentaRecursoIntermitenteDespachableValue,
                    'ofertaVentaNoDespachableValue' => $record->ofertaVentaNoDespachableValue,
                    'ofertasDelGIProgramaDeGeneracionValue' => $record->ofertasDelGIProgramaDeGeneracionValue,
                    'ofertasDeExportacionValue' => $record->ofertasDeExportacionValue,
                ];
            })->unique(function ($item) {
                return $item['fecha'] . '|' . $item['sistema'];
            })->toArray();

            DB::connection('tenant')->table('ofertasDeVentaPorTipo')->upsert($data, ['fecha', 'sistema'], [
                'ofertaVentaTermicaValue', 'ofertaVentaHidroelectricaValue', 'ofertaVentaRecursoIntermitenteDespachableValue',
                'ofertaVentaNoDespachableValue', 'ofertasDelGIProgramaDeGeneracionValue', 'ofertasDeExportacionValue'
            ]);
        }
    }

    private function cleanupOldRecords($limitDate)
    {
        $this->info("Limpiando registros transaccionales de mercado/contratos más antiguos de $limitDate en el destino...");

        DB::connection('tenant')->table('preciosPML_MDA')->where('fecha', '<', $limitDate)->delete();
        DB::connection('tenant')->table('preciosPML_MTR')->where('fecha', '<', $limitDate)->delete();
        DB::connection('tenant')->table('preciosPND_MDA')->where('fecha', '<', $limitDate)->delete();
        DB::connection('tenant')->table('preciosPND_MTR')->where('fecha', '<', $limitDate)->delete();
        DB::connection('tenant')->table('energiaAsignadaZonadeCarga')->where('fecha', '<', $limitDate)->delete();
        DB::connection('tenant')->table('ofertasDeCompraPorTipo')->where('fecha', '<', $limitDate)->delete();
        DB::connection('tenant')->table('ofertasDeVentaPorTipo')->where('fecha', '<', $limitDate)->delete();
        DB::connection('tenant')->table('tipoCambioLiquidacion')->where('fecha', '<', $limitDate)->delete();
        DB::connection('tenant')->table('tipoCambioFIX')->where('fecha', '<', $limitDate)->delete();
        DB::connection('tenant')->table('EnergiaGeneradaporTipodeTecnologia')->where('Fecha', '<', $limitDate)->delete();
        DB::connection('tenant')->table('preciosSPOTERCOT')->where('Fecha', '<', $limitDate)->delete();
        DB::connection('tenant')->table('conceptosDeCalculoDeContrato')->where('fechaInicio', '<', $limitDate)->delete();
        DB::connection('tenant')->table('variablesDeContrato')->where('fechaInicio', '<', $limitDate)->delete();
    }
}
