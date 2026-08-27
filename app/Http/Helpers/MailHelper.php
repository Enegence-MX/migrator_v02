<?php namespace App\Http\Helpers;

use Illuminate\Support\Facades\Mail;

/**
 * Helper class to send email notificaions.
 */
class MailHelper
{

    /**
     * Static function to notify about MediMEM measurements error.
     *
     * @param $email String Email account.
     * @param $data  Array  Email data.
     *
     * @return void
     */
    public static function sendMedimemError($email, $data)
    {
        Mail::send(
            'emails.MediMemErrorNotification',
            $data,
            function ($mail) use ($email, $data) {
                $mail->to($email);
                if (isset($data['bccEmail'])) {
                    $mail->bcc($data['bccEmail']);
                }
                $mail->subject('Error en lectura programada de mediciones MediMEM');
            }
        );
    }

    public static function sendTestMonitorEmail($email)
    {
        $data = [
            'customMessage' => 'Este es un correo de prueba.',
        ];

        Mail::send(
            'emails.testMail',
            $data,
            function ($mail) use ($email) {
                $mail->to($email);
                $mail->subject('Correo de prueba del monitor');
            }
        );
    }
}
