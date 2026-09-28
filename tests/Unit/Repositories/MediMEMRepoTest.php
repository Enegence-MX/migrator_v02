<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Http\Repositories\MediMEMRepo;
use App\Http\Repositories\MeasurementsRepo;
use App\Http\Services\MediMEMService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Mockery;
use ReflectionClass;
use stdClass;

class MediMEMRepoTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper method to access private methods using reflection
     */
    protected function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Helper method to access protected methods using reflection
     */
    protected function invokeProtectedMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Test roundRobinByTeamId interleaves items by teamId
     */
    public function test_round_robin_by_team_id_interleaves_correctly()
    {
        // Mock dependencies
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('channel')->andReturnSelf();

        $serviceMock = Mockery::mock(MediMEMService::class);
        $measurementsRepoMock = Mockery::mock(MeasurementsRepo::class);
        $repo = new MediMEMRepo($serviceMock, $measurementsRepoMock);

        $collection = collect([
            (object)['teamId' => 1, 'name' => 'Item1-Team1'],
            (object)['teamId' => 1, 'name' => 'Item2-Team1'],
            (object)['teamId' => 2, 'name' => 'Item1-Team2'],
            (object)['teamId' => 2, 'name' => 'Item2-Team2'],
            (object)['teamId' => 3, 'name' => 'Item1-Team3'],
        ]);

        $result = $this->invokePrivateMethod($repo, 'roundRobinByTeamId', [$collection]);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(5, $result);

        // Verify round-robin pattern: Team1, Team2, Team3, Team1, Team2
        $this->assertEquals(1, $result[0]->teamId);
        $this->assertEquals(2, $result[1]->teamId);
        $this->assertEquals(3, $result[2]->teamId);
        $this->assertEquals(1, $result[3]->teamId);
        $this->assertEquals(2, $result[4]->teamId);
    }

    /**
     * Test roundRobinByTeamId with empty collection
     */
    public function test_round_robin_by_team_id_empty_collection()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('channel')->andReturnSelf();

        $serviceMock = Mockery::mock(MediMEMService::class);
        $measurementsRepoMock = Mockery::mock(MeasurementsRepo::class);
        $repo = new MediMEMRepo($serviceMock, $measurementsRepoMock);

        $collection = collect();

        $result = $this->invokePrivateMethod($repo, 'roundRobinByTeamId', [$collection]);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    /**
     * Test roundRobinByTeamId with single teamId
     */
    public function test_round_robin_by_team_id_single_team()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('channel')->andReturnSelf();

        $serviceMock = Mockery::mock(MediMEMService::class);
        $measurementsRepoMock = Mockery::mock(MeasurementsRepo::class);
        $repo = new MediMEMRepo($serviceMock, $measurementsRepoMock);

        $collection = collect([
            (object)['teamId' => 1, 'name' => 'Item1'],
            (object)['teamId' => 1, 'name' => 'Item2'],
            (object)['teamId' => 1, 'name' => 'Item3'],
        ]);

        $result = $this->invokePrivateMethod($repo, 'roundRobinByTeamId', [$collection]);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
        $this->assertEquals('Item1', $result[0]->name);
        $this->assertEquals('Item2', $result[1]->name);
        $this->assertEquals('Item3', $result[2]->name);
    }

    /**
     * Test roundRobinByTeamId with uneven distribution
     */
    public function test_round_robin_by_team_id_uneven_distribution()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('channel')->andReturnSelf();

        $serviceMock = Mockery::mock(MediMEMService::class);
        $measurementsRepoMock = Mockery::mock(MeasurementsRepo::class);
        $repo = new MediMEMRepo($serviceMock, $measurementsRepoMock);

        $collection = collect([
            (object)['teamId' => 1, 'name' => 'Item1-T1'],
            (object)['teamId' => 1, 'name' => 'Item2-T1'],
            (object)['teamId' => 1, 'name' => 'Item3-T1'],
            (object)['teamId' => 2, 'name' => 'Item1-T2'],
        ]);

        $result = $this->invokePrivateMethod($repo, 'roundRobinByTeamId', [$collection]);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(4, $result);

        // Should alternate until Team2 is exhausted, then continue with Team1
        $this->assertEquals(1, $result[0]->teamId);
        $this->assertEquals(2, $result[1]->teamId);
        $this->assertEquals(1, $result[2]->teamId);
        $this->assertEquals(1, $result[3]->teamId);
    }

    /**
     * Test sendGoogleChatNotification method (mocked HTTP call)
     */
    public function test_send_google_chat_notification()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('channel')->andReturnSelf();

        // Mock HTTP facade - allow but don't require the call
        Http::shouldReceive('post')->zeroOrMoreTimes()->andReturn(true);

        $serviceMock = Mockery::mock(MediMEMService::class);
        $measurementsRepoMock = Mockery::mock(MeasurementsRepo::class);
        $repo = new MediMEMRepo($serviceMock, $measurementsRepoMock);

        $result = $this->invokeProtectedMethod(
            $repo,
            'sendGoogleChatNotification',
            ['Test Title', 'Test Message', 'RPU123']
        );

        // Method doesn't return anything, just verify it doesn't throw
        $this->assertTrue(true);
    }

    /**
     * Test constructor initializes service
     */
    public function test_constructor_initializes_service()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('channel')->andReturnSelf();

        $serviceMock = Mockery::mock(MediMEMService::class);
        $measurementsRepoMock = Mockery::mock(MeasurementsRepo::class);
        $repo = new MediMEMRepo($serviceMock, $measurementsRepoMock);

        $this->assertInstanceOf(MediMEMRepo::class, $repo);
    }

    /**
     * Test sendGoogleChatNotification uses default webhook when config is empty
     */
    public function test_send_google_chat_notification_uses_default_webhook()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('channel')->andReturnSelf();

        \Illuminate\Support\Facades\Config::shouldReceive('get')
            ->with('services.google_chat.webhook', Mockery::any())
            ->andReturn('');

        Http::shouldReceive('timeout')->with(5)->andReturnSelf();
        Http::shouldReceive('post')->once()->andReturn(true);

        $serviceMock = Mockery::mock(MediMEMService::class);
        $measurementsRepoMock = Mockery::mock(MeasurementsRepo::class);
        $repo = new MediMEMRepo($serviceMock, $measurementsRepoMock);

        $this->invokeProtectedMethod(
            $repo,
            'sendGoogleChatNotification',
            ['Error Title', 'Error Message', 'RPU-001']
        );

        $this->assertTrue(true);
    }

    /**
     * Test sendGoogleChatNotification handles HTTP exception gracefully
     */
    public function test_send_google_chat_notification_handles_exception()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
        Log::shouldReceive('channel')->with('task_errors')->andReturnSelf();
        Log::shouldReceive('error')->once()->with(Mockery::pattern('/No se pudo enviar la notificación/'));

        \Illuminate\Support\Facades\Config::shouldReceive('get')
            ->with('services.google_chat.webhook', Mockery::any())
            ->andReturn('https://chat.googleapis.com/webhook');

        Http::shouldReceive('timeout')->with(5)->andReturnSelf();
        Http::shouldReceive('post')->andThrow(new \Exception('Network error'));

        $serviceMock = Mockery::mock(MediMEMService::class);
        $measurementsRepoMock = Mockery::mock(MeasurementsRepo::class);
        $repo = new MediMEMRepo($serviceMock, $measurementsRepoMock);

        $this->invokeProtectedMethod(
            $repo,
            'sendGoogleChatNotification',
            ['Error Title', 'Error Message', 'RPU-001']
        );

        $this->assertTrue(true);
    }

    /**
     * Test sendGoogleChatNotification includes RPU/RMU in message
     */
    public function test_send_google_chat_notification_includes_context()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('channel')->andReturnSelf();

        \Illuminate\Support\Facades\Config::shouldReceive('get')
            ->with('services.google_chat.webhook', Mockery::any())
            ->andReturn('https://chat.googleapis.com/webhook');

        Http::shouldReceive('timeout')->with(5)->andReturnSelf();
        Http::shouldReceive('post')
            ->once()
            ->with(
                'https://chat.googleapis.com/webhook',
                Mockery::on(function ($payload) {
                    return isset($payload['text']) &&
                           str_contains($payload['text'], 'RPU-12345') &&
                           str_contains($payload['text'], 'ERROR EN TAREA MEDIMEM');
                })
            )
            ->andReturn(true);

        $serviceMock = Mockery::mock(MediMEMService::class);
        $measurementsRepoMock = Mockery::mock(MeasurementsRepo::class);
        $repo = new MediMEMRepo($serviceMock, $measurementsRepoMock);

        $this->invokeProtectedMethod(
            $repo,
            'sendGoogleChatNotification',
            ['API Error', 'Connection timeout', 'RPU-12345']
        );

        $this->assertTrue(true);
    }

    /**
     * Test roundRobinByTeamId with three teams
     */
    public function test_round_robin_by_team_id_three_teams()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('channel')->andReturnSelf();

        $serviceMock = Mockery::mock(MediMEMService::class);
        $measurementsRepoMock = Mockery::mock(MeasurementsRepo::class);
        $repo = new MediMEMRepo($serviceMock, $measurementsRepoMock);

        $collection = collect([
            (object)['teamId' => 100, 'id' => 1],
            (object)['teamId' => 100, 'id' => 2],
            (object)['teamId' => 200, 'id' => 3],
            (object)['teamId' => 200, 'id' => 4],
            (object)['teamId' => 300, 'id' => 5],
            (object)['teamId' => 300, 'id' => 6],
        ]);

        $result = $this->invokePrivateMethod($repo, 'roundRobinByTeamId', [$collection]);

        $this->assertCount(6, $result);
        // Pattern: 100, 200, 300, 100, 200, 300
        $this->assertEquals(100, $result[0]->teamId);
        $this->assertEquals(200, $result[1]->teamId);
        $this->assertEquals(300, $result[2]->teamId);
        $this->assertEquals(100, $result[3]->teamId);
        $this->assertEquals(200, $result[4]->teamId);
        $this->assertEquals(300, $result[5]->teamId);
    }

    /**
     * Test roundRobinByTeamId preserves all items
     */
    public function test_round_robin_by_team_id_preserves_all_items()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('channel')->andReturnSelf();

        $serviceMock = Mockery::mock(MediMEMService::class);
        $measurementsRepoMock = Mockery::mock(MeasurementsRepo::class);
        $repo = new MediMEMRepo($serviceMock, $measurementsRepoMock);

        $collection = collect([
            (object)['teamId' => 1, 'id' => 'A'],
            (object)['teamId' => 2, 'id' => 'B'],
            (object)['teamId' => 1, 'id' => 'C'],
            (object)['teamId' => 2, 'id' => 'D'],
        ]);

        $result = $this->invokePrivateMethod($repo, 'roundRobinByTeamId', [$collection]);

        // Verify all IDs are preserved
        $ids = $result->pluck('id')->toArray();
        $this->assertContains('A', $ids);
        $this->assertContains('B', $ids);
        $this->assertContains('C', $ids);
        $this->assertContains('D', $ids);
        $this->assertCount(4, $ids);
    }
}
