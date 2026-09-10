<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCloudCatalogData extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sync:cloud-catalog-data {team?}';

    /**
     * @var string
     */
    protected $description = 'Sincroniza datos de catálogos (contrapartes y listadoContratos) desde la BD origen en la nube (enegence_cloud) hacia la BD tenant del equipo';

    public function handle()
    {
        try {
            if (!$this->setupCloudConnection()) {
                $this->error("No se pudo configurar la conexión de origen (mysql_cloud) a la base de datos 'enegence_cloud'.");
                return Command::FAILURE;
            }

            $team = $this->argument('team');

            if ($team) {
                $this->info("Iniciando sincronización de catálogos desde la nube para el equipo específico: $team...");
                $cacheKey = "sync_running:sync:cloud-catalog-data:{$team}";
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, 3600);
                try {
                    if ($this->setupDynamicConnection($team)) {
                        $this->executeSyncForTeam($team);
                    } else {
                        $this->error("No se pudo configurar la conexión destino tenant para el equipo $team (¿Existe en team_databases y está activo?).");
                        return Command::FAILURE;
                    }
                } finally {
                    \Illuminate\Support\Facades\Cache::forget($cacheKey);
                }
            } else {
                $this->info("Iniciando sincronización de catálogos desde la nube en bucle para todos los equipos configurados...");
                $activeTeams = \App\Models\Team::where('active', true)->where('sync_cloud_catalog_data', true)->get();

                if ($activeTeams->isEmpty()) {
                    $this->info("No hay equipos activos con la sincronización de catálogos en la nube automática habilitada.");
                    return Command::SUCCESS;
                }

                foreach ($activeTeams as $t) {
                    $teamId = $t->team_id;
                    $this->info("-------------------------------------------------------------");
                    $this->info("Sincronizando catálogos en la nube para equipo: $teamId...");

                    $cacheKey = "sync_running:sync:cloud-catalog-data:{$teamId}";
                    \Illuminate\Support\Facades\Cache::put($cacheKey, true, 3600);
                    try {
                        if ($this->setupDynamicConnection($teamId)) {
                            $this->executeSyncForTeam($teamId);
                        } else {
                            $this->error("No se pudo configurar la conexión destino tenant para el equipo $teamId.");
                        }
                    } finally {
                        \Illuminate\Support\Facades\Cache::forget($cacheKey);
                    }
                }
            }

            $this->info("Sincronización finalizada correctamente.");
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
                    'text' => "🚨 *ERROR FATAL EN COMANDO (Sync)*\n*Comando:* `sync:cloud-catalog-data`\n*Error:* " . $th->getMessage() . "\n*Archivo:* " . basename($th->getFile()) . " línea " . $th->getLine() . "\n*Fecha:* " . $fecha
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

    private function executeSyncForTeam($team)
    {
        $this->syncContrapartes($team);
        $this->syncListadoContratos($team);
    }

    private function setupCloudConnection()
    {
        $primaryConfig = config('database.connections.mysql_primary');
        if (!$primaryConfig) {
            return false;
        }

        $cloudConfig = $primaryConfig;
        $cloudConfig['database'] = 'enegence_cloud';

        config(['database.connections.mysql_cloud' => $cloudConfig]);
        DB::purge('mysql_cloud');
        DB::reconnect('mysql_cloud');

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

    private function syncContrapartes($team)
    {
        $this->info("Sincronizando contrapartes (desde invoiceRecipients) para el equipo $team...");

        DB::connection('mysql_cloud')->table('invoiceRecipients')
            ->where('teamId', $team)
            ->orderBy('id', 'asc')
            ->chunk(500, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'teamId' => $record->teamId,
                        'shortname' => null, 
                        'alias' => $record->alias,
                        'name' => $record->name,
                        'rfc' => $record->rfc,
                        'country' => $record->country,
                        'contractNumber' => $record->contractNumber,
                        'contractNumberId' => $record->contractNumberId,
                        'nameOptional' => $record->nameOptional,
                        'lastName' => $record->lastName,
                        'email' => $record->email ? substr($record->email, 0, 100) : null, 
                        'phone' => $record->phone,
                        'street' => $record->street,
                        'externalNumber' => $record->externalNumber,
                        'internalNumber' => $record->internalNumber,
                        'colony' => $record->colony,
                        'zipCode' => $record->zipCode,
                        'municipio' => $record->municipio,
                        'stateInput' => $record->stateInput,
                        'regimenFiscal' => $record->regimenFiscal,
                        'currency' => $record->currency,
                        'exchangeRateType' => $record->exchangeRateType,
                        'invoiceConcept' => $record->invoiceConcept,
                        'additionalText' => $record->additionalText,
                        'paymentMethod' => $record->paymentMethod,
                        'paymentForm' => $record->paymentForm,
                        'cdfiUse' => $record->cdfiUse,
                        'quantity' => $record->quantity,
                        'units' => $record->units,
                        'productCode' => $record->productCode,
                        'created_at' => $record->created_at,
                    ];
                })->toArray();

                if (!empty($data)) {
                    $keysToUpdate = array_keys(current($data));
                    $keysToUpdate = array_filter($keysToUpdate, function($k) { return $k !== 'id'; });

                    DB::connection('tenant')->table('contrapartes')->upsert($data, ['id'], array_values($keysToUpdate));
                }
            });
    }

    private function syncListadoContratos($team)
    {
        $this->info("Sincronizando listadoContratos (desde contracts) para el equipo $team...");

        DB::connection('mysql_cloud')->table('contracts')
            ->where('teamId', $team)
            ->orderBy('id', 'asc')
            ->chunk(500, function ($records) {
                $data = $records->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'teamId' => $record->teamId,
                        'contractNumber' => $record->contractNumber,
                        'contractNumberId' => $record->contractNumberId,
                        'name' => $record->name,
                        'content' => $record->content,
                        'version' => $record->version,
                        'isInvoiceable' => $record->isInvoiceable,
                        'isInvoiceForOneClient' => $record->isInvoiceForOneClient,
                        'invoiceLevel' => $record->invoiceLevel,
                        'invoiceable' => $record->invoiceable,
                        'clients' => $record->clients,
                        'CEclients' => $record->CEclients,
                        'centrosDeCarga' => $record->centrosDeCarga,
                        'centralesElectricas' => $record->centralesElectricas,
                        'contractVariables' => $record->contractVariables,
                        'primaryComponents' => $record->primaryComponents,
                        'equationComponents' => $record->equationComponents,
                        'algorithmComponents' => $record->algorithmComponents,
                        'contractVariablesDetails' => $record->contractVariablesDetails,
                        'primaryComponentsDetails' => $record->primaryComponentsDetails,
                        'equationComponentsDetails' => $record->equationComponentsDetails,
                        'algorithmComponentsDetails' => $record->algorithmComponentsDetails,
                        'created_at' => $record->created_at,
                        'status' => $record->status,
                        'substatus' => $record->substatus,
                        'contract_template' => $record->contract_template,
                        'contractTemplateVersion' => $record->contractTemplateVersion,
                        'vigenciaStartDate' => $record->vigenciaStartDate,
                        'vigenciaEndDate' => $record->vigenciaEndDate,
                        'isCalculationResultAMatrix' => $record->isCalculationResultAMatrix,
                        'isCalculationResultAMatrixCC' => $record->isCalculationResultAMatrixCC,
                        'isCalculationResutlMultidivisa' => $record->isCalculationResutlMultidivisa,
                        'currency' => $record->currency,
                        'exchangeRateType' => $record->exchangeRateType,
                        'periodoDeRenovacion' => $record->periodoDeRenovacion,
                        'vigenciaEndDateAlert' => $record->vigenciaEndDateAlert,
                        'compareCFE_SSB' => $record->compareCFE_SSB,
                        'demandaContratada' => $record->demandaContratada,
                        'energiaContratada' => $record->energiaContratada,
                        'multiClientByCentralElectrica' => $record->multiClientByCentralElectrica,
                        'cantidadPrincipal' => $record->cantidadPrincipal,
                        'grupoTarifario' => $record->grupoTarifario,
                        'division' => $record->division,
                        'unidadDeMedida' => $record->unidadDeMedida,
                        'tratamientoDeNegativos' => $record->tratamientoDeNegativos,
                        'importePrincipal' => $record->importePrincipal,
                        'importePrincipalUSD' => $record->importePrincipalUSD,
                        'componenteTipoDeCambio' => $record->componenteTipoDeCambio,
                        'informeCliente' => $record->informeCliente,
                        'informeEnFactura' => $record->informeEnFactura,
                        'adendaCliente' => $record->adendaCliente,
                        'adendaNotaCliente' => $record->adendaNotaCliente,
                        'invoiceTemplate' => $record->invoiceTemplate,
                        'noteTemplate' => $record->noteTemplate,
                        'contractType' => $record->contractType,
                        'contractMO' => $record->contractMO,
                        'frecuenciaDeCalculo' => $record->frecuenciaDeCalculo,
                        'invoiceEmailSender' => $record->invoiceEmailSender,
                        'mailServerSenderData' => $record->mailServerSenderData,
                        'allowsDiscounts' => $record->allowsDiscounts,
                        'paymentMethod' => $record->paymentMethod,
                        'paymentForm' => $record->paymentForm,
                        'quantity' => $record->quantity,
                        'units' => $record->units,
                        'productCode' => $record->productCode,
                        'invoiceConcept' => $record->invoiceConcept,
                        'additionalText' => $record->additionalText,
                        'cfdiUse' => $record->cfdiUse,
                        'isInvoiceGroup' => $record->isInvoiceGroup,
                    ];
                })->toArray();

                if (!empty($data)) {
                    $keysToUpdate = array_keys(current($data));
                    $keysToUpdate = array_filter($keysToUpdate, function($k) { return $k !== 'id'; });

                    DB::connection('tenant')->table('listadoContratos')->upsert($data, ['id'], array_values($keysToUpdate));
                }
            });
    }
}
