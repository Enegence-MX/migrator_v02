<?php

namespace App\Http\Repositories;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SimsaInvoiceRepository
{
    protected $sourceConnection;
    protected $targetConnection;
    protected $startDate;
    protected $endDate;
    protected $teamIds = [93, 339, 454];
    protected $companyName = 'SIMSA';
    protected $logger;
    protected $loggerFileName = 'simsa_invoice_sync';
    protected $storePeriodOnMonths = 2;
    protected $invoceSCTableName = 'simsa_sc_invoices';
    protected $invoceMEMTableName = 'simsa_mem_invoices';

    // SMART API configuration
    protected $apiBaseUrl = 'https://desarrollo.enegence.com.mx/panel/api';
    protected $apiEmail;
    protected $apiPassword;
    protected $batchSize = 20; // Number of invoices to process per API request
    protected $apiTimeout = 90; // API request timeout in seconds

    // TeamId credentials list
    protected $teamCredentials = [];

    /**
     * Constructor to initialize database connections and date range
     *
     * @param string|null $startDate Start date for invoice sync (Y-m-d format)
     * @param string|null $endDate   End date for invoice sync (Y-m-d format)
     */
    public function __construct($startDate = null, $endDate = null)
    {
        $this->logger = Log::channel($this->loggerFileName);

        // Source uses mysql_cloud which connects to enegence_cloud
        $this->sourceConnection = DB::connection('mysql_cloud');
        $this->targetConnection = DB::connection('mysql_gcp_simsa_target');

        if (null == $startDate) {
            $this->startDate = Carbon::now()->subDays(3)->toDateString();
        } else {
            $this->startDate = $startDate;
        }

        if (null == $endDate) {
            $this->endDate = Carbon::now()->toDateString();
        } else {
            $this->endDate = "$endDate 23:59:59";
        }

        $this->logger->info(
            sprintf(
                "== %s Invoice Sync Initialized ===",
                $this->companyName
            ),
            [
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'team_ids' => $this->teamIds,
                'timestamp' => now()->toDateTimeString()
            ]
        );

        // Initialize teamId-specific credentials
        $this->teamCredentials = [
            93 => [
                'email' => config('app.SIMSA_SMART_EMAIL_TEAMID_93', null),
                'password' => config('app.SIMSA_SMART_PASSWORD_TEAMID_93', null)
            ]
        ];
    }

    /**
     * Get team IDs as array for query building
     *
     * @return array
     */
    protected function getTeamIds()
    {
        return $this->teamIds;
    }

    /**
     * Build team ID WHERE clause and parameters
     *
     * @return array ['clause' => string, 'params' => array]
     */
    protected function buildTeamIdClause()
    {
        $teamIds = $this->getTeamIds();

        if (count($teamIds) === 1) {
            return [
                'clause' => 'i.team_id = ?',
                'params' => $teamIds
            ];
        } else {
            $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
            return [
                'clause' => "i.team_id IN ({$placeholders})",
                'params' => $teamIds
            ];
        }
    }

