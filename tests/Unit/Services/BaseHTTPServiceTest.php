<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Http\Services\BaseHTTPService;
use App\Http\Helpers\MailHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionClass;
use Exception;

class BaseHTTPServiceTest extends TestCase
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
     * Test constructor initializes properties
     */
    public function test_constructor_initializes_properties()
    {
        Config::shouldReceive('get')->with('app.EMAIL_NOTIFICATIONS', '')->andReturn('test@example.com');

        $service = new BaseHTTPService();

        $client = $this->getProtectedProperty($service, 'client');
        $logFilePath = $this->getProtectedProperty($service, 'logFilePath');
        $emailNotification = $this->getProtectedProperty($service, 'emailNotification');

        $this->assertInstanceOf(Client::class, $client);
        $this->assertStringContainsString('storage', $logFilePath);
        $this->assertStringContainsString('service_errors.log', $logFilePath);
        $this->assertEquals('test@example.com', $emailNotification);
    }

    /**
     * Test request GET method success
     */
    public function test_request_get_method_success()
    {
        Config::shouldReceive('get')->with('app.EMAIL_NOTIFICATIONS', '')->andReturn('test@example.com');

        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
        $mockResponse->shouldReceive('getBody->getContents')->andReturn('{"data":"test"}');
        $mockClient->shouldReceive('get')->andReturn($mockResponse);

        $service = new BaseHTTPService();
        $this->setProtectedProperty($service, 'client', $mockClient);
        $this->setProtectedProperty($service, 'baseUrl', 'https://api.example.com');

        $result = $this->invokeProtectedMethod($service, 'request', ['GET', '/test-endpoint']);

        $this->assertIsObject($result);
        $this->assertEquals('test', $result->data);
    }

    /**
     * Test request GET method with exception
     */
    public function test_request_get_method_with_exception()
    {
        Config::shouldReceive('get')->with('app.EMAIL_NOTIFICATIONS', '')->andReturn('test@example.com');
        Log::shouldReceive('channel')->with('task_errors')->andReturnSelf();
        Log::shouldReceive('error')->andReturn(true);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('get')->andThrow(new Exception('Connection failed'));

        $service = Mockery::mock(BaseHTTPService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $this->setProtectedProperty($service, 'client', $mockClient);
        $this->setProtectedProperty($service, 'baseUrl', 'https://api.example.com');
        $service->shouldReceive('sendError')->once();

        $result = $this->invokeProtectedMethod($service, 'request', ['GET', '/test-endpoint', null, false]);

        $this->assertNull($result);
    }

    /**
     * Test request unsupported method throws exception
     */
    public function test_request_unsupported_method_throws_exception()
    {
        Config::shouldReceive('get')->with('app.EMAIL_NOTIFICATIONS', '')->andReturn('test@example.com');
        Log::shouldReceive('channel')->with('task_errors')->andReturnSelf();
        Log::shouldReceive('error')->andReturn(true);

        $service = Mockery::mock(BaseHTTPService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $this->setProtectedProperty($service, 'baseUrl', 'https://api.example.com');
        $service->shouldReceive('sendError')->once();

        $result = $this->invokeProtectedMethod($service, 'request', ['POST', '/test-endpoint', null, false]);

        $this->assertNull($result);
    }

    /**
     * Test processResponse with 200 status code
     */
    public function test_process_response_success()
    {
        Config::shouldReceive('get')->with('app.EMAIL_NOTIFICATIONS', '')->andReturn('test@example.com');

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
        $mockResponse->shouldReceive('getBody->getContents')->andReturn('{"success":true}');

        $service = new BaseHTTPService();

        $result = $this->invokeProtectedMethod($service, 'processResponse', [$mockResponse, '/test', false]);

        $this->assertIsObject($result);
        $this->assertTrue($result->success);
    }

    /**
     * Test processResponse with non-200 status code
     */
    public function test_process_response_failure()
    {
        Config::shouldReceive('get')->with('app.EMAIL_NOTIFICATIONS', '')->andReturn('test@example.com');

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(500);

        $service = Mockery::mock(BaseHTTPService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('sendError')->once()->with('Respuesta no exitosa', '/test', false);

        $result = $this->invokeProtectedMethod($service, 'processResponse', [$mockResponse, '/test', false]);

        $this->assertNull($result);
    }

    /**
     * Test sendError with email enabled
     */
    public function test_send_error_with_email()
    {
        Config::shouldReceive('get')->with('app.EMAIL_NOTIFICATIONS', '')->andReturn('test@example.com');

        // Mock Mail facade to avoid sending actual emails
        \Illuminate\Support\Facades\Mail::shouldReceive('send')
            ->once()
            ->with('emails.MediMemErrorNotification', Mockery::type('array'), Mockery::type('Closure'));

        $service = new BaseHTTPService();
        $this->setProtectedProperty($service, 'baseUrl', 'https://api.example.com');
        $this->setProtectedProperty($service, 'emailNotification', 'test@example.com');

        $this->invokeProtectedMethod($service, 'sendError', ['Test error', '/endpoint', true]);

        $this->assertTrue(true); // Verify no exception thrown
    }

    /**
     * Test sendError with email disabled
     */
    public function test_send_error_without_email()
    {
        Config::shouldReceive('get')->with('app.EMAIL_NOTIFICATIONS', '')->andReturn('test@example.com');

        // Mail should NOT be called when sendErrorEmail is false
        \Illuminate\Support\Facades\Mail::shouldReceive('send')->never();

        $service = new BaseHTTPService();
        $this->setProtectedProperty($service, 'baseUrl', 'https://api.example.com');

        $this->invokeProtectedMethod($service, 'sendError', ['Test error', '/endpoint', false]);

        $this->assertTrue(true); // Verify no exception thrown
    }

    /**
     * Test sendError creates correct data structure
     */
    public function test_send_error_data_structure()
    {
        Config::shouldReceive('get')->with('app.EMAIL_NOTIFICATIONS', '')->andReturn('notify@example.com');

        // Mock Mail to validate the data structure
        \Illuminate\Support\Facades\Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.MediMemErrorNotification',
                Mockery::on(function ($data) {
                    return isset($data['subject']) &&
                           isset($data['info']) &&
                           isset($data['url']) &&
                           $data['subject'] === 'Error en lectura de endpoint' &&
                           $data['info'] === 'Connection timeout' &&
                           $data['url'] === 'https://api.example.com/api/v1/data';
                }),
                Mockery::type('Closure')
            );

        $service = new BaseHTTPService();
        $this->setProtectedProperty($service, 'baseUrl', 'https://api.example.com');
        $this->setProtectedProperty($service, 'emailNotification', 'notify@example.com');

        $this->invokeProtectedMethod($service, 'sendError', ['Connection timeout', '/api/v1/data', true]);

        $this->assertTrue(true);
    }

    /**
     * Test request builds correct URL
     */
    public function test_request_builds_correct_url()
    {
        Config::shouldReceive('get')->with('app.EMAIL_NOTIFICATIONS', '')->andReturn('test@example.com');

        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
        $mockResponse->shouldReceive('getBody->getContents')->andReturn('{"result":"ok"}');
        $mockClient->shouldReceive('get')->with('https://api.test.com/v1/endpoint')->andReturn($mockResponse);

        $service = new BaseHTTPService();
        $this->setProtectedProperty($service, 'client', $mockClient);
        $this->setProtectedProperty($service, 'baseUrl', 'https://api.test.com');

        $result = $this->invokeProtectedMethod($service, 'request', ['GET', '/v1/endpoint', null, false]);

        $this->assertIsObject($result);
        $this->assertEquals('ok', $result->result);
    }

    /**
     * Test constructor sets email notification from config
     */
    public function test_constructor_email_notification_from_config()
    {
        Config::shouldReceive('get')->with('app.EMAIL_NOTIFICATIONS', '')->andReturn('admin@example.com');

        $service = new BaseHTTPService();

        $emailNotification = $this->getProtectedProperty($service, 'emailNotification');

        $this->assertEquals('admin@example.com', $emailNotification);
    }

    /**
     * Test constructor with empty email configuration
     */
    public function test_constructor_empty_email_configuration()
    {
        Config::shouldReceive('get')->with('app.EMAIL_NOTIFICATIONS', '')->andReturn('');

        $service = new BaseHTTPService();

        $emailNotification = $this->getProtectedProperty($service, 'emailNotification');

        $this->assertEquals('', $emailNotification);
    }
}
