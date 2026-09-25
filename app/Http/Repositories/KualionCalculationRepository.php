<?php

namespace App\Http\Repositories;

use Exception;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\CalculationHelper;

class KualionCalculationRepository
{
    /**
     * Generate and export the Concepts Report for Kualion calculations.
     *
     * This method retrieves contract calculations, processes concepts by section,
     * calculates amounts and IVA, stores data in MySQL, and exports to Oracle via SQL*Loader.
     *
     * @return void
     */
    public function getCalulationConceptsReport()
    {
        $query = "SELECT
                contractNumber,
                contractCalculationNumber,
                startDateParam,
                endDateParam,
                centrosDeCargaParam,
                centralesElectricasParam,
                name,
                calculationResultSection,
                calculationResultSectionByCE,
                contractId,
                created_at,
                updated_at
            FROM contractCalculations
            WHERE teamId = 155";

        $queryResult = collect(DB::connection('mysql_cloud')->select($query))->toArray();

        $centralNamesById = DB::connection('mysql_dev_2')
            ->table('centralElectrica')
            ->where('teamId', 155)
            ->pluck('name', 'id')
            ->toArray();

        $allConcepts = [];
        $reportData = []; // New report data array
        $currentDateTime = date('Y-m-d H:i:s');

        // Get all component Units
        $contractVariablesList = DB::connection('mysql_cloud')
        ->table('contractVariables')
        ->where('teamId', 155)
        ->get()
        ->keyBy('id');

        $algorithmsList = DB::connection('mysql_cloud')
        ->table('algorithms')
        ->where('teamId', 155)
        ->get()
        ->keyBy('id');

        $equationsList = DB::connection('mysql_cloud')
        ->table('equations')
        ->where('teamId', 155)
        ->get()
        ->keyBy('id');

        $primaryComponentsList = DB::connection('mysql_cloud')
        ->table('primary_components')
        ->get()
        ->keyBy('id');

        $contracts = DB::connection('mysql_cloud')
        ->table('contracts')
        ->where('teamId', 155)
        ->get()
        ->keyBy('id');

        foreach ($queryResult as $item) {
            $contract = $contracts[$item->contractId];
            $contractCurrency = $contract->currency ?? '';

            if ($contractCurrency == 'Multidivisa') {
                $calculationResultSectionByCE = json_decode(
                    $item->calculationResultSectionByCE,
                    true
                );
                $calculationResultSectionMXN = $calculationResultSectionByCE['MXN'];
                $calculationResultSectionUSD = $calculationResultSectionByCE['USD'];
                $calculationResultSection = array_merge(
                    $calculationResultSectionMXN,
                    $calculationResultSectionUSD
                );
            } else {
                $calculationResultSection = json_decode($item->calculationResultSection, true);
            }

            foreach ($calculationResultSection as $section) {
                $allConcepts[$item->contractCalculationNumber][$section['name']] = $section['concepts'];

                // New logic to generate a more detailed report
                foreach ($section['concepts'] as $concept) {
                    $component = null;
                    $currency = '';
                    switch ($concept['componentType']) {
                        case 'contractVariable':
                            $component = $contractVariablesList[$concept['componentId']] ?? null;
                            break;
                        
                        case 'algorithmComponent':
                            $component = $algorithmsList[$concept['componentId']] ?? null;
                            break;

                        case 'equationComponent':
                            $component = $equationsList[$concept['componentId']] ?? null;
                            break;
                        
                        case 'primaryComponents':
                            $component =  $primaryComponentsList[$concept['componentId']] ?? null;
                            break;
                    }

                    $componentUnits = $component->units ?? '';
                    if ($componentUnits == 'MXN' || $componentUnits == 'MXN/USD') {
                        $currency = 'MXN';
                    } elseif ($componentUnits == 'USD' || $componentUnits == 'USD/MXN') {
                        $currency = 'USD';
                    }

                    $reportData[] = [
                        'contrato' => $item->contractNumber,
                        'idDeCalculo' => $item->contractCalculationNumber,
                        'fechaInicio' => $item->startDateParam,
                        'fechaFin' => $item->endDateParam,
                        'centrosDeCarga' => $item->centrosDeCargaParam,
                        'centralesElectricas' => $centralNamesById[$item->centralesElectricasParam]
                            ?? $item->centralesElectricasParam,
                        'NombreDelCalculo' => $item->name,
                        'CategoriaOSeccion' => $section['name'],
                        'InstrumentoOProducto' => $concept['description'],
                        'componentId' => $concept['componentId'],
                        'componentType' => $concept['componentType'],
                        'UnidadComponente' => $component->units ?? null,
                        'Cantidad' => floatval($concept['cantidad']) ?? null,
                        'Precio' => CalculationHelper::convertToNumber($concept['valorUnitario'] ?? null),
                        'UnidadFact' => $concept['unidad'] ?? null,
                        'Monto' => CalculationHelper::convertToNumber($concept['total']),
                        'Divisa' => $currency,
                        'IVA' => CalculationHelper::calculateIVA(CalculationHelper::convertToNumber($concept['total'])),
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                        'report_created_at' => $currentDateTime,
                    ];
                }
            }
        }
    
        $uniqueReportData = [];
        foreach ($reportData as $row) {
            $key = implode('-', [
                $row['contrato'],
                $row['idDeCalculo'],
                $row['fechaInicio'],
                $row['fechaFin'],
                $row['CategoriaOSeccion'],
                $row['componentId']
            ]);

            $uniqueReportData[$key] = $row; // This will automatically remove duplicates
        }
        $reportData = array_values($uniqueReportData); // Convert back to indexed array

         // Insert into the database
        if (!empty($reportData)) {
            echo 'doing truncate';
            echo PHP_EOL;
            DB::connection('mysql_dev_2')
            ->statement("TRUNCATE TABLE reporteDeConceptosDeCaluloDeContrato");

            // Execute insert in chunks intead of single insert
            $chunkSize = 1000;
            // Split data in chunks
            $chunks = array_chunk($reportData, $chunkSize);
            
            foreach ($chunks as $index => $chunk) {
                try {
                    DB::connection('mysql_dev_2')
                    ->table('reporteDeConceptosDeCaluloDeContrato')
                    ->insert($chunk);
                } catch (Exception $e) {
                    echo $e->getMessage();
                    return;
                }
            }
        }

        echo 'all good';
        echo PHP_EOL;
        $this->generateConceptsReportCsv();
    }

