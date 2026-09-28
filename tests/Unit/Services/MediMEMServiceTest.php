<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Http\Services\MediMEMService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionClass;
use Exception;

class MediMEMServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper method to invoke protected methods
     */
    protected function invokeProtectedMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Helper method to set protected property
     */
    protected function setProtectedProperty($object, $propertyName, $value)
    {
        $reflection = new ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Helper method to get protected property
     */
    protected function getProtectedProperty($object, $propertyName)
    {
        $reflection = new ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * Test constructor initializes MediMEM-specific properties
     */
    public function test_constructor_initializes_medimem_properties()
    {
        // Mock config() helper calls with Mockery::any() for default parameter
        Config::shouldReceive('get')
            ->with('app.MEDIMEM_API_ENDPOINT', Mockery::any())
            ->andReturn('https://api.medimem.com');
        Config::shouldReceive('get')
            ->with('app.MEDIMEM_EMAIL_NOTIFICATIONS', Mockery::any())
            ->andReturn('alerts@medimem.com');

        $service = new MediMEMService();

        $baseUrl = $this->getProtectedProperty($service, 'baseUrl');
        $logFilePath = $this->getProtectedProperty($service, 'logFilePath');
        $emailNotification = $this->getProtectedProperty($service, 'emailNotification');

        $this->assertEquals('https://api.medimem.com', $baseUrl);
        $this->assertStringContainsString('medimem_errors.log', $logFilePath);
        $this->assertEquals('alerts@medimem.com', $emailNotification);
    }

    /**
     * Test constructor creates Guzzle client
     */
    public function test_constructor_creates_client()
    {
        Config::shouldReceive('get')->andReturn('');

        $service = new MediMEMService();

        $client = $this->getProtectedProperty($service, 'client');

        $this->assertInstanceOf(Client::class, $client);
    }

    /**
     * Test getRPUMeasurements builds correct endpoint
     */
    public function test_get_rpu_measurements_builds_endpoint()
    {
        // Use Mockery::any() for second parameter as config() may pass null
        Config::shouldReceive('get')
            ->with('app.MEDIMEM_API_ENDPOINT', Mockery::any())
            ->andReturn('https://api.medimem.com');
        Config::shouldReceive('get')
            ->with('app.MEDIMEM_EMAIL_NOTIFICATIONS', Mockery::any())
            ->andReturn('');

        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
        $mockResponse->shouldReceive('getBody->getContents')->andReturn('{"measurements":[]}');

        $mockClient->shouldReceive('get')
            ->with(
                'https://api.medimem.com/medimem/4.0.0/lecturas/RPU-123/2025-01-01/2025-01-31',
                Mockery::on(function ($options) {
                    return isset($options['headers']['Authorization']) &&
                           $options['headers']['Authorization'] === 'Bearer test-token-123' &&
                           $options['headers']['accept'] === 'application/json';
                })
            )
            ->andReturn($mockResponse);

        $service = new MediMEMService();
        $this->setProtectedProperty($service, 'client', $mockClient);

        $result = $service->getRPUMeasurements('RPU-123', '2025-01-01', '2025-01-31', 'test-token-123', false);

        $this->assertIsObject($result);
    }

    /**
     * Test getRPUMeasurements returns measurements data
     */
    public function test_get_rpu_measurements_returns_data()
    {
        Config::shouldReceive('get')
            ->with('app.MEDIMEM_API_ENDPOINT', Mockery::any())
            ->andReturn('https://api.medimem.com');
        Config::shouldReceive('get')
            ->with('app.MEDIMEM_EMAIL_NOTIFICATIONS', Mockery::any())
            ->andReturn('');

        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
        $mockResponse->shouldReceive('getBody->getContents')->andReturn('{"measurements":[{"value":100}]}');
        $mockClient->shouldReceive('get')->andReturn($mockResponse);

        $service = new MediMEMService();
        $this->setProtectedProperty($service, 'client', $mockClient);

        $result = $service->getRPUMeasurements('RPU-456', '2025-02-01', '2025-02-28', 'token-abc', false);

        $this->assertIsObject($result);
        $this->assertIsArray($result->measurements);
        $this->assertEquals(100, $result->measurements[0]->value);
    }

    /**
     * Test request GET method with authorization header
     */
    public function test_request_get_with_authorization()
    {
        Config::shouldReceive('get')->andReturn('');

        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
        $mockResponse->shouldReceive('getBody->getContents')->andReturn('{"status":"success"}');

        $mockClient->shouldReceive('get')
            ->with(
                Mockery::type('string'),
                Mockery::on(function ($options) {
                    return $options['headers']['Authorization'] === 'Bearer my-secret-token' &&
                           $options['headers']['accept'] === 'application/json';
                })
            )
            ->andReturn($mockResponse);

        $service = new MediMEMService();
        $this->setProtectedProperty($service, 'client', $mockClient);
        $this->setProtectedProperty($service, 'baseUrl', 'https://api.test.com');

        $result = $this->invokeProtectedMethod($service, 'request', ['GET', '/endpoint', 'my-secret-token', false]);

        $this->assertIsObject($result);
    }

    /**
     * Test request unsupported method throws exception
     */
    public function test_request_unsupported_method()
    {
        Config::shouldReceive('get')->andReturn('');
        Log::shouldReceive('channel')->with('task_errors')->andReturnSelf();
        Log::shouldReceive('error')->andReturn(true);

        $service = new MediMEMService();
        $this->setProtectedProperty($service, 'baseUrl', 'https://api.test.com');

        $result = $this->invokeProtectedMethod($service, 'request', ['POST', '/endpoint', 'token', false]);

        $this->assertNull($result);
    }

    /**
     * Test request exception handling
     */
    public function test_request_exception_handling()
    {
        Config::shouldReceive('get')->andReturn('');
        Log::shouldReceive('channel')->with('task_errors')->andReturnSelf();
        Log::shouldReceive('error')->once()->with(Mockery::pattern('/Error HTTP MediMEM API/'));

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('get')->andThrow(new Exception('Network error', 500));

        $service = new MediMEMService();
        $this->setProtectedProperty($service, 'client', $mockClient);
        $this->setProtectedProperty($service, 'baseUrl', 'https://api.test.com');

        $result = $this->invokeProtectedMethod($service, 'request', ['GET', '/test', 'token', false]);

        $this->assertNull($result);
    }

    /**
     * Test request logs error code and message
     */
    public function test_request_logs_error_details()
    {
        Config::shouldReceive('get')->andReturn('');
        Log::shouldReceive('channel')->with('task_errors')->andReturnSelf();
        Log::shouldReceive('error')->once()->with(Mockery::pattern('/\[404\].*Not Found/'));

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('get')->andThrow(new Exception('Not Found', 404));

        $service = new MediMEMService();
        $this->setProtectedProperty($service, 'client', $mockClient);
        $this->setProtectedProperty($service, 'baseUrl', 'https://api.test.com');

        $result = $this->invokeProtectedMethod($service, 'request', ['GET', '/missing', 'token', false]);

        $this->assertNull($result);
    }

    /**
     * Test getRPUMeasurements with different date formats
     */
    public function test_get_rpu_measurements_different_dates()
    {
        Config::shouldReceive('get')
            ->with('app.MEDIMEM_API_ENDPOINT', Mockery::any())
            ->andReturn('https://api.medimem.com');
        Config::shouldReceive('get')
            ->with('app.MEDIMEM_EMAIL_NOTIFICATIONS', Mockery::any())
            ->andReturn('');

        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
        $mockResponse->shouldReceive('getBody->getContents')->andReturn('{"data":[]}');

        $mockClient->shouldReceive('get')
            ->with(
                'https://api.medimem.com/medimem/4.0.0/lecturas/RPU-999/2025-12-01/2025-12-31',
                Mockery::any()
            )
            ->andReturn($mockResponse);

        $service = new MediMEMService();
        $this->setProtectedProperty($service, 'client', $mockClient);

        $result = $service->getRPUMeasurements('RPU-999', '2025-12-01', '2025-12-31', 'token', false);

        $this->assertIsObject($result);
    }

    /**
     * Test service extends BaseHTTPService
     */
    public function test_service_extends_base_http_service()
    {
        Config::shouldReceive('get')->andReturn('');

        $service = new MediMEMService();

        $this->assertInstanceOf(\App\Http\Services\BaseHTTPService::class, $service);
    }

    /**
     * Test getRPUMeasurements passes sendErrorEmail parameter
     */
    public function test_get_rpu_measurements_send_error_email_parameter()
    {
        Config::shouldReceive('get')->with('app.MEDIMEM_API_ENDPOINT', Mockery::any())->andReturn('https://api.medimem.com');
        Config::shouldReceive('get')->with('app.MEDIMEM_EMAIL_NOTIFICATIONS', Mockery::any())->andReturn('');

        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
        $mockResponse->shouldReceive('getBody->getContents')->andReturn('{}');
        $mockClient->shouldReceive('get')->andReturn($mockResponse);

        $service = new MediMEMService();
        $this->setProtectedProperty($service, 'client', $mockClient);

        // Test with sendErrorEmail = false
        $result = $service->getRPUMeasurements('RPU-001', '2025-01-01', '2025-01-31', 'token', false);
        $this->assertIsObject($result);

        // Test with sendErrorEmail = true
        $result = $service->getRPUMeasurements('RPU-001', '2025-01-01', '2025-01-31', 'token', true);
        $this->assertIsObject($result);
    }

    /**
     * Test processResponse inherited from parent
     */
    public function test_process_response_inherited()
    {
        Config::shouldReceive('get')->andReturn('');

        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
        $mockResponse->shouldReceive('getBody->getContents')->andReturn('{"inherited":true}');
        $mockClient->shouldReceive('get')->andReturn($mockResponse);

        $service = new MediMEMService();
        $this->setProtectedProperty($service, 'client', $mockClient);
        $this->setProtectedProperty($service, 'baseUrl', 'https://api.test.com');

        $result = $this->invokeProtectedMethod($service, 'request', ['GET', '/test', 'token', false]);

        $this->assertIsObject($result);
        $this->assertTrue($result->inherited);
    }

    /**
     * Test instance type
     */
    public function test_instance_is_medimem_service()
    {
        Config::shouldReceive('get')->andReturn('');

        $service = new MediMEMService();

        $this->assertInstanceOf(MediMEMService::class, $service);
    }
}
