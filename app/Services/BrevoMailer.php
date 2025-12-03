<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoMailer
{
    /**
     * Envía un correo usando la API HTTP de Brevo.
     *
     * @param  string       $toEmail   Correo destinatario
     * @param  string       $toName    Nombre destinatario
     * @param  string       $subject   Asunto
     * @param  string       $html      HTML completo del correo
     * @param  string|null  $text      Versión de texto plano (opcional)
     * @return bool
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $html,
        ?string $text = null
    ): bool {
        $apiKey = env('BREVO_API_KEY');

        if (!$apiKey) {
            Log::error('Brevo API key not set in env.');
            return false;
        }

        $senderEmail = env('MAIL_FROM_ADDRESS', 'no-reply@example.com');
        $senderName  = env('MAIL_FROM_NAME', 'Training Calendar');

        $payload = [
            'sender' => [
                'email' => $senderEmail,
                'name'  => $senderName,
            ],
            'to' => [
                [
                    'email' => $toEmail,
                    'name'  => $toName ?: $toEmail,
                ],
            ],
            'subject'      => $subject,
            'htmlContent'  => $html,
        ];

        if ($text) {
            $payload['textContent'] = $text;
        }

        try {
            $response = Http::withHeaders([
                'api-key'      => $apiKey,
                'accept'       => 'application/json',
                'content-type' => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', $payload);

            if (!$response->successful()) {
                Log::error('Brevo API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Brevo API exception', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
