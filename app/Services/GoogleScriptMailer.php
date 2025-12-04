<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleScriptMailer
{
    public static function send(
        string $to,
        ?string $name,
        string $subject,
        string $html,
        string $text = ''
    ): bool {
        $url    = config('services.google_script_mailer.url');     // .env
        $secret = config('services.google_script_mailer.secret');  // .env

        $payload = [
            'secret'  => $secret,
            'to'      => $to,
            'subject' => $subject,
            'html'    => $html,
            'text'    => $text,
        ];

        try {
            Log::info('Enviando petición a Google Script', [
                'url'  => $url,
                'to'   => $to,
                'subj' => $subject,
            ]);

            $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            Log::info('Respuesta de Google Script', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            if (! $response->successful()) {
                return false;
            }

            $json = $response->json();
            return isset($json['ok']) && $json['ok'] === true;

        } catch (\Throwable $e) {
            Log::error('Error en GoogleScriptMailer::send', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
