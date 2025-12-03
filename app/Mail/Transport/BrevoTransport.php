<?php

namespace App\Mail\Transport;

use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Configuration;
use Illuminate\Mail\Transport\Transport;
use Swift_Mime_SimpleMessage;

class BrevoTransport extends Transport
{
    protected $client;

    public function __construct($apiKey)
    {
        $config = Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', $apiKey);

        $this->client = new TransactionalEmailsApi(null, $config);
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $to = collect($message->getTo())->map(function ($name, $email) {
            return ['email' => $email, 'name' => $name];
        })->values()->toArray();

        $emailData = [
            'subject' => $message->getSubject(),
            'htmlContent' => $message->getBody(),
            'sender' => [
                'email' => config('mail.from.address'),
                'name' => config('mail.from.name'),
            ],
            'to' => $to,
        ];

        $this->client->sendTransacEmail($emailData);
    }
}
