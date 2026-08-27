<?php
/**
 * MediMEM serivice file.
 *
 * PHP Version 7.4
 *
 * @category Services
 * @package  Service
 * @author   MCK developer <programacion@mck.agency>
 * @license  Apache 2 https://www.apache.org/licenses/LICENSE-2.0
 * @link     ''
 */

namespace App\Http\Services;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Service to request rpu|rmu measurements.
 * Contains MediMeme service for CFE distribucion services.
 *
 * @category Services
 * @package  Service
 * @author   MCK developer <programacion@mck.agency>
 * @license  Apache 2 https://www.apache.org/licenses/LICENSE-2.0
 * @link     ''
 */
class MediMEMService extends BaseHTTPService
{
    protected $baseUrl;
    protected $apiKey;
    protected $client;
    protected $logFilePath;
    protected $emailNotification;

    /**
     * Construct function.
     */
    public function __construct()
    {
        $this->baseUrl           = config('app.MEDIMEM_API_ENDPOINT');
        $this->client            = new Client();
        $this->logFilePath       = storage_path('logs/medimem_errors.log');
        $this->emailNotification = config('app.MEDIMEM_EMAIL_NOTIFICATIONS');
    }

    /**
     * Public function to create and make http request
     * for CFE distibution measurements.
     *
     * @param $key            String  RPU|RMU key.
     * @param $fechaInicio    Date    Initial date for filter.
     * @param $fechaFin       Date    Ending date for filter.
     * @param $token          String  MediMEM http token for header.
     * @param $sendErrorEmail boolean Flag to enable or disable email error notification.
     *
     * @return jsonObjectList
     */
    public function getRPUMeasurements($key, $fechaInicio, $fechaFin, $token, $sendErrorEmail = true)
    {
        $endpoint = "/medimem/4.0.0/lecturas/$key/$fechaInicio/$fechaFin";
        return $this->request('GET', $endpoint, $token, $sendErrorEmail);
    }

    /**
     * Function to make http request.
     *
     * @param $method         String  Only suports GET method.
     * @param $endpoint       String  Url endpoint without domain.
     * @param $token          String  MediMEM http token for header.
     * @param $sendErrorEmail boolean Flag to enable or disable email error notification.
     *
     * @return jsonObjectList
     */
    protected function request($method, $endpoint, $token = null, $sendErrorEmail = true)
    {
        try {
            $response = null;
            switch (strtoupper($method)) {
                case 'GET':
                    $response = $this->client->get(
                        $this->baseUrl . $endpoint,
                        [
                        'headers' => [
                            'Authorization' => "Bearer $token",
                            'accept'        => 'application/json',
                        ],
                        ]
                    );
                    break;
                default:
                    throw new Exception($method . " not implemented", 1);
            }
            return $this->processResponse($response, $endpoint);
        } catch (Exception $e) {
            Log::channel('task_errors')->error($e);
            $this->sendError(
                $e->getMessage(),
                $endpoint,
                $sendErrorEmail
            );
            return null;
        }
    }
}