    /**
     * Remove old invoices from target database based on storePeriodOnMonths
     * Deletes invoices where UPDATE_FACTURACION is older than the retention period
     *
     * @return array Summary of deletion results
     */
    public function removeOldInvoices()
    {
        $cutoffDate = Carbon::now()->subMonths($this->storePeriodOnMonths);

        $this->logger->info('Starting old invoices removal process', [
            'cutoff_date' => $cutoffDate->toDateTimeString(),
            'retention_months' => $this->storePeriodOnMonths,
            'current_date' => Carbon::now()->toDateTimeString()
        ]);

        try {
            // Delete old SC invoices
            $this->logger->info('Deleting old SC invoices', [
                'table' => $this->invoceSCTableName,
                'cutoff_date' => $cutoffDate->toDateTimeString()
            ]);

            $deletedSC = $this->targetConnection
                ->table($this->invoceSCTableName)
                ->where('UPDATE_FACTURACION', '<', $cutoffDate)
                ->delete();

            $this->logger->info('SC invoices deleted', [
                'table' => $this->invoceSCTableName,
                'deleted_count' => $deletedSC
            ]);

            // Delete old MEM invoices
            $this->logger->info('Deleting old MEM invoices', [
                'table' => $this->invoceMEMTableName,
                'cutoff_date' => $cutoffDate->toDateTimeString()
            ]);

            $deletedMEM = $this->targetConnection
                ->table($this->invoceMEMTableName)
                ->where('UPDATE_FACTURACION', '<', $cutoffDate)
                ->delete();

            $this->logger->info('MEM invoices deleted', [
                'table' => $this->invoceMEMTableName,
                'deleted_count' => $deletedMEM
            ]);

            $summary = [
                'success' => true,
                'cutoff_date' => $cutoffDate->toDateTimeString(),
                'retention_months' => $this->storePeriodOnMonths,
                'sc_deleted' => $deletedSC,
                'mem_deleted' => $deletedMEM,
                'total_deleted' => $deletedSC + $deletedMEM,
                'timestamp' => Carbon::now()->toDateTimeString()
            ];

            $this->logger->info('=== Old Invoices Removal Completed ===', $summary);

            return $summary;
        } catch (Exception $e) {
            $this->logger->error('Critical error in removeOldInvoices', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Synchronize invoices from source to target database
     * Handles both SC and MEM module invoices separately
     *
     * @return array Summary of synchronization results
     */
    public function synchronizeInvoices()
    {
        $this->logger->info('Starting invoice synchronization process for both SC and MEM modules');

        try {
            $scStats = $this->synchronizeSCInvoices();
            $memStats = $this->synchronizeMEMInvoices();

            $summary = [
                'success' => true,
                'sc_module' => $scStats,
                'mem_module' => $memStats,
                'totals' => [
                    'total_fetched' => $scStats['total_fetched'] + $memStats['total_fetched'],
                    'inserted' => $scStats['inserted'] + $memStats['inserted'],
                    'updated' => $scStats['updated'] + $memStats['updated'],
                    'errors' => $scStats['errors'] + $memStats['errors'],
                ],
                'date_range' => [
                    'start' => $this->startDate,
                    'end' => $this->endDate
                ]
            ];

            $this->logger->info('=== Invoice Synchronization Completed ===', $summary);

            return $summary;
        } catch (Exception $e) {
            $this->logger->error('Critical error in synchronizeInvoices', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Synchronize SC module invoices
     *
     * @return array Summary of SC synchronization results
     */
    protected function synchronizeSCInvoices()
    {
        $this->logger->info('Starting SC module invoice synchronization');

        try {
            // Fetch SC invoices from source
            $this->logger->info('Fetching SC invoices from source database');
            $invoices = $this->fetchSCInvoicesFromSource();
            $totalFetched = count($invoices);

            $this->logger->info("Successfully fetched {$totalFetched} SC invoices from source", [
                'total_invoices' => $totalFetched,
                'module' => 'sc',
                'date_range' => [
                    'start' => $this->startDate,
                    'end' => $this->endDate
                ]
            ]);

            $inserted = 0;
            $updated = 0;
            $errors = 0;
            $processedCount = 0;

            foreach ($invoices as $invoice) {
                $processedCount++;

                try {
                    $this->logger->debug("Processing SC invoice {$processedCount}/{$totalFetched}", [
                        'invoice_id' => $invoice->id ?? 'N/A',
                        'invoice_number' => $invoice->invoiceNumber ?? 'N/A',
                        'uuid' => $invoice->uuid ?? 'N/A',
                        'module' => 'sc'
                    ]);

                    $mappedData = $this->mapSCInvoiceData($invoice);
                    $result = $this->upsertSCInvoiceData($mappedData);

                    if ($result === 'inserted') {
                        $inserted++;
                        $this->logger->info("SC Invoice inserted successfully", [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoiceNumber,
                            'uuid' => $invoice->uuid
                        ]);
                    } elseif ($result === 'updated') {
                        $updated++;
                        $this->logger->info("SC Invoice updated successfully", [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoiceNumber,
                            'uuid' => $invoice->uuid
                        ]);
                    }

                    // Log progress every 10 records
                    if ($processedCount % 10 === 0) {
                        $this->logger->info("SC Progress: {$processedCount}/{$totalFetched} processed", [
                            'inserted' => $inserted,
                            'updated' => $updated,
                            'errors' => $errors
                        ]);
                    }
                } catch (Exception $e) {
                    $errors++;
                    $this->logger->error('Error processing SC invoice', [
                        'invoice_id' => $invoice->id ?? 'N/A',
                        'invoice_number' => $invoice->invoiceNumber ?? 'N/A',
                        'uuid' => $invoice->uuid ?? 'N/A',
                        'module' => 'sc',
                        'error_message' => $e->getMessage(),
                        'error_trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $scSummary = [
                'total_fetched' => $totalFetched,
                'inserted' => $inserted,
                'updated' => $updated,
                'errors' => $errors,
            ];

            $this->logger->info('=== SC Module Synchronization Completed ===', $scSummary);

            // Execute Invoice PDF synchronization.
            $this->synchronizeSCPDFInvoices();
            return $scSummary;
        } catch (Exception $e) {
            $this->logger->error('Critical error in synchronizeSCInvoices', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * Synchronize MEM module invoices
     *
     * @return array Summary of MEM synchronization results
     */
    protected function synchronizeMEMInvoices()
    {
        $this->logger->info('Starting MEM module invoice synchronization');

        try {
            // Fetch MEM invoices from source
            $this->logger->info('Fetching MEM invoices from source database');
            $invoices = $this->fetchMEMInvoicesFromSource();
            $totalFetched = count($invoices);

            $this->logger->info("Successfully fetched {$totalFetched} MEM invoices from source", [
                'total_invoices' => $totalFetched,
                'module' => 'mem',
                'date_range' => [
                    'start' => $this->startDate,
                    'end' => $this->endDate
                ]
            ]);

            $inserted = 0;
            $updated = 0;
            $errors = 0;
            $processedCount = 0;

            foreach ($invoices as $invoice) {
                $processedCount++;

                try {
                    $this->logger->debug("Processing MEM invoice {$processedCount}/{$totalFetched}", [
                        'invoice_id' => $invoice->id ?? 'N/A',
                        'invoice_number' => $invoice->invoiceNumber ?? 'N/A',
                        'uuid' => $invoice->uuid ?? 'N/A',
                        'module' => 'mem'
                    ]);

                    $mappedData = $this->mapMEMInvoiceData($invoice);
                    $result = $this->upsertMEMInvoiceData($mappedData);

                    if ($result === 'inserted') {
                        $inserted++;
                        $this->logger->info("MEM Invoice inserted successfully", [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoiceNumber,
                            'uuid' => $invoice->uuid
                        ]);
                    } elseif ($result === 'updated') {
                        $updated++;
                        $this->logger->info("MEM Invoice updated successfully", [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoiceNumber,
                            'uuid' => $invoice->uuid
                        ]);
                    }

                    // Log progress every 10 records
                    if ($processedCount % 10 === 0) {
                        $this->logger->info("MEM Progress: {$processedCount}/{$totalFetched} processed", [
                            'inserted' => $inserted,
                            'updated' => $updated,
                            'errors' => $errors
                        ]);
                    }
                } catch (Exception $e) {
                    $errors++;
                    $this->logger->error('Error processing MEM invoice', [
                        'invoice_id' => $invoice->id ?? 'N/A',
                        'invoice_number' => $invoice->invoiceNumber ?? 'N/A',
                        'uuid' => $invoice->uuid ?? 'N/A',
                        'module' => 'mem',
                        'error_message' => $e->getMessage(),
                        'error_trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $memSummary = [
                'total_fetched' => $totalFetched,
                'inserted' => $inserted,
                'updated' => $updated,
                'errors' => $errors,
            ];

            $this->logger->info('=== MEM Module Synchronization Completed ===', $memSummary);

            // Execute Invoice PDF synchronization.
            $this->synchronizeMEMPDFInvoices();
            return $memSummary;
        } catch (Exception $e) {
            $this->logger->error('Critical error in synchronizeMEMInvoices', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch SC module invoices from source database
     * Joins with calculationsResults table for SC-specific fields
     *
     * @return array Collection of SC invoices
     */
    protected function fetchSCInvoicesFromSource()
    {
        $this->logger->debug('Executing SC invoices source database query', [
            'team_ids' => $this->getTeamIds(),
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'module' => 'sc'
        ]);

        try {
            $teamIdClause = $this->buildTeamIdClause();

            $query = "
                SELECT
                    -- From enegence_cloud.invoices
                    i.id,
                    i.team_id,
                    i.contractId,
                    i.contractCalculation,
                    i.invoiceNumber,
                    i.estado,
                    i.cfdiBeforeTimbrado,
                    i.created_at,
                    i.updated_at,
                    i.cfdi,
                    i.invoice_module,
                    i.uuid,
                    i.tipo_factura,
                    i.serie,
                    i.folio,

                    -- From enegence_dev.calculationsResults
                    cr.centrosDeCargaParam,
                    cr.centralesElectricasParam,
                    cr.endDateParam,
                    cr.startDateParam,
                    cr.quantityEnergySectionSum,
                    cr.CFE_SSB,
                    cr.fechaPago,
                    cr.metodoPago,
                    cr.uuidComplemento,
                    cr.energyAmount,
                    cr.capacityAmount,
                    cr.cleanEnergyCertificateAmount,
                    cr.regulatedTariffAmount,
                    cr.marketCostAmount,
                    cr.associatedProductsAmount,
                    cr.othersAmount,
                    cr.receptor,
                    cr.tipoComprobante,
                    cr.fechaEmision,
                    cr.subtotal,
                    cr.iva,
                    cr.total

                FROM enegence_cloud.invoices AS i
                LEFT JOIN enegence_dev.calculationsResults AS cr ON i.uuid = cr.uuid

                WHERE {$teamIdClause['clause']}
                AND i.invoice_module = 'sc'
                AND i.estado in ('facturado', 'Cancelado', 'Cancelación local')
                AND i.updated_at BETWEEN ? AND ?
            ";

            $parameters = array_merge($teamIdClause['params'], [$this->startDate, $this->endDate]);
            $results = $this->sourceConnection->select($query, $parameters);

            $this->logger->debug('SC query executed successfully', [
                'rows_returned' => count($results)
            ]);

            return $results;
        } catch (Exception $e) {
            $this->logger->error('Error fetching SC invoices from source database', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);
            throw $e;
        }
    }

    /**
     * Fetch MEM module invoices from source database
     * Joins with ecd_montos_diarios table for MEM-specific fields
     *
     * @return array Collection of MEM invoices
     */
    protected function fetchMEMInvoicesFromSource()
    {
        $this->logger->debug('Executing MEM invoices source database query', [
            'team_ids' => $this->getTeamIds(),
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'module' => 'mem'
        ]);

        try {
            $teamIdClause = $this->buildTeamIdClause();

            $query = "
                SELECT
                    -- From enegence_cloud.invoices
                    i.id,
                    i.team_id,
                    i.invoiceNumber,
                    i.estado,
                    i.cfdiBeforeTimbrado,
                    i.created_at,
                    i.updated_at,
                    i.cfdi,
                    i.invoice_module,
                    i.uuid,
                    i.tipo_factura,
                    i.codigo_fuf_idx as fuf,
                    i.serie,
                    i.folio,

                    -- From enegence_dev.ecd_montos_diarios
                    ecd.cuenta_de_orden,
                    ecd.fecha_oper,
                    ecd.fecha_fuf,
                    ecd.fuecd,
                    ecd.liquidacion,
                    ecd.mes,
                    ecd.semana,
                    ecd.monto_total,
                    ecd.total_neto,
                    ecd.fechaTimbrado,
                    ecd.emisor,
                    ecd.tipoDocumento,
                    ecd.tipoComprobante,
                    ecd.fechaPago,
                    ecd.metodoPago,
                    ecd.uuidComplemento,
                    ecd.fechaTimbradoComplemento

                FROM enegence_cloud.invoices AS i
                LEFT JOIN enegence_dev.ecd_montos_diarios AS ecd ON i.uuid = ecd.uuid

                WHERE {$teamIdClause['clause']}
                AND i.invoice_module = 'mem'
                AND i.estado in ('facturado', 'Cancelado', 'Cancelación local')
                AND i.updated_at BETWEEN ? AND ?
            ";

            $parameters = array_merge($teamIdClause['params'], [$this->startDate, $this->endDate]);
            $results = $this->sourceConnection->select($query, $parameters);

            $this->logger->debug('MEM query executed successfully', [
                'rows_returned' => count($results)
            ]);

            return $results;
        } catch (Exception $e) {
            $this->logger->error('Error fetching MEM invoices from source database', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);
            throw $e;
        }
    }

    /**
     * Map SC invoice data from source format to target simsa_sc_invoices format
     *
     * @param object $sourceInvoice Source invoice data from enegence_cloud.invoices + calculationsResults
     * @return array Mapped SC invoice data
     */
    protected function mapSCInvoiceData($sourceInvoice)
    {
        return [
            'TEAMID' => $sourceInvoice->team_id ?? null,
            'FACTURAID' => $sourceInvoice->id ?? null,
            'CONTRATO' => $sourceInvoice->contractId ?? null,
            'CALCULO' => $sourceInvoice->contractCalculation ?? null,
            'FECHAINICIO' => $sourceInvoice->startDateParam ?? null,
            'FECHAFIN' => $sourceInvoice->endDateParam ?? null,
            'CENTROSDECARGA' => $sourceInvoice->centrosDeCargaParam ?? null,
            'CENTRALESELECTRICAS' => $sourceInvoice->centralesElectricasParam ?? null,
            'CANTIDADENERGIA' => $sourceInvoice->quantityEnergySectionSum ?? null,
            'CFE_SSB' => $sourceInvoice->CFE_SSB ?? null,
            'ENERGIA' => $sourceInvoice->energyAmount ?? null,
            'POTENCIA' => $sourceInvoice->capacityAmount ?? null,
            'CELS' => $sourceInvoice->cleanEnergyCertificateAmount ?? null,
            'TARIFASREGULADAS' => $sourceInvoice->regulatedTariffAmount ?? null,
            'PRODUCTOSASOCIADOS' => $sourceInvoice->associatedProductsAmount ?? null,
            'COSTOSDEMERCADO' => $sourceInvoice->marketCostAmount ?? null,
            'OTROS' => $sourceInvoice->othersAmount ?? null,
            'RECEPTOR' => $sourceInvoice->receptor ?? null,
            'TIPOCOMPROBANTE' => $sourceInvoice->tipoComprobante ?? null,
            'FECHAEMISION' => $sourceInvoice->fechaEmision ?? null,
            'SUBTOTAL' => $sourceInvoice->subtotal ?? null,
            'IVA' => $sourceInvoice->iva ?? null,
            'TOTAL' => $sourceInvoice->total ?? null,
            'UUID' => $sourceInvoice->uuid ?? null,
            'FECHAPAGO' => $sourceInvoice->fechaPago ?? null,
            'METODOPAGO' => $sourceInvoice->metodoPago ?? null,
            'UUIDCOMPLEMENTO' => $sourceInvoice->uuidComplemento ?? null,
            'FACTURA' => $sourceInvoice->invoiceNumber ?? null,
            'ESTADO' => $sourceInvoice->estado ?? null,
            'XML' => $sourceInvoice->cfdiBeforeTimbrado ?? null,
            'TIPODOCUMENTO' => $sourceInvoice->tipo_factura ?? null,
            'CFDI' => $sourceInvoice->cfdi ?? null,
            'SERIE' => $sourceInvoice->serie ?? null,
            'FOLIO' => $sourceInvoice->folio ?? null,
            'FECHA_FACTURACION' => $sourceInvoice->created_at ?? null,
            'UPDATE_FACTURACION' => $sourceInvoice->updated_at ?? null,
            'FECHAENVIO' => date("Y-m-d H:i:s"),
            'FECHAACTUALIZACION' => date("Y-m-d H:i:s"),
        ];
    }

    /**
     * Map MEM invoice data from source format to target simsa_mem_invoices format
     *
     * @param object $sourceInvoice Source invoice data from enegence_cloud.invoices + ecd_montos_diarios
     * @return array Mapped MEM invoice data
     */
    protected function mapMEMInvoiceData($sourceInvoice)
    {
        return [
            'TEAMID' => $sourceInvoice->team_id ?? null,
            'FACTURAID' => $sourceInvoice->id ?? null,
            'UUID' => $sourceInvoice->uuid ?? null,
            'FECHAPAGO' => $sourceInvoice->fechaPago ?? null,
            'METODOPAGO' => $sourceInvoice->metodoPago ?? null,
            'UUIDCOMPLEMENTO' => $sourceInvoice->uuidComplemento ?? null,
            'FECHATIMBRADOCOMPLEMENTO' => $sourceInvoice->fechaTimbradoComplemento ?? null,
            'FACTURA' => $sourceInvoice->invoiceNumber ?? null,
            'ESTADO' => $sourceInvoice->estado ?? null,
            'XML' => $sourceInvoice->cfdiBeforeTimbrado ?? null,
            'FECHA_FACTURACION' => $sourceInvoice->created_at ?? null,
            'UPDATE_FACTURACION' => $sourceInvoice->updated_at ?? null,
            'CUENTADEORDEN' => $sourceInvoice->cuenta_de_orden ?? null,
            'FECHAOPER' => $sourceInvoice->fecha_oper ?? null,
            'FECHAFUF' => $sourceInvoice->fecha_fuf ?? null,
            'FUECD' => $sourceInvoice->fuecd ?? null,
            'FUF' => $sourceInvoice->fuf ?? null,
            'LIQUIDACION' => $sourceInvoice->liquidacion ?? null,
            'MES' => $sourceInvoice->mes ?? null,
            'SEMANA' => $sourceInvoice->semana ?? null,
            'MONTOTOTAL' => $sourceInvoice->monto_total ?? null,
            'TOTALNETO' => $sourceInvoice->total_neto ?? null,
            'FECHATIMBRADO' => $sourceInvoice->fechaTimbrado ?? null,
            'EMISOR' => $sourceInvoice->emisor ?? null,
            'TIPODOCUMENTO' => ($sourceInvoice->tipo_factura == 'Pago')
                ? 'Pago'
                : $sourceInvoice->tipoDocumento ?? null,
            'TIPOCOMPROBANTE' => $sourceInvoice->tipoComprobante ?? null,
            'CFDI' => $sourceInvoice->cfdi ?? null,
            'SERIE' => $sourceInvoice->serie ?? null,
            'FOLIO' => $sourceInvoice->folio ?? null,
            'FECHAENVIO' => date("Y-m-d H:i:s"),
            'FECHAACTUALIZACION' => date("Y-m-d H:i:s"),
        ];
    }

    /**
     * Insert or update SC invoice in target database (simsa_sc_invoices table)
     *
     * @param array $invoiceData Mapped SC invoice data
     * @return string 'inserted' or 'updated'
     */
    protected function upsertSCInvoiceData($invoiceData)
    {
        $existing = null;

        try {
            // Try to find existing record by UUID or FACTURAID
            if (!empty($invoiceData['UUID'])) {
                $this->logger->debug('Checking for existing SC invoice by UUID', [
                    'uuid' => $invoiceData['UUID'],
                    'table' => $this->invoceSCTableName
                ]);

                $existing = $this->targetConnection
                    ->table($this->invoceSCTableName)
                    ->where('UUID', $invoiceData['UUID'])
                    ->first();
            } elseif (!empty($invoiceData['FACTURAID'])) {
                $this->logger->debug('Checking for existing SC invoice by FACTURAID', [
                    'factura_id' => $invoiceData['FACTURAID'],
                    'table' => $this->invoceSCTableName
                ]);

                $existing = $this->targetConnection
                    ->table($this->invoceSCTableName)
                    ->where('FACTURAID', $invoiceData['FACTURAID'])
                    ->first();
            }

            if ($existing) {
                // Update existing record
                $this->logger->debug('Updating existing SC invoice', [
                    'target_id' => $existing->ID,
                    'uuid' => $invoiceData['UUID'],
                    'factura_id' => $invoiceData['FACTURAID'],
                    'table' => $this->invoceSCTableName
                ]);
                unset($invoiceData['FECHAENVIO']);

                $this->targetConnection
                    ->table($this->invoceSCTableName)
                    ->where('ID', $existing->ID)
                    ->update($invoiceData);

                return 'updated';
            } else {
                // Insert new record
                $this->logger->debug('Inserting new SC invoice', [
                    'uuid' => $invoiceData['UUID'],
                    'factura_id' => $invoiceData['FACTURAID'],
                    'invoice_number' => $invoiceData['FACTURA'],
                    'table' => $this->invoceSCTableName
                ]);

                $this->targetConnection
                    ->table($this->invoceSCTableName)
                    ->insert($invoiceData);

                return 'inserted';
            }
        } catch (Exception $e) {
            $this->logger->error('Error during SC invoice upsert operation', [
                'uuid' => $invoiceData['UUID'] ?? 'N/A',
                'factura_id' => $invoiceData['FACTURAID'] ?? 'N/A',
                'table' => $this->invoceSCTableName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);
            throw $e;
        }
    }

    /**
     * Insert or update MEM invoice in target database (simsa_mem_invoices table)
     *
     * @param array $invoiceData Mapped MEM invoice data
     * @return string 'inserted' or 'updated'
     */
    protected function upsertMEMInvoiceData($invoiceData)
    {
        $existing = null;

        try {
            // Try to find existing record by UUID or FACTURAID
            if (!empty($invoiceData['UUID'])) {
                $this->logger->debug('Checking for existing MEM invoice by UUID', [
                    'uuid' => $invoiceData['UUID'],
                    'table' => $this->invoceMEMTableName
                ]);

                $existing = $this->targetConnection
                    ->table($this->invoceMEMTableName)
                    ->where('UUID', $invoiceData['UUID'])
                    ->first();
            } elseif (!empty($invoiceData['FACTURAID'])) {
                $this->logger->debug('Checking for existing MEM invoice by FACTURAID', [
                    'factura_id' => $invoiceData['FACTURAID'],
                    'table' => $this->invoceMEMTableName
                ]);

                $existing = $this->targetConnection
                    ->table($this->invoceMEMTableName)
                    ->where('FACTURAID', $invoiceData['FACTURAID'])
                    ->first();
            }

            if ($existing) {
                // Update existing record
                $this->logger->debug('Updating existing MEM invoice', [
                    'target_id' => $existing->ID,
                    'uuid' => $invoiceData['UUID'],
                    'factura_id' => $invoiceData['FACTURAID'],
                    'table' => $this->invoceMEMTableName
                ]);
                unset($invoiceData['FECHAENVIO']);

                $this->targetConnection
                    ->table($this->invoceMEMTableName)
                    ->where('ID', $existing->ID)
                    ->update($invoiceData);

                return 'updated';
            } else {
                // Insert new record
                $this->logger->debug('Inserting new MEM invoice', [
                    'uuid' => $invoiceData['UUID'],
                    'factura_id' => $invoiceData['FACTURAID'],
                    'invoice_number' => $invoiceData['FACTURA'],
                    'table' => $this->invoceMEMTableName
                ]);

                $this->targetConnection
                    ->table($this->invoceMEMTableName)
                    ->insert($invoiceData);

                return 'inserted';
            }
        } catch (Exception $e) {
            $this->logger->error('Error during MEM invoice upsert operation', [
                'uuid' => $invoiceData['UUID'] ?? 'N/A',
                'factura_id' => $invoiceData['FACTURAID'] ?? 'N/A',
                'table' => $this->invoceMEMTableName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);
            throw $e;
        }
    }

    /**
     * Get count of invoices in date range
     *
     * @return int Count of invoices
     */
    public function getInvoiceCount()
    {
        $this->logger->info('Getting invoice count from source database');

        try {
            $query = "
                SELECT COUNT(*) as total
                FROM enegence_cloud.invoices AS i
                WHERE i.team_id = 93
                AND i.created_at BETWEEN ? AND ?
            ";

            $result = $this->sourceConnection->select($query, [$this->startDate, $this->endDate]);
            $count = $result[0]->total ?? 0;

            $this->logger->info('Source invoice count retrieved', [
                'count' => $count,
                'date_range' => [
                    'start' => $this->startDate,
                    'end' => $this->endDate
                ]
            ]);

            return $count;
        } catch (Exception $e) {
            $this->logger->error('Error getting source invoice count', [
                'error_message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get count of invoices in target database
     *
     * @return int Count of invoices
     */
    public function getTargetInvoiceCount()
    {
        $this->logger->info('Getting invoice count from target database');

        try {
            $count = $this->targetConnection
                ->table('simsa_invoices')
                ->count();

            $this->logger->info('Target invoice count retrieved', [
                'count' => $count
            ]);

            return $count;
        } catch (Exception $e) {
            $this->logger->error('Error getting target invoice count', [
                'error_message' => $e->getMessage()
            ]);
            throw $e;
        }
    }


    /**
     * Synchronize SC module invoices with API download functionality
     * Overrides parent method to add invoice download via API
     * Processes invoices grouped by TeamId with separate authentication per team
     *
     * @return array Summary of SC synchronization results
     */
    protected function synchronizeSCPDFInvoices()
    {
        $this->logger->info('Starting Acciona SC module PDF invoice synchronization with API download');

        try {
            // Get list of FACTURAID values grouped by TeamId
            $this->logger->info('Fetching FACTURAID list grouped by TeamId for API download');
            $facturaIdsByTeam = $this->getFACTURAIDList();

            if (empty($facturaIdsByTeam)) {
                $this->logger->info('No FACTURAID values found for API download');
                $scSummary['api_download'] = [
                    'attempted' => false,
                    'reason' => 'No FACTURAID values available'
                ];
                return $scSummary;
            }

            $this->logger->info('Retrieved FACTURAID list grouped by TeamId', [
                'teams' => array_keys($facturaIdsByTeam),
                'counts_per_team' => array_map('count', $facturaIdsByTeam)
            ]);

            // Process invoices for each TeamId separately
            $downloadResults = [];
            $totalUpdated = 0;
            $totalErrors = 0;

            foreach ($facturaIdsByTeam as $teamId => $facturaIds) {
                $this->logger->info('Processing TeamId', [
                    'team_id' => $teamId,
                    'factura_count' => count($facturaIds)
                ]);

                // Authenticate and get API token for this specific teamId
                $this->logger->info('Authenticating with API to get token for TeamId', [
                    'team_id' => $teamId
                ]);
                $token = $this->getApiToken($teamId);

                if (!$token) {
                    $this->logger->error('Failed to obtain API token for TeamId', [
                        'team_id' => $teamId
                    ]);
                    $downloadResults[$teamId] = [
                        'attempted' => true,
                        'success' => false,
                        'reason' => 'Authentication failed',
                        'team_id' => $teamId
                    ];
                    continue;
                }

                $this->logger->info('Successfully obtained API token for TeamId', [
                    'team_id' => $teamId
                ]);

                // Download invoices using the API for this teamId
                $this->logger->info('Downloading invoices via API for TeamId', [
                    'team_id' => $teamId,
                    'factura_ids' => $facturaIds
                ]);
                $downloadResult = $this->downloadInvoices($token, $facturaIds);

                $downloadResult['team_id'] = $teamId;
                $downloadResults[$teamId] = $downloadResult;

                if ($downloadResult['success']) {
                    $totalUpdated += $downloadResult['updated_count'];
                    $totalErrors += $downloadResult['error_count'];
                }
            }

            $scSummary['api_download'] = [
                'attempted' => true,
                'success' => true,
                'teams_processed' => count($facturaIdsByTeam),
                'total_updated' => $totalUpdated,
                'total_errors' => $totalErrors,
                'results_by_team' => $downloadResults
            ];

            $this->logger->info('=== Acciona SC Module Synchronization with API Download Completed ===', $scSummary);

            return $scSummary;
        } catch (Exception $e) {
            $this->logger->error('Critical error in Acciona synchronizeSCInvoices', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get list of FACTURAID values grouped by TeamId
     *
     * @return array Array with TeamId as keys and FACTURAID arrays as values
     */
    protected function getFACTURAIDList()
    {
        try {
            $results = $this->targetConnection
                ->table($this->invoceSCTableName)
                ->select('FACTURAID', 'TeamId')
                ->whereNotNull('FACTURAID')
                ->whereNull('PDFFACTURA')
                ->get();

            $groupedByTeam = [];
            foreach ($results as $result) {
                $teamId = $result->TeamId;
                if (!isset($groupedByTeam[$teamId])) {
                    $groupedByTeam[$teamId] = [];
                }
                $groupedByTeam[$teamId][] = $result->FACTURAID;
            }

            $this->logger->debug('FACTURAID list retrieved and grouped by TeamId', [
                'teams' => array_keys($groupedByTeam),
                'counts_per_team' => array_map('count', $groupedByTeam),
                'table' => $this->invoceSCTableName
            ]);

            return $groupedByTeam;
        } catch (Exception $e) {
            $this->logger->error('Error fetching FACTURAID list', [
                'error_message' => $e->getMessage(),
                'table' => $this->invoceSCTableName
            ]);
            throw $e;
        }
    }

    /**
     * Authenticate with API and get access token for specific teamId
     *
     * @param int $teamId The team ID to authenticate for
     * @return string|null API token or null on failure
     */
    protected function getApiToken($teamId)
    {
        try {
            // Get credentials for the specific teamId
            if (!isset($this->teamCredentials[$teamId])) {
                $this->logger->error('No credentials found for teamId', [
                    'team_id' => $teamId
                ]);
                return null;
            }

            $credentials = $this->teamCredentials[$teamId];
            $email = $credentials['email'];
            $password = $credentials['password'];

            $this->logger->debug('Sending authentication request to API', [
                'url' => "{$this->apiBaseUrl}/login",
                'email' => $email,
                'team_id' => $teamId
            ]);

            $response = Http::post("{$this->apiBaseUrl}/login", [
                'email' => $email,
                'password' => $password
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['token'] ?? null;

                if ($token) {
                    $this->logger->info('API authentication successful for teamId', [
                        'team_id' => $teamId
                    ]);
                    return $token;
                } else {
                    $this->logger->error('API response missing token', [
                        'team_id' => $teamId,
                        'response' => $data
                    ]);
                    return null;
                }
            } else {
                $this->logger->error('API authentication failed', [
                    'team_id' => $teamId,
                    'status_code' => $response->status(),
                    'response_body' => $response->body()
                ]);
                return null;
            }
        } catch (Exception $e) {
            $this->logger->error('Exception during API authentication', [
                'team_id' => $teamId,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Download SC invoices using API and update PDFFACTURA field
     * Processes invoices in batches to prevent timeouts
     *
     * @param string $token API access token
     * @param array $facturaIds List of FACTURAID values
     * @return array Download result summary
     */
    protected function downloadInvoices($token, $facturaIds)
    {
        $updatedCount = 0;
        $errorCount = 0;
        $totalInvoices = count($facturaIds);

        try {
            $this->logger->info('Starting SC invoice download with batch processing', [
                'total_invoices' => $totalInvoices,
                'batch_size' => $this->batchSize,
                'estimated_batches' => ceil($totalInvoices / $this->batchSize)
            ]);

            // Process invoices in batches
            $batches = array_chunk($facturaIds, $this->batchSize);
            $batchNumber = 0;

            foreach ($batches as $batchIds) {
                $batchNumber++;

                $this->logger->debug('Processing SC invoice batch', [
                    'batch_number' => $batchNumber,
                    'batch_size' => count($batchIds),
                    'url' => "{$this->apiBaseUrl}/invoices/download"
                ]);

                $response = Http::timeout($this->apiTimeout)
                    ->withHeaders([
                        'Authorization' => "Bearer {$token}",
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])->post("{$this->apiBaseUrl}/invoices/download", [
                        'ids' => $batchIds,
                        'type' => 'pdf'
                    ]);

                if ($response->successful()) {
                    $responseData = $response->json();

                    $this->logger->info('SC Invoice batch download successful', [
                        'batch_number' => $batchNumber,
                        'status_code' => $response->status(),
                        'response_data_count' => isset($responseData['data']) ? count($responseData['data']) : 0
                    ]);

                    // Process each invoice and update PDFFACTURA field
                    if (isset($responseData['data']) && is_array($responseData['data'])) {
                        foreach ($responseData['data'] as $invoiceData) {
                            try {
                                $facturaId = $invoiceData['invoiceId'] ?? null;
                                $pdfBase64 = $invoiceData['content'] ?? null;

                                if ($facturaId && $pdfBase64) {
                                    $this->logger->debug('Updating PDFFACTURA for SC invoice', [
                                        'factura_id' => $facturaId,
                                        'batch_number' => $batchNumber
                                    ]);

                                    $updated = $this->targetConnection
                                        ->table($this->invoceSCTableName)
                                        ->where('FACTURAID', $facturaId)
                                        ->update([
                                            'PDFFACTURA' => $pdfBase64,
                                            'FECHAACTUALIZACION' => date("Y-m-d H:i:s")
                                        ]);

                                    if ($updated) {
                                        $updatedCount++;
                                        $this->logger->info('SC Invoice PDF updated successfully', [
                                            'factura_id' => $facturaId
                                        ]);
                                    }
                                } else {
                                    $this->logger->warning('Missing invoiceId or content in response data', [
                                        'invoice_data' => $invoiceData,
                                        'batch_number' => $batchNumber
                                    ]);
                                    $errorCount++;
                                }
                            } catch (Exception $e) {
                                $errorCount++;
                                $this->logger->error('Error updating SC invoice PDF', [
                                    'factura_id' => $facturaId ?? 'N/A',
                                    'error_message' => $e->getMessage(),
                                    'batch_number' => $batchNumber
                                ]);
                            }
                        }
                    }
                } else {
                    $this->logger->error('SC Invoice batch download failed', [
                        'batch_number' => $batchNumber,
                        'status_code' => $response->status(),
                        'response_body' => $response->body(),
                        'batch_ids_count' => count($batchIds)
                    ]);
                    $errorCount += count($batchIds);
                }
            }

            return [
                'attempted' => true,
                'success' => true,
                'status_code' => 200,
                'factura_ids_count' => $totalInvoices,
                'batches_processed' => $batchNumber,
                'updated_count' => $updatedCount,
                'error_count' => $errorCount
            ];
        } catch (Exception $e) {
            $this->logger->error('Exception during SC invoice download', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return [
                'attempted' => true,
                'success' => false,
                'factura_ids_count' => $totalInvoices,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Synchronize MEM module invoices with API download functionality
     * Overrides parent method to add invoice download via API
     * Processes invoices grouped by TeamId with separate authentication per team
     *
     * @return array Summary of MEM synchronization results
     */
    protected function synchronizeMEMPDFInvoices()
    {
        $this->logger->info('Starting Acciona MEM module invoice synchronization with API download');

        try {
            // Get list of FACTURAID and FUF values grouped by TeamId
            $this->logger->info('Fetching FACTURAID and FUF list grouped by TeamId for MEM API download');
            $invoiceDataByTeam = $this->getFACTURAIDListMEM();

            if (empty($invoiceDataByTeam)) {
                $this->logger->info('No FACTURAID values found for MEM API download');
                $memSummary['api_download'] = [
                    'attempted' => false,
                    'reason' => 'No FACTURAID values available'
                ];
                return $memSummary;
            }

            $this->logger->info('Retrieved MEM FACTURAID and FUF list grouped by TeamId', [
                'teams' => array_keys($invoiceDataByTeam),
                'counts_per_team' => array_map(
                    function ($team) {
                        return count($team['ids']);
                    },
                    $invoiceDataByTeam
                )
            ]);

            // Process invoices for each TeamId separately
            $downloadResults = [];
            $totalUpdated = 0;
            $totalErrors = 0;

            foreach ($invoiceDataByTeam as $teamId => $invoiceData) {
                $this->logger->info('Processing MEM invoices for TeamId', [
                    'team_id' => $teamId,
                    'factura_count' => count($invoiceData['ids'])
                ]);

                // Authenticate and get API token for this specific teamId
                $this->logger->info('Authenticating with API to get token for MEM TeamId', [
                    'team_id' => $teamId
                ]);
                $token = $this->getApiToken($teamId);

                if (!$token) {
                    $this->logger->error('Failed to obtain API token for MEM TeamId', [
                        'team_id' => $teamId
                    ]);
                    $downloadResults[$teamId] = [
                        'attempted' => true,
                        'success' => false,
                        'reason' => 'Authentication failed',
                        'team_id' => $teamId
                    ];
                    continue;
                }

                $this->logger->info('Successfully obtained API token for MEM TeamId', [
                    'team_id' => $teamId
                ]);

                // Download MEM invoices using the API for this teamId
                $this->logger->info('Downloading MEM invoices via API for TeamId', [
                    'team_id' => $teamId,
                    'factura_ids' => $invoiceData['ids'],
                    'fufs' => $invoiceData['fufs']
                ]);
                $downloadResult = $this->downloadMEMInvoices($token, $invoiceData['ids'], $invoiceData['fufs']);

                $downloadResult['team_id'] = $teamId;
                $downloadResults[$teamId] = $downloadResult;

                if ($downloadResult['success']) {
                    $totalUpdated += $downloadResult['updated_count'];
                    $totalErrors += $downloadResult['error_count'];
                }
            }

            $memSummary['api_download'] = [
                'attempted' => true,
                'success' => true,
                'teams_processed' => count($invoiceDataByTeam),
                'total_updated' => $totalUpdated,
                'total_errors' => $totalErrors,
                'results_by_team' => $downloadResults
            ];

            $this->logger->info('=== Acciona MEM Module Synchronization with API Download Completed ===', $memSummary);

            return $memSummary;
        } catch (Exception $e) {
            $this->logger->error('Critical error in Acciona synchronizeMEMInvoices', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get list of FACTURAID and FUF values from MEM invoices table grouped by TeamId
     *
     * @return array Array with TeamId as keys, each containing 'ids' and 'fufs' arrays
     */
    protected function getFACTURAIDListMEM()
    {
        try {
            $results = $this->targetConnection
                ->table($this->invoceMEMTableName)
                ->select('FACTURAID', 'FUF', 'TeamId')
                ->whereNotNull('FACTURAID')
                ->whereNotNull('FUF')
                ->whereNull('PDFFACTURA')
                ->get();

            $groupedByTeam = [];

            foreach ($results as $result) {
                $teamId = $result->TeamId;
                if (!isset($groupedByTeam[$teamId])) {
                    $groupedByTeam[$teamId] = [
                        'ids' => [],
                        'fufs' => []
                    ];
                }
                $groupedByTeam[$teamId]['ids'][] = $result->FACTURAID;
                $groupedByTeam[$teamId]['fufs'][] = $result->FUF;
            }

            $this->logger->debug('MEM FACTURAID and FUF list retrieved and grouped by TeamId', [
                'teams' => array_keys($groupedByTeam),
                'counts_per_team' => array_map(
                    function ($team) {
                        return count($team['ids']);
                    },
                    $groupedByTeam
                ),
                'table' => $this->invoceMEMTableName
            ]);

            return $groupedByTeam;
        } catch (Exception $e) {
            $this->logger->error('Error fetching MEM FACTURAID list', [
                'error_message' => $e->getMessage(),
                'table' => $this->invoceMEMTableName
            ]);
            throw $e;
        }
    }

    /**
     * Download MEM invoices using API and update PDFFACTURA field
     * Makes individual requests per FUF since the API only returns FUF in single requests
     *
     * @param string $token API access token
     * @param array $facturaIds List of FACTURAID values
     * @param array $fufs List of FUF values
     * @return array Download result summary
     */
    protected function downloadMEMInvoices($token, $facturaIds, $fufs)
    {
        $updatedCount = 0;
        $errorCount = 0;
        $totalInvoices = count($facturaIds);

        try {
            // Build FUF to FACTURAID mapping
            $fufToFacturaIdMap = [];
            for ($i = 0; $i < count($fufs); $i++) {
                $fufToFacturaIdMap[$fufs[$i]] = $facturaIds[$i];
            }

            $this->logger->info('Starting MEM invoice download with single FUF requests', [
                'total_invoices' => $totalInvoices,
                'fuf_count' => count($fufs)
            ]);

            // Process each FUF individually
            $processedCount = 0;

            foreach ($fufs as $fuf) {
                $processedCount++;
                $facturaId = $fufToFacturaIdMap[$fuf];

                $this->logger->debug('Processing MEM invoice for single FUF', [
                    'progress' => "{$processedCount}/{$totalInvoices}",
                    'fuf' => $fuf,
                    'factura_id' => $facturaId,
                    'url' => "{$this->apiBaseUrl}/mem-invoices/download"
                ]);

                try {
                    $response = Http::timeout($this->apiTimeout)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])->post("{$this->apiBaseUrl}/mem-invoices/download", [
                            'fufs' => [$fuf],
                            'type' => 'pdf'
                        ]);

                    if ($response->successful()) {
                        $responseData = $response->json();

                        $this->logger->debug('MEM Invoice single FUF download successful', [
                            'fuf' => $fuf,
                            'status_code' => $response->status(),
                            'has_data' => isset($responseData['data'])
                        ]);

                        // Process the invoice data
                        $dataRows = count($responseData['data'] ?? []) > 0;
                        $dataIsArray = is_array($responseData['data'] ?? null);
                        if (isset($responseData['data']) && $dataIsArray && $dataRows) {
                            $invoiceData = $responseData['data'][0];
                            $returnedFuf = $invoiceData['fuf'] ?? null;
                            $pdfBase64 = $invoiceData['content'] ?? null;

                            if ($returnedFuf && $pdfBase64) {
                                // Use the returned FUF to get the FACTURAID
                                $mappedFacturaId = $fufToFacturaIdMap[$returnedFuf] ?? null;

                                if ($mappedFacturaId) {
                                    $this->logger->debug('Updating PDFFACTURA for MEM invoice', [
                                        'fuf' => $returnedFuf,
                                        'factura_id' => $mappedFacturaId
                                    ]);

                                    $updated = $this->targetConnection
                                        ->table($this->invoceMEMTableName)
                                        ->where('FACTURAID', $mappedFacturaId)
                                        ->where('FUF', $returnedFuf)
                                        ->update([
                                            'PDFFACTURA' => $pdfBase64,
                                            'FECHAACTUALIZACION' => date("Y-m-d H:i:s")
                                        ]);

                                    if ($updated) {
                                        $updatedCount++;
                                        $this->logger->info('MEM Invoice PDF updated successfully', [
                                            'fuf' => $returnedFuf,
                                            'factura_id' => $mappedFacturaId
                                        ]);
                                    } else {
                                        $this->logger->warning('No rows updated for MEM invoice', [
                                            'fuf' => $returnedFuf,
                                            'factura_id' => $mappedFacturaId
                                        ]);
                                        $errorCount++;
                                    }
                                } else {
                                    $this->logger->warning('Could not map FUF to FACTURAID', [
                                        'returned_fuf' => $returnedFuf
                                    ]);
                                    $errorCount++;
                                }
                            } else {
                                $this->logger->warning('Missing FUF or content in MEM response data', [
                                    'fuf' => $fuf,
                                    'has_fuf' => isset($invoiceData['fuf']),
                                    'has_content' => isset($invoiceData['content'])
                                ]);
                                $errorCount++;
                            }
                        } else {
                            $this->logger->warning('No data in MEM response', [
                                'fuf' => $fuf
                            ]);
                            $errorCount++;
                        }
                    } else {
                        $this->logger->error('MEM Invoice single FUF download failed', [
                            'fuf' => $fuf,
                            'factura_id' => $facturaId,
                            'status_code' => $response->status(),
                            'response_body' => $response->body()
                        ]);
                        $errorCount++;
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    $this->logger->error('Exception during single MEM invoice download', [
                        'fuf' => $fuf,
                        'factura_id' => $facturaId,
                        'error_message' => $e->getMessage()
                    ]);
                }

                // Add a small delay to avoid overwhelming the API
                if ($processedCount < $totalInvoices) {
                    usleep(100000); // 100ms delay between requests
                }
            }

            return [
                'attempted' => true,
                'success' => true,
                'status_code' => 200,
                'factura_ids_count' => $totalInvoices,
                'requests_made' => $processedCount,
                'updated_count' => $updatedCount,
                'error_count' => $errorCount
            ];
        } catch (Exception $e) {
            $this->logger->error('Exception during MEM invoice download', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return [
                'attempted' => true,
                'success' => false,
                'factura_ids_count' => $totalInvoices,
                'error' => $e->getMessage()
            ];
        }
    }
}
