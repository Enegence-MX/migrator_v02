<?php

namespace App\Http\Repositories;

use Exception;
use Illuminate\Support\Facades\DB;

class AccionaInvoiceRepository extends SimsaInvoiceRepository
{
    // Override properties specific to Acciona
    protected $teamIds = [500, 508, 516, 524];
    protected $companyName = 'ACCIONA';
    protected $loggerFileName = 'acciona_invoice_sync';
    protected $invoceSCTableName = 'acciona_sc_invoices';
    protected $invoceMEMTableName = 'acciona_mem_invoices';

    /**
     * Constructor to initialize database connections and date range
     * Inherits most logic from parent, only overrides target connection
     *
     * @param string|null $startDate Start date for invoice sync (Y-m-d format)
     * @param string|null $endDate   End date for invoice sync (Y-m-d format)
     */
    public function __construct($startDate = null, $endDate = null)
    {
        // Call parent constructor to handle common initialization
        parent::__construct($startDate, $endDate);

        // Override target connection for Acciona
        $this->targetConnection = DB::connection('mysql_gcp_acciona_target');
        $this->apiEmail = config('app.ACCIONA_SMART_EMAIL');
        $this->apiPassword = config('app.ACCIONA_SMART_PASSWORD');

        // Initialize teamId-specific credentials
        $this->teamCredentials = [
            500 => [
                'email' => config('app.ACCIONA_SMART_EMAIL_TEAMID_500', null),
                'password' => config('app.ACCIONA_SMART_PASSWORD_TEAMID_500', null)
            ],
            508 => [
                'email' => config('app.ACCIONA_SMART_EMAIL_TEAMID_508', null),
                'password' => config('app.ACCIONA_SMART_PASSWORD_TEAMID_508', null)
            ],
            516 => [
                'email' => config('app.ACCIONA_SMART_EMAIL_TEAMID_516', null),
                'password' => config('app.ACCIONA_SMART_PASSWORD_TEAMID_516', null)
            ],
            524 => [
                'email' => config('app.ACCIONA_SMART_EMAIL_TEAMID_524', null),
                'password' => config('app.ACCIONA_SMART_PASSWORD_TEAMID_524', null)
            ]
        ];
    }
}
