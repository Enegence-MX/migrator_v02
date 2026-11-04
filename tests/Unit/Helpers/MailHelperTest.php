<?php

namespace Tests\Unit\Helpers;

use Tests\TestCase;
use App\Http\Helpers\MailHelper;
use Illuminate\Support\Facades\Mail;
use Mockery;

class MailHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test sendMedimemError sends email with correct data
     */
    public function test_send_medimem_error_basic()
    {
        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.MediMemErrorNotification',
                \Mockery::type('array'),
                \Mockery::type('Closure')
            );

        $email = 'test@example.com';
        $data = [
            'error' => 'Test error message',
            'rpu' => 'RPU-001',
            'fecha' => '2025-01-15'
        ];

        MailHelper::sendMedimemError($email, $data);

        $this->assertTrue(true); // Verify no exception thrown
    }

    /**
     * Test sendMedimemError with BCC email
     */
    public function test_send_medimem_error_with_bcc()
    {
        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.MediMemErrorNotification',
                \Mockery::on(function ($data) {
                    return isset($data['bccEmail']) && $data['bccEmail'] === 'admin@example.com';
                }),
                \Mockery::type('Closure')
            );

        $email = 'test@example.com';
        $data = [
            'error' => 'Test error',
            'bccEmail' => 'admin@example.com'
        ];

        MailHelper::sendMedimemError($email, $data);

        $this->assertTrue(true);
    }

    /**
     * Test sendMedimemError with multiple data fields
     */
    public function test_send_medimem_error_comprehensive_data()
    {
        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.MediMemErrorNotification',
                \Mockery::type('array'),
                \Mockery::type('Closure')
            );

        $email = 'notifications@example.com';
        $data = [
            'error' => 'Connection timeout',
            'rpu' => 'RPU-12345',
            'fecha' => '2025-01-15',
            'sistema' => 'BCA',
            'details' => 'MediMEM API returned 504',
            'bccEmail' => 'backup@example.com'
        ];

        MailHelper::sendMedimemError($email, $data);

        $this->assertTrue(true);
    }

    /**
     * Test sendTestMonitorEmail sends basic test email
     */
    public function test_send_test_monitor_email()
    {
        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.testMail',
                \Mockery::type('array'),
                \Mockery::type('Closure')
            );

        $email = 'monitor@example.com';

        MailHelper::sendTestMonitorEmail($email);

        $this->assertTrue(true);
    }

    /**
     * Test sendTestMonitorEmail with different email addresses
     */
    public function test_send_test_monitor_email_various_addresses()
    {
        Mail::shouldReceive('send')
            ->times(3)
            ->with(
                'emails.testMail',
                \Mockery::type('array'),
                \Mockery::type('Closure')
            );

        $emails = [
            'test1@example.com',
            'test2@example.com',
            'admin@example.com'
        ];

        foreach ($emails as $email) {
            MailHelper::sendTestMonitorEmail($email);
        }

        $this->assertTrue(true);
    }

    /**
     * Test sendMedimemError without BCC
     */
    public function test_send_medimem_error_without_bcc()
    {
        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.MediMemErrorNotification',
                \Mockery::on(function ($data) {
                    return !isset($data['bccEmail']);
                }),
                \Mockery::type('Closure')
            );

        $email = 'test@example.com';
        $data = [
            'error' => 'Simple error message',
            'rpu' => 'RPU-999'
        ];

        MailHelper::sendMedimemError($email, $data);

        $this->assertTrue(true);
    }

    /**
     * Test sendMedimemError is called exactly once
     */
    public function test_send_medimem_error_called_once()
    {
        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.MediMemErrorNotification',
                \Mockery::type('array'),
                \Mockery::type('Closure')
            );

        MailHelper::sendMedimemError('test@example.com', ['error' => 'Test']);

        $this->assertTrue(true);
    }

    /**
     * Test sendTestMonitorEmail is called exactly once
     */
    public function test_send_test_monitor_email_called_once()
    {
        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.testMail',
                \Mockery::type('array'),
                \Mockery::type('Closure')
            );

        MailHelper::sendTestMonitorEmail('monitor@example.com');

        $this->assertTrue(true);
    }
}
