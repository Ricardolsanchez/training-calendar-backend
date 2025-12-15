<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;


class AdminStatsController extends Controller
{
    /**
     * GET /admin/stats/kpis
     * Returns KPIs + chart-ready datasets.
     *
     * Optional query params:
     * - from=YYYY-MM-DD
     * - to=YYYY-MM-DD
     */
    public function kpis(Request $request)
    {
        try {
            $from = $request->query('from');
            $to = $request->query('to');

            $bookingsQ = Booking::query();
            $classesQ = ClassSession::query();

            // Optional date range filter:
            // bookings: by created_at
            // classes:  by start_date
            if ($from) {
                $bookingsQ->whereDate('created_at', '>=', $from);
                $classesQ->whereDate('start_date', '>=', $from);
            }
            if ($to) {
                $bookingsQ->whereDate('created_at', '<=', $to);
                $classesQ->whereDate('start_date', '<=', $to);
            }

            // KPIs
            $totalBookings = (clone $bookingsQ)->count();

            // Only accepted bookings
            $acceptedQ = (clone $bookingsQ)->where('status', 'accepted');
            $acceptedTotal = (clone $acceptedQ)->count();

            $attendedCount = (clone $acceptedQ)->where('attendedbutton', true)->count();
            $notAttendedCount = (clone $acceptedQ)->where('attendedbutton', false)->count();
            $notMarkedCount = (clone $acceptedQ)->whereNull('attendedbutton')->count();

            $totalClassesCreated = (clone $classesQ)->count();
            $totalAttendedOverall = $attendedCount;

            // Pie chart
            $pie = [
                ['label' => 'Attended', 'value' => $attendedCount],
                ['label' => 'Not Attended', 'value' => $notAttendedCount],
                ['label' => 'Not Marked', 'value' => $notMarkedCount],
            ];

            // Line chart by day (Postgres DATE())
            $lineRows = (clone $acceptedQ)
                ->selectRaw("DATE(created_at) as day")
                ->selectRaw("SUM(CASE WHEN attendedbutton = true THEN 1 ELSE 0 END) as attended")
                ->selectRaw("SUM(CASE WHEN attendedbutton = false THEN 1 ELSE 0 END) as not_attended")
                ->selectRaw("SUM(CASE WHEN attendedbutton IS NULL THEN 1 ELSE 0 END) as not_marked")
                ->groupBy(DB::raw("DATE(created_at)"))
                ->orderBy(DB::raw("DATE(created_at)"), 'asc')
                ->get();

            $line = $lineRows->map(fn($r) => [
                'day' => $r->day,
                'attended' => (int) $r->attended,
                'not_attended' => (int) $r->not_attended,
                'not_marked' => (int) $r->not_marked,
            ])->values();

            // Requests per class (grouped)
            $perClassRows = (clone $acceptedQ)
                ->select([
                    'name as class_title',
                    'start_date',
                    'end_date',
                    'trainer_name',
                ])
                ->selectRaw('COUNT(*) as requests')
                ->selectRaw("SUM(CASE WHEN attendedbutton = true THEN 1 ELSE 0 END) as attended")
                ->selectRaw("SUM(CASE WHEN attendedbutton = false THEN 1 ELSE 0 END) as not_attended")
                ->selectRaw("SUM(CASE WHEN attendedbutton IS NULL THEN 1 ELSE 0 END) as not_marked")
                ->groupBy('name', 'start_date', 'end_date', 'trainer_name')
                ->orderBy('start_date', 'asc')
                ->get();

            $perClass = $perClassRows->map(fn($r) => [
                'classTitle' => $r->class_title,
                'start_date' => $r->start_date,
                'end_date' => $r->end_date,
                'trainer_name' => $r->trainer_name,
                'requests' => (int) $r->requests,
                'attended' => (int) $r->attended,
                'not_attended' => (int) $r->not_attended,
                'not_marked' => (int) $r->not_marked,
            ])->values();

            return response()->json([
                'ok' => true,
                'kpis' => [
                    'total_bookings' => $totalBookings,
                    'total_classes_created' => $totalClassesCreated,
                    'accepted_total' => $acceptedTotal,
                    'attended_total' => $attendedCount,
                    'not_attended_total' => $notAttendedCount,
                    'not_marked_total' => $notMarkedCount,
                    'total_attended_overall' => $totalAttendedOverall,
                ],
                'charts' => [
                    'pie_attendance' => $pie,
                    'line_attendance_by_day' => $line,
                ],
                'per_class' => $perClass,
                'filters' => [
                    'from' => $from,
                    'to' => $to,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error building admin stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Server error building stats.',
            ], 500);
        }
    }
    public function exportKpisCsv(Request $request): StreamedResponse
    {
        // Reutiliza la MISMA lógica llamando a kpis() y usando su data
        $jsonResponse = $this->kpis($request);

        $payload = $jsonResponse->getData(true);

        // Si hubo error, evita descargar un CSV vacío
        if (!($payload['ok'] ?? false)) {
            return response()->stream(function () {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['error', 'Could not build KPIs']);
                fclose($out);
            }, 500, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="kpis_error.csv"',
            ]);
        }

        $kpis = $payload['kpis'] ?? [];
        $from = $payload['filters']['from'] ?? '';
        $to = $payload['filters']['to'] ?? '';

        $filename = 'kpis_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->stream(function () use ($kpis, $from, $to) {
            $out = fopen('php://output', 'w');

            // BOM para Excel
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // (Opcional) filtros
            fputcsv($out, ['from', $from]);
            fputcsv($out, ['to', $to]);
            fputcsv($out, []); // línea en blanco

            // KPIs
            fputcsv($out, ['metric', 'value']);
            foreach ($kpis as $metric => $value) {
                fputcsv($out, [$metric, $value]);
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
