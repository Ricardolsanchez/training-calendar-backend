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
        $url    = config('GOOGLE_SCRIPT_MAILER_URL');
        $secret = config('GOOGLE_SCRIPT_MAILER_SECRET');

        // 1) Validar URL y SECRET
        if (! $url || ! $secret) {
            Log::error('GoogleScriptMailer: faltan URL o SECRET', [
                'url'    => $url,
                'secret' => $secret ? '***' : null,
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

            // 2) Usar asJson() para dejar claro que va JSON
            $response = Http::asJson()->post($url, $payload);

            Log::info('Respuesta de Google Script', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            if (! $response->successful()) {
                Log::warning('GoogleScriptMailer: HTTP no exitoso', [
                    'status' => $response->status(),
                ]);
                return false;
            }

            $json = $response->json();

            if (!is_array($json)) {
                Log::warning('GoogleScriptMailer: respuesta no es JSON válido', [
                    'body' => $response->body(),
                ]);
                // Si prefieres, aquí puedes devolver true si te consta que el script manda el correo igual
                return false;
            }

            if (empty($json['ok'])) {
                Log::warning('GoogleScriptMailer: JSON sin ok=true', [
                    'json' => $json,
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Error en GoogleScriptMailer::send', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