    /**
     * Generate and export the Components Report for Kualion calculations.
     *
     * This method retrieves contract variables from calculations, processes values by date,
     * stores data in MySQL, and exports to Oracle via SQL*Loader.
     *
     * @return void
     */
    public function getCalulationComponentsReport()
    {
        $query = "SELECT contractNumber, contractCalculationNumber, centrosDeCargaParam, centralesElectricasParam, contractVariables, startDateParam, created_at, updated_at FROM contractCalculations WHERE teamId = 155";//phpcs:ignore
        $queryResult = collect(DB::connection('mysql_cloud')->select($query));
        $centralNamesById = DB::connection('mysql_dev_2')
            ->table('centralElectrica')
            ->where('teamId', 155)
            ->pluck('name', 'id')
            ->toArray();

        $reportData = [];
        $uniqueEntries = []; // Use this to track unique values
        $currentDateTime = date('Y-m-d H:i:s');

        foreach ($queryResult as $row) {
            $contractNumber = $row->contractNumber;
            $contractCalculationNumber = $row->contractCalculationNumber;
            $centrosDeCargaParam = $row->centrosDeCargaParam;
            $centralesElectricasParam = $row->centralesElectricasParam;
            $contractVariablesJson = $row->contractVariables;
            $startDateParam = $row->startDateParam; // Get start date
            $created_at = $row->created_at;
            $updated_at = $row->updated_at;

            // Format startDateParam to match the JSON keys (YYYY_M)
            $dateKey = date('Y_n', strtotime($startDateParam));
        
            // Decode JSON
            $contractVariablesArray = json_decode($contractVariablesJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                continue; // Skip invalid JSON
            }

            $variableIds = array_keys($contractVariablesArray);

            if (empty($variableIds)) {
                continue;
            }

            // Fetch variable details from contractVariables table
            $variables = DB::connection('mysql_cloud')
                ->table('contractVariables')
                ->whereIn('id', $variableIds)
                ->get(['id', 'name', 'units'])
                ->keyBy('id');

            // Map variables to their corresponding values from JSON
            foreach ($contractVariablesArray as $variableId => $variableData) {
                if (!isset($variables[$variableId])) {
                    continue; // Skip if the variable ID doesn't exist in contractVariables table
                }

                $variable = $variables[$variableId];

                // Extract contract variable value
                $variableValue = isset($variableData['contractVariableValue'])
                    ? (is_array($variableData['contractVariableValue'])
                        ? json_encode($variableData['contractVariableValue']) // Convert array to JSON string
                        : $variableData['contractVariableValue'])
                    : null;

                // Decode JSON only if it's a string
                $decodedValue = is_string($variableValue) ? json_decode($variableValue, true) : null;

                // If it's an array, extract only the value corresponding to startDateParam
                if (is_array($decodedValue)) {
                    $filteredValue = $decodedValue[$dateKey] ?? null;
                } else {
                    $filteredValue = $variableValue; // If it's already a number, use it directly
                }

                // Unique key to prevent duplicates
                $uniqueKey = "{$contractNumber}_{$contractCalculationNumber}_{$variableId}";

                if (!isset($uniqueEntries[$uniqueKey])) {
                    $uniqueEntries[$uniqueKey] = true;

                    // Add row to report
                    $reportData[] = [
                        'contrato' => $contractNumber,
                        'idDeCalculo' => $contractCalculationNumber,
                        'centrosDeCarga' => $centrosDeCargaParam ?? '',
                        'centralesElectricas' => $centralNamesById[$centralesElectricasParam]
                            ?? $centralesElectricasParam ?? '',
                        'nombreDeVariable' => $variable->name,
                        'idDeVariable' => $variableId,
                        'fechaInicio' => $startDateParam,
                        'valor' => $filteredValue,
                        'unidades' => $variable->units,
                        'created_at' => $created_at,
                        'updated_at' => $updated_at,
                        'report_created_at' => $currentDateTime,
                    ];
                }
            }
        }

        // Insert into MySQL table after ensuring uniqueness
        if (!empty($reportData)) {
            echo 'doing truncate';
            echo PHP_EOL;
            DB::connection('mysql_dev_2')
            ->statement("TRUNCATE TABLE reporteDeVariablesDeCalculoDeContrato");

            // Execute insert in chunks intead of single insert
            $chunkSize = 1000;
            // Split data in chunks
            $chunks = array_chunk($reportData, $chunkSize);
            
            foreach ($chunks as $index => $chunk) {
                try {
                    DB::connection('mysql_dev_2')
                    ->table('reporteDeVariablesDeCalculoDeContrato')
                    ->insert($chunk);
                } catch (Exception $e) {
                    echo $e->getMessage();
                    return;
                }
            }
        }

        echo 'all good';
        echo PHP_EOL;
        $this->generateComponentsReportCsv();
    }

