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
        // 🔹 Mejor leer desde config(), que ya usa el .env por debajo
        $url    = config('services.google_script_mailer.url', env('GOOGLE_SCRIPT_MAILER_URL'));
        $secret = config('services.google_script_mailer.secret', env('GOOGLE_SCRIPT_MAILER_SECRET'));

        Log::info('GoogleScriptMailer ENV vs CONFIG', [
            'env_url'       => env('GOOGLE_SCRIPT_MAILER_URL'),
            'config_url'    => config('services.google_script_mailer.url'),
            'env_secret'    => env('GOOGLE_SCRIPT_MAILER_SECRET') ? true : false,
            'config_secret' => config('services.google_script_mailer.secret') ? true : false,
        ]);

        if (! $url || ! $secret) {
            Log::error('GoogleScriptMailer: faltan URL o SECRET', [
                'url'            => $url,
                'secret_present' => $secret ? true : false,
            ]);
            return false;
        }

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

            $response = Http::asJson()->post($url, $payload);

            Log::info('Respuesta de Google Script', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            if (! $response->successful()) {
                Log::error('GoogleScriptMailer error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            $json = $response->json();

            return isset($json['ok']) && $json['ok'] === true;
        } catch (\Throwable $e) {
            Log::error('Error en GoogleScriptMailer::send', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'to'      => $to,
                'subject' => $subject,
            ]);

            return false;
        }
    }
}
