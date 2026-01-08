<?php

namespace App\Support;

use Carbon\Carbon;

class GoogleCalendarLink
{
    public static function build(
        string $title,
        string $dateIso,
        string $timeRange,
        string $details = '',
        ?string $location = null,
        string $tz = 'America/Bogota'
    ): string {
        // timeRange esperado: "HH:mm - HH:mm"
        $clean = str_replace('—', '-', $timeRange);
        [$startStr, $endStr] = array_map('trim', explode('-', $clean) + ['', '']);

        // fallback si viene vacío
        if (!$startStr) $startStr = '09:00';
        if (!$endStr) $endStr = '10:00';

        // Parse local -> UTC
        $startLocal = Carbon::createFromFormat('Y-m-d H:i', $dateIso.' '.$startStr, $tz);
        $endLocal   = Carbon::createFromFormat('Y-m-d H:i', $dateIso.' '.$endStr, $tz);

        $startUtc = $startLocal->copy()->utc()->format('Ymd\THis\Z');
        $endUtc   = $endLocal->copy()->utc()->format('Ymd\THis\Z');

        $params = [
            'action'  => 'TEMPLATE',
            'text'    => $title,
            'dates'   => $startUtc . '/' . $endUtc,
            'details' => $details,
            'ctz'     => $tz,
        ];

        if ($location) $params['location'] = $location;

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }
}