    /**
     * Generate CSV file and load data for Concepts Report using SQL*Loader.
     *
     * Reads data from MySQL reporteDeConceptosDeCaluloDeContrato table,
     * generates a pipe-delimited CSV file, creates CTL control file,
     * and loads data into Oracle using sqlldr.
     *
     * @return void
     * @throws Exception If CSV/CTL file generation or SQL*Loader execution fails
     */
    public function generateConceptsReportCsv()
    {
        echo "Generating Concepts Report CSV using SQL*Loader...\n";

        $query = "SELECT contrato, idDeCalculo, fechaInicio, fechaFin, centrosDeCarga,
                NombreDelCalculo, CategoriaOSeccion, InstrumentoOProducto,
                componentId, Cantidad, UnidadFact, Monto, Divisa, IVA,
                centralesElectricas, componentType, created_at, updated_at,
                report_created_at, Precio, UnidadComponente
            FROM reporteDeConceptosDeCaluloDeContrato";

        $queryResult = collect(DB::connection('mysql_dev_2')->select($query))->toArray();

        if (empty($queryResult)) {
            echo "No records found.\n";
            return;
        }

        echo "Total records: " . count($queryResult) . "\n";

        // Prepare export data
        $csvData = [];
        foreach ($queryResult as $row) {
            $csvData[] = [
                $row->contrato,
                $row->idDeCalculo,
                $row->fechaInicio,
                $row->fechaFin,
                $row->centrosDeCarga,
                $row->NombreDelCalculo,
                $row->CategoriaOSeccion,
                $row->InstrumentoOProducto,
                $row->componentId,
                $row->Cantidad,
                $row->UnidadFact,
                $row->Monto,
                $row->Divisa,
                $row->IVA,
                $row->centralesElectricas,
                $row->componentType,
                $row->created_at,
                $row->updated_at,
                $row->report_created_at,
                $row->Precio,
                $row->UnidadComponente
            ];
        }

        // Set file path
        $baseDir = public_path() . "/recurringData/sqlFiles";
        $csvFile = "{$baseDir}/conceptsReport.csv";
        $ctlFile = "{$baseDir}/conceptsReport.ctl";

        // Create CSV file
        $rowCount = $this->writeCsvFile($csvFile, $csvData, 21);
        echo "Generated CSV with {$rowCount} rows\n";

        // Create CTL file
        $this->generateConceptsCtlFile($ctlFile, 'conceptsReport.csv');

        // Execute insert using sqlldr
        $this->executeOracleDataLoad($csvFile, $ctlFile, 'Concepts Report');

        echo "Concepts Report loaded successfully using SQL*Loader.\n";
    }

