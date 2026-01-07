<?php

namespace App\Services;

use App\Models\ClassSession;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;

class GoogleMeetService
{
    /**
     * Crea un evento en Google Calendar con Google Meet.
     * Retorna event_id, meet_link y html_link.
     */
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
            'html_link' => $created->htmlLink,
        ];
    }

    /**
     * Asegura que una ClassSession tenga su evento + link.
     * - Si ya tiene calendar_url => lo retorna
     * - Si tiene calendar_event_id pero no calendar_url => intenta reconstruir/leer (opcional)
     * - Si no tiene nada => crea el evento y guarda en DB
     */
    public static function ensureSessionEvent(ClassSession $session): string
    {
        // ✅ Si ya existe url, no hacemos nada
        if (!empty($session->calendar_url)) {
            return $session->calendar_url;
        }

        // ✅ Si ya hay event_id (pero no url), evitamos crear duplicados.
        // (Opcional: podrías fetch el evento para recuperar htmlLink/meet link.)
        if (!empty($session->calendar_event_id)) {
            // Como fallback, retornamos vacío o podrías implementar fetch.
            // Para no romper, mejor retorna algo controlado:
            return '';
        }

        // ✅ Parse robusto de time_range: "HH:mm - HH:mm"
        [$startTime, $endTime] = self::parseTimeRange($session->time_range);

        // ✅ ISO8601 real con zona horaria (Carbon)
        $tz = 'America/Bogota';
        $start = Carbon::parse($session->date_iso . ' ' . $startTime, $tz)->toRfc3339String();
        $end   = Carbon::parse($session->date_iso . ' ' . $endTime, $tz)->toRfc3339String();

        $result = self::createMeetEvent([
            'summary' => $session->title ?? 'Training session',
            'description' => $session->description ?? 'Training session',
            'start' => $start,
            'end' => $end,
            'timeZone' => $tz,
        ]);

        // ✅ Guardamos 1 link POR SESIÓN
        $session->calendar_event_id = $result['event_id'];
        $session->calendar_url = $result['meet_link']; // o $result['html_link']
        $session->save();

        return $session->calendar_url;
    }

    /**
     * Espera formato "HH:mm - HH:mm" o "HH:mm-HH:mm"
     */
    private static function parseTimeRange(?string $timeRange): array
    {
        $tr = trim((string) $timeRange);

        // soporta "09:00 - 10:30" o "09:00-10:30"
        $parts = preg_split('/\s*-\s*/', $tr);

        $start = $parts[0] ?? '09:00';
        $end   = $parts[1] ?? '10:00';

        // limpiezas básicas
        $start = substr(trim($start), 0, 5);
        $end   = substr(trim($end), 0, 5);

        return [$start, $end];
    }
}
