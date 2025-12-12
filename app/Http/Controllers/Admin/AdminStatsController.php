<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AdminStatsController extends Controller
{
    /**
     * GET /api/admin/stats
     * Returns KPIs + chart-ready datasets.
     *
     * Optional query params:
     * - from=YYYY-MM-DD
     * - to=YYYY-MM-DD
     */
    public function index(Request $request)
    {
        try {
            $from = $request->query('from');
            $to   = $request->query('to');

            // Base queries
            $bookingsQ = Booking::query();
            $classesQ  = ClassSession::query();

            // Optional date range filter:
            // We filter bookings by created_at; and classes by start_date.
            if ($from) {
                $bookingsQ->whereDate('created_at', '>=', $from);
                $classesQ->whereDate('start_date', '>=', $from);
            }
            if ($to) {
                $bookingsQ->whereDate('created_at', '<=', $to);
                $classesQ->whereDate('start_date', '<=', $to);
            }

            // KPIs (global)
            $totalBookings = (clone $bookingsQ)->count();

            // attendance counts only for accepted bookings (you can remove this filter if you want)
            $acceptedQ = (clone $bookingsQ)->where('status', 'accepted');

            $attendedCount = (clone $acceptedQ)->where('attendedbutton', true)->count();
            $notAttendedCount = (clone $acceptedQ)->where('attendedbutton', false)->count();
            $notMarkedCount = (clone $acceptedQ)->whereNull('attendedbutton')->count();

            $totalClassesCreated = (clone $classesQ)->count();

            // Total attended overall (same as attendedCount, but kept separate for clarity)
            $totalAttendedOverall = $attendedCount;

            // Pie chart (attended vs not attended vs not marked)
            $pie = [
                ['label' => 'Attended', 'value' => $attendedCount],
                ['label' => 'Not Attended', 'value' => $notAttendedCount],
                ['label' => 'Not Marked', 'value' => $notMarkedCount],
            ];

            // Line chart: attended per day (by booking.created_at)
            // Postgres-friendly with DATE(created_at)
            $lineRows = (clone $acceptedQ)
                ->selectRaw("DATE(created_at) as day")
                ->selectRaw("SUM(CASE WHEN attendedbutton = true THEN 1 ELSE 0 END) as attended")
                ->selectRaw("SUM(CASE WHEN attendedbutton = false THEN 1 ELSE 0 END) as not_attended")
                ->selectRaw("SUM(CASE WHEN attendedbutton IS NULL THEN 1 ELSE 0 END) as not_marked")
                ->groupBy(DB::raw("DATE(created_at)"))
                ->orderBy(DB::raw("DATE(created_at)"), 'asc')
                ->get();

            $line = $lineRows->map(fn ($r) => [
                'day' => $r->day,
                'attended' => (int) $r->attended,
                'not_attended' => (int) $r->not_attended,
                'not_marked' => (int) $r->not_marked,
            ])->values();

            // Requests per class (grouped)
            // Matching the logic you use in frontend: booking.name == class title, booking.start_date == class start_date
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

            $perClass = $perClassRows->map(fn ($r) => [
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
                    'accepted_total' => (clone $acceptedQ)->count(),
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
}