    /**
     * Generate CSV file and load data for Components Report using SQL*Loader.
     *
     * Reads data from MySQL reporteDeVariablesDeCalculoDeContrato table,
     * generates a pipe-delimited CSV file, creates CTL control file,
     * and loads data into Oracle using sqlldr.
     *
     * @return void
     * @throws Exception If CSV/CTL file generation or SQL*Loader execution fails
     */
    public function generateComponentsReportCsv()
    {
        echo "Generating Components Report CSV using SQL*Loader...\n";

        $query = "SELECT contrato, idDeCalculo, centrosDeCarga, nombreDeVariable,
                idDeVariable, valor, unidades, fechaInicio, centralesElectricas,
                created_at, updated_at, report_created_at
            FROM reporteDeVariablesDeCalculoDeContrato";

        $queryResult = collect(DB::connection('mysql_dev_2')->select($query))->toArray();

        if (empty($queryResult)) {
            echo "No records found.\n";
            return;
        }

        echo "Total records: " . count($queryResult) . "\n";

        // Prepare CSV data
        $csvData = [];
        foreach ($queryResult as $row) {
            $csvData[] = [
                $row->contrato,
                $row->idDeCalculo,
                $row->centrosDeCarga,
                $row->nombreDeVariable,
                $row->idDeVariable,
                $row->valor,
                $row->unidades,
                $row->fechaInicio,
                $row->centralesElectricas,
                $row->created_at,
                $row->updated_at,
                $row->report_created_at
            ];
        }

        // File path
        $baseDir = public_path() . "/recurringData/sqlFiles";
        $csvFile = "{$baseDir}/componentsReport.csv";
        $ctlFile = "{$baseDir}/componentsReport.ctl";

        // Create CSV file
        $rowCount = $this->writeCsvFile($csvFile, $csvData, 12);
        echo "Generated CSV with {$rowCount} rows\n";

        // Create CTL file
        $this->generateComponentsCtlFile($ctlFile, 'componentsReport.csv');

        // Execute insert using sqlldr
        $this->executeOracleDataLoad($csvFile, $ctlFile, 'Components Report');

        echo "Components Report loaded successfully using SQL*Loader.\n";
    }

