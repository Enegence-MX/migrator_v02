<?php
/**
 * Base Service for HTTP request.
 *
 * PHP Version 7.4
 *
 * @category Services
 * @package  Service
 * @author   Saul Alonso <alonso.ibarra@mck.agency>
 * @license  Apache 2 https://www.apache.org/licenses/LICENSE-2.0
 * @link     ''
 */

namespace App\Http\Services;

use App\Http\Helpers\MailHelper;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Service to request rpu|rmu measurements.
 * Contains MediMeme service for CFE distribucion services.
 *
 * @category Services
 * @package  Service
 * @author   Saul Alonso <alonso.ibarra@mck.agency>
 * @license  Apache 2 https://www.apache.org/licenses/LICENSE-2.0
 * @link     ''
 */
class BaseHTTPService
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
        $this->client            = new Client();
        $this->logFilePath       = storage_path('logs/service_errors.log');
        $this->emailNotification = config('app.EMAIL_NOTIFICATIONS', '');
    }

    /**
     * Function to make custom http request.
     *
     * @param $method         String  GET|POST|PUT method.
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
                    );
                    break;

                default:
                    throw new Exception($method." not implemented", 1);
            }

            return $this->processResponse($response, $endpoint, $sendErrorEmail);
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

    /**
     * Function for custom parsing response data.
     *
     * @param $response       HttpResponse Object.
     * @param $endpoint       String       Url endpoint.
     * @param $sendErrorEmail boolean      Flag to enable or disable email error notification.
     *
     * @return jsonObjectList
     */
    protected function processResponse($response, $endpoint, $sendErrorEmail = true)
    {
        if ($response->getStatusCode() == 200) {
            return json_decode($response->getBody()->getContents());
        }
        $this->sendError('Respuesta no exitosa', $endpoint, $sendErrorEmail);
        return null;
    }

    /**
     * Function to send error email and add error to log file.
     *
     * @param $message        String  Error string.
     * @param $endpoint       String  Endpoint that rise the error.
     * @param $endpoint       String  Endpoint that rise the error.
     * @param $sendErrorEmail boolean Flag to enable or disable email error notification.
     *
     * @return void
     */
    protected function sendError($message, $endpoint, $sendErrorEmail = true)
    {
        $data = [
            'subject' => 'Error en lectura de endpoint',
            'info'    => $message,
            'url'     => $this->baseUrl . $endpoint,
        ];
        if ($sendErrorEmail) {
            MailHelper::sendMedimemError($this->emailNotification, $data);
        }
    }
}
