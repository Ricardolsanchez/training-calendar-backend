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
        
        // 👇 AQUÍ sí leemos desde el .env
        $url    = env('GSCRIPT_MAILER_URL');    
        $secret = env('GSCRIPT_MAILER_SECRET'); 

        if (!$url || !$secret) {
            Log::error('GoogleScriptMailer: URL o SECRET no configurados');
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

            return $response->json()['ok'] ?? false;

        } catch (\Throwable $e) {
            Log::error('GoogleScriptMailer exception', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