    /**
     * Execute Oracle data load using SQL*Loader (sqlldr).
     *
     * Loads data from CSV file into Oracle database table using sqlldr with DIRECT mode.
     * Validates files exist, executes sqlldr command, and handles errors with .bad file reporting.
     *
     * @param string $csvFile Full path to the CSV data file
     * @param string $ctlFile Full path to the CTL control file
     * @param string $reportName Name of the report for logging purposes
     * @return void
     * @throws Exception If files not found or SQL*Loader fails
     */
    public function executeOracleDataLoad($csvFile, $ctlFile, $reportName)
    {
        echo "In executeOracleDataLoad for {$reportName}" . PHP_EOL;

        // Oracle connection settings
        $oracleUsername = config('app.KUALION_DB_USERNAME', '');
        $oraclePassword = config('app.KUALION_DB_PASSWORD', '');
        $oracleHost = config('app.KUALION_DB_HOST', '');
        $oraclePort = config('app.KUALION_DB_PORT', '1521');
        $oracleServiceName = config('app.KUALION_DB_SERVICE_NAME', '');

        // library paths
        $oracleClientPath = "/opt/instantclient";
        $sqlldrPath = "{$oracleClientPath}/sqlldr";

        // Create connection string for ocracle connection
        $connectionString = "{$oracleUsername}/{$oraclePassword}@//{$oracleHost}:{$oraclePort}/{$oracleServiceName}";

        // Set log and errors file path
        $logFile = str_replace('.csv', '.log', $csvFile);
        $badFile = str_replace('.csv', '.bad', $csvFile);

        if (!file_exists($csvFile)) {
            throw new Exception("CSV file not found: {$csvFile}");
        }

        if (!file_exists($ctlFile)) {
            throw new Exception("CTL file not found: {$ctlFile}");
        }

        echo "CSV file: {$csvFile}\n";
        echo "CTL file: {$ctlFile}\n";
        echo "Log file: {$logFile}\n";

        $sqlldrCommand = sprintf(
            "ORACLE_HOME=%s LD_LIBRARY_PATH=%s NLS_LANG=AMERICAN_AMERICA.AL32UTF8 %s %s control=%s data=%s log=%s bad=%s errors=0 2>&1",
            escapeshellarg($oracleClientPath),
            escapeshellarg($oracleClientPath),
            escapeshellarg($sqlldrPath),
            escapeshellarg($connectionString),
            escapeshellarg($ctlFile),
            escapeshellarg($csvFile),
            escapeshellarg($logFile),
            escapeshellarg($badFile)
        );

        echo "Executing sqlldr for {$reportName}...\n";

        $output = [];
        $returnVar = 0;
        exec($sqlldrCommand, $output, $returnVar);

        // sqlldr return codes: 0=success, 1=fail, 2=warnings (OK), 3=fatal
        if ($returnVar === 0 || $returnVar === 2) {
            echo "SQL*Loader completed successfully for {$reportName}\n";

            // Print summary
            if (file_exists($logFile)) {
                $logContent = file_get_contents($logFile);
                if (preg_match('/(\d+) Rows successfully loaded/', $logContent, $matches)) {
                    echo "Rows loaded: {$matches[1]}\n";
                }
            }
        } else {
            echo "Error executing SQL*Loader for {$reportName}. Return code: {$returnVar}\n";
            echo "Output:\n" . implode("\n", $output) . "\n";

            // Print bad file
            if (file_exists($badFile) && filesize($badFile) > 0) {
                echo "Bad records found in: {$badFile}\n";
            }

            throw new Exception("SQL*Loader failed for {$reportName}");
        }
    }

