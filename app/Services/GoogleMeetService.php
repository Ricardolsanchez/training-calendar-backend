<?php

namespace App\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;

class GoogleMeetService
{
    public static function createMeetEvent(array $data): array
    {
        $client = new Client();
        $client->setAuthConfig(storage_path('app/google/service-account.json'));
        $client->addScope(Calendar::CALENDAR);

        $service = new Calendar($client);

        $event = new Event([
            'summary' => $data['summary'],
            'description' => $data['description'] ?? '',
            'start' => [
                'dateTime' => $data['start'], // ISO8601
                'timeZone' => $data['timeZone'] ?? 'America/Bogota',
            ],
            'end' => [
                'dateTime' => $data['end'],   // ISO8601
                'timeZone' => $data['timeZone'] ?? 'America/Bogota',
            ],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => uniqid('meet_', true),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
        ]);

        $calendarId = $data['calendarId'] ?? env('GOOGLE_CALENDAR_ID', 'primary');

        $created = $service->events->insert(
            $calendarId,
            $event,
            ['conferenceDataVersion' => 1]
        );

        return [
            'event_id' => $created->id,
            'meet_link' => $created->getHangoutLink(),
        ];
    }
}
