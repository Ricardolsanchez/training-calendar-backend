<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleScriptMailer
{
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $html,
        ?string $text = null
    ): bool {
        // 👇 OJO: aquí van NOMBRES de variables de entorno, no la URL literal
        $url    = env('https://script.google.com/a/macros/alonsoalonsolaw.com/s/AKfycbwsdg7ucuyNwx7kHi6DppfIS_76GtGm4TEzcTf9nU_NE5jdVlgPoVUaO2CJcU7rV4vXKA/exec');    // tu Web App URL
        $secret = env('A7kP2sM9vQ1tR4bW6yZ8uH3c'); // tu token/secret del script

        if (!$url || !$secret) {
            Log::error('GoogleScriptMailer: URL o SECRET no configurados', [
                'url'    => $url,
                'secret' => $secret ? '***set***' : null,
            ]);
            return false;
        }

        try {
            $response = Http::post($url, [
                'secret'  => $secret,
                'to'      => $toEmail,
                'name'    => $toName,
                'subject' => $subject,
                'html'    => $html,
                'text'    => $text,
            ]);

            if (!$response->successful()) {
                Log::error('GoogleScriptMailer error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            $body = $response->json();

            Log::info('GoogleScriptMailer respuesta', ['body' => $body]);

            return $body['ok'] ?? false;

        } catch (\Throwable $e) {
            Log::error('GoogleScriptMailer exception', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