    /**
     * Execute Oracle SQL script using SQL*Plus (LEGACY method).
     *
     * Tests Oracle connection, then executes a SQL script file via SQL*Plus.
     * Used by the old INSERT-based approach.
     *
     * @param string $scriptFile Name of the SQL script file (without path)
     * @return void
     */
    public function executeOracleInsertScript($scriptFile)
    {
        echo 'In executeOracleInsertScript' . PHP_EOL;
        // Oracle connection details
        $oracleUsername = config('app.KUALION_DB_USERNAME', '');
        $oraclePassword = config('app.KUALION_DB_PASSWORD', '');
        $oracleHost = config('app.KUALION_DB_HOST', '');
        $oraclePort = config('app.KUALION_DB_PORT', '1521');
        $oracleServiceName = config('app.KUALION_DB_SERVICE_NAME', '');

        // Binary file path
        $oracleSqlPlusPath = "/opt/instantclient/sqlplus";

        $oracleEnv = 'ORACLE_HOME=/opt/instantclient LD_LIBRARY_PATH=/opt/instantclient';

        $connectionString = sprintf(
            '%s/%s@//%s:%s/%s',
            escapeshellarg($oracleUsername),
            escapeshellarg($oraclePassword),
            escapeshellarg($oracleHost),
            escapeshellarg($oraclePort),
            escapeshellarg($oracleServiceName)
        );
        // First, test the SQL*Plus login
        $testLoginCommand = sprintf(
            "echo 'exit' | %s NLS_LANG=AMERICAN_AMERICA.AL32UTF8 timeout 15 %s -S %s 2>&1",
            $oracleEnv,
            $oracleSqlPlusPath,
            $connectionString
        );

        echo "Testing Oracle connection...\n";

        $output = [];
        $returnVar = 0;
        exec($testLoginCommand, $output, $returnVar);

        // Check if the login was successful
        if ($returnVar !== 0) {
            echo "Oracle connection test failed! Error details:\n";
            echo implode("\n", $output);
            exit;
        }

        echo "Oracle connection successful.\n";

        // Path to the generated SQL script
        $insertSqlFile = public_path() . "/recurringData/sqlFiles/{$scriptFile}";

        if (!file_exists($insertSqlFile)) {
            echo "Error: SQL script file not found: {$scriptFile}";
            exit;
        }

        // Construct the SQL*Plus command with NLS_LANG to enforce UTF-8
        $sqlplusCommand = sprintf(
            "%s NLS_LANG=AMERICAN_AMERICA.AL32UTF8 timeout 300 %s -S %s @%s",
            $oracleEnv,
            $oracleSqlPlusPath,
            $connectionString,
            escapeshellarg($insertSqlFile)
        );

        echo "Executing: {$sqlplusCommand}\n";

        // Execute the script and capture both stdout & stderr
        $output = [];
        $returnVar = 0;
        exec($sqlplusCommand . ' 2>&1', $output, $returnVar);

        // Check execution result
        if ($returnVar === 0) {
            echo "Oracle SQL script executed successfully: {$scriptFile}\n";
        } else {
            echo "Error executing Oracle SQL script: {$scriptFile}. Output:\n";
            echo implode("\n", $output);
        }
    }

