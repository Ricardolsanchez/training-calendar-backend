<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleScriptMailer
{
    /**
     * Envía un correo usando el Google Apps Script publicado como web app.
     *
     * @param  string      $to      Correo destino
     * @param  string|null $name    Nombre del destinatario (opcional)
     * @param  string      $subject Asunto del correo
     * @param  string      $html    Cuerpo HTML
     * @param  string      $text    Cuerpo texto plano (opcional)
     * @return bool
     */
    public static function send(
        string $to,
        ?string $name,
        string $subject,
        string $html,
        string $text = ''
    ): bool {
        // 🔹 LEER DIRECTO DESDE env()
        $url    = env('GOOGLE_SCRIPT_MAILER_URL');
        $secret = env('GOOGLE_SCRIPT_MAILER_SECRET');

        // 🔹 Log de comparación env() vs config() (debug)
        Log::info('GoogleScriptMailer ENV vs CONFIG', [
            'env_url'       => $url,
            'config_url'    => config('services.google_script_mailer.url'),
            'env_secret'    => $secret ? true : false,
            'config_secret' => config('services.google_script_mailer.secret') ? true : false,
        ]);

        // 🔹 Validar que existan URL y SECRET
        if (! $url || ! $secret) {
            Log::error('GoogleScriptMailer: faltan URL o SECRET', [
                'url'             => $url,
                'secret_present'  => $secret ? true : false,
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

            $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

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
