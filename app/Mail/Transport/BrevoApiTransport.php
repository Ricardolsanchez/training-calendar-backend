<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

class BrevoApiTransport extends AbstractTransport
{
    protected string $apiKey;

    public function __construct(string $apiKey)
    {
        parent::__construct();

        $this->apiKey = $apiKey;
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            return;
        }

        // TO
        $to = [];
        foreach ($email->getTo() as $addr) {
            $to[] = [
                'email' => $addr->getAddress(),
                'name'  => $addr->getName() ?: null,
            ];
        }

        // FROM (si no viene en el mensaje, usamos el del config)
        $from = $email->getFrom()[0] ?? null;
        $senderEmail = $from ? $from->getAddress() : config('mail.from.address');
        $senderName  = $from ? $from->getName() : config('mail.from.name');

        $subject = $email->getSubject() ?? '';
        $html    = $email->getHtmlBody() ?? nl2br((string) $email->getTextBody());

        $payload = [
            'sender' => [
                'email' => $senderEmail,
                'name'  => $senderName,
            ],
            'to' => $to,
            'subject' => $subject,
            'htmlContent' => $html,
        ];

        Http::withHeaders([
            'api-key'       => $this->apiKey,
            'accept'        => 'application/json',
            'content-type'  => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', $payload)->throw();
    }

    public function __toString(): string
    {
        return 'brevo-api';
    }
}