    /**
     * Format value for CSV export (pipe-delimited, quoted).
     *
     * Converts values to strings suitable for CSV export. Handles null values,
     * DateTime objects, and other data types.
     *
     * @param mixed $value The value to format
     * @return string Formatted value as string, empty string if null
     */
    private function formatCsvValue($value)
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s.u');
        }

        return (string) $value;
    }

    /**
     * Write data to CSV file with pipe delimiter.
     *
     * Generates a pipe-delimited CSV file with quoted values.
     * Optionally validates that all rows have the expected column count.
     *
     * @param string $filePath Full path where CSV file will be created
     * @param array $data Array of rows, where each row is an array of values
     * @param int|null $columnCount Expected number of columns per row (optional validation)
     * @return int Number of rows written to the file
     * @throws Exception If file cannot be opened or column count validation fails
     */
    private function writeCsvFile($filePath, $data, $columnCount = null)
    {
        $handle = fopen($filePath, 'w');
        if ($handle === false) {
            throw new Exception("Failed to open CSV file: {$filePath}");
        }

        $rowCount = 0;
        foreach ($data as $row) {
            // Convert row to array of formatted values
            $csvRow = array_map([$this, 'formatCsvValue'], $row);

            // Validate column count if specified
            if ($columnCount !== null && count($csvRow) !== $columnCount) {
                fclose($handle);
                throw new Exception("Row has " . count($csvRow) . " columns, expected {$columnCount}");
            }

            // Write pipe-delimited, quoted row
            fputcsv($handle, $csvRow, '|', '"');
            $rowCount++;
        }

        fclose($handle);
        return $rowCount;
    }

    /**
     * Generate CTL control file for Concepts Report.
     *
     * Creates a SQL*Loader control file that defines the structure for loading
     * the Concepts Report CSV into Oracle REPORTEDECONCEPTOSDECALULODECONTRATO table.
     *
     * @param string $ctlFilePath Full path where CTL file will be created
     * @param string $csvFileName Name of the CSV file to reference in CTL
     * @return void
     */
    private function generateConceptsCtlFile($ctlFilePath, $csvFileName)
    {
        $ctlContent = <<<CTL
OPTIONS (SKIP=0, DIRECT=TRUE)
LOAD DATA
INFILE '{$csvFileName}'
TRUNCATE
INTO TABLE REPORTEDECONCEPTOSDECALULODECONTRATO
FIELDS TERMINATED BY '|' OPTIONALLY ENCLOSED BY '"'
TRAILING NULLCOLS
(
    CONTRATO,
    IDDECALCULO,
    FECHAINICIO DATE "YYYY-MM-DD",
    FECHAFIN DATE "YYYY-MM-DD",
    CENTROSDECARGA CHAR(32767),
    NOMBREDELCALCULO,
    CATEGORIAOSECCION,
    INSTRUMENTOOPRODUCTO,
    COMPONENTID,
    CANTIDAD,
    UNIDADFACT,
    MONTO,
    DIVISA,
    IVA,
    CENTRALESELECTRICAS CHAR(32767),
    COMPONENTTYPE,
    CREATED_AT TIMESTAMP "YYYY-MM-DD HH24:MI:SS.FF",
    UPDATED_AT TIMESTAMP "YYYY-MM-DD HH24:MI:SS.FF",
    REPORT_CREATED_AT TIMESTAMP "YYYY-MM-DD HH24:MI:SS.FF",
    PRECIO,
    UNIDADCOMPONENTE
)
CTL;

        file_put_contents($ctlFilePath, $ctlContent);

        if (file_exists($ctlFilePath)) {
            chmod($ctlFilePath, 0755);
        }

        echo "Generated CTL file: {$ctlFilePath}\n";
    }

    /**
     * Generate CTL control file for Components Report.
     *
     * Creates a SQL*Loader control file that defines the structure for loading
     * the Components Report CSV into Oracle REPORTEDEVARIABLESDECALCULODECONTRATO table.
     *
     * @param string $ctlFilePath Full path where CTL file will be created
     * @param string $csvFileName Name of the CSV file to reference in CTL
     * @return void
     */
    private function generateComponentsCtlFile($ctlFilePath, $csvFileName)
    {
        $ctlContent = <<<CTL
OPTIONS (SKIP=0, DIRECT=TRUE)
LOAD DATA
INFILE '{$csvFileName}'
TRUNCATE
INTO TABLE REPORTEDEVARIABLESDECALCULODECONTRATO
FIELDS TERMINATED BY '|' OPTIONALLY ENCLOSED BY '"'
TRAILING NULLCOLS
(
    CONTRATO,
    IDDECALCULO,
    CENTROSDECARGA CHAR(32767),
    NOMBREDEVARIABLE,
    IDDEVARIABLE,
    VALOR CHAR(32767),
    UNIDADES,
    FECHAINICIO DATE "YYYY-MM-DD",
    CENTRALESELECTRICAS CHAR(32767),
    CREATED_AT TIMESTAMP "YYYY-MM-DD HH24:MI:SS.FF",
    UPDATED_AT TIMESTAMP "YYYY-MM-DD HH24:MI:SS.FF",
    REPORT_CREATED_AT TIMESTAMP "YYYY-MM-DD HH24:MI:SS.FF"
)
CTL;

        file_put_contents($ctlFilePath, $ctlContent);

        if (file_exists($ctlFilePath)) {
            chmod($ctlFilePath, 0755);
        }

        echo "Generated CTL file: {$ctlFilePath}\n";
    }
}
