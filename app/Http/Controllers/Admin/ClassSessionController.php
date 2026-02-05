<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ClassSessionController extends Controller
{
    /**
     * LISTADO PARA ADMIN  (/api/admin/classes)
     */
    public function index()
    {
        $classes = ClassSession::orderBy('date_iso')->orderBy('time_range')->get();

        return response()->json([
            'classes' => $classes->map(function (ClassSession $cls) {

                $startDate = $cls->date_iso;
                $endDate = $cls->end_date_iso ?? $cls->date_iso;

                $startTime = null;
                $endTime = null;

                if ($cls->time_range && str_contains($cls->time_range, '-')) {
                    [$startTime, $endTime] = array_map('trim', explode('-', $cls->time_range));
                }

                return [
                    'id' => $cls->id,
                    'title' => $cls->title,
                    'trainer_name' => $cls->trainer_name,

                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,

                    'date_iso' => $cls->date_iso,
                    'end_date_iso' => $cls->end_date_iso,
                    'time_range' => $cls->time_range,

                    'modality' => $cls->modality,
                    'level' => $cls->level,
                    'spots_left' => $cls->spots_left,
                    'description' => $cls->description,

                    'workday_url' => $cls->workday_url ?? null,
                    'audience' => $cls->audience ?? 'all_employees',

                    'group_code' => $cls->group_code ?? null,
                ];
            }),
        ]);
    }

    /**
     * LISTADO PÚBLICO DEL CALENDARIO (/api/classes)
     */
    public function indexPublic()
    {
        $classes = ClassSession::orderBy('date_iso')->orderBy('time_range')->get();

        return response()->json([
            'classes' => $classes->map(function (ClassSession $cls) {

                $startDate = $cls->date_iso;
                $endDate = $cls->end_date_iso ?? $cls->date_iso;

                $startTime = null;
                $endTime = null;

                if ($cls->time_range && str_contains($cls->time_range, '-')) {
                    [$startTime, $endTime] = array_map('trim', explode('-', $cls->time_range));
                }

                return [
                    'id' => $cls->id,
                    'title' => $cls->title,
                    'trainer_name' => $cls->trainer_name,

                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,

                    'date_iso' => $cls->date_iso,
                    'time_range' => $cls->time_range,

                    'modality' => $cls->modality,
                    'level' => $cls->level,
                    'spots_left' => $cls->spots_left,
                    'description' => $cls->description,

                    'workday_url' => $cls->workday_url ?? null,
                    'audience' => $cls->audience ?? 'all_employees',

                    'group_code' => $cls->group_code ?? null,
                ];
            }),
        ]);
    }

    /**
     * CREAR CLASE (ADMIN – POST /api/admin/classes)
     * ✅ Permite guardar aunque NO haya sesiones (start_time/end_time pueden ser null)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'trainer_name' => 'required|string|max:255',

            // ✅ ahora opcionales (para permitir 0 sesiones)
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',

            'modality' => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'workday_url' => 'nullable|string|max:2000',
            'audience' => 'nullable|in:sales,all_employees,new_hires,hr,it,legal,records',
        ]);

        $class = new ClassSession();
        $class->title = $validated['title'];
        $class->trainer_name = $validated['trainer_name'];

        $class->date_iso = $validated['start_date'] ?? now()->toDateString();
        $class->end_date_iso = $validated['end_date'] ?? ($validated['start_date'] ?? $class->date_iso);

        $start = $validated['start_time'] ?? null;
        $end   = $validated['end_time'] ?? null;
        $class->time_range = ($start && $end) ? "{$start} - {$end}" : null;

        $class->modality = $validated['modality'];
        $class->level = 'General';
        $class->spots_left = $validated['spots_left'];
        $class->description = $validated['description'] ?? null;

        $class->workday_url = $validated['workday_url'] ?? null;
        $class->audience = $validated['audience'] ?? 'all_employees';

        $class->group_code = (string) Str::uuid();
        $class->save();

        return response()->json(['class' => $class], 201);
    }

    /**
     * LISTADO PÚBLICO AGRUPADO (/api/classes-grouped)
     */
    public function indexPublicGrouped()
    {
        $rows = ClassSession::orderBy('date_iso')
            ->orderBy('time_range')
            ->get();

        $grouped = $rows->groupBy(function ($c) {
            return $c->group_code ?: ('single-' . $c->id);
        });

        $classes = $grouped->map(function ($items, $groupCode) {

            $first = $items->first();

            $minDate = $items->min('date_iso');
            $maxDate = $items->max('date_iso');

            $sessions = $items->map(function ($s) {
                return [
                    'id' => $s->id,
                    'date_iso' => $s->date_iso,
                    'time_range' => $s->time_range,
                    'spots_left' => (int) $s->spots_left,
                    'workday_url' => $s->workday_url ?? null,
                ];
            })->values();

            $workdayUrl = $items->pluck('workday_url')->filter()->first();

            return [
                'group_code' => $groupCode,
                'title' => $first->title,
                'trainer_name' => $first->trainer_name,
                'modality' => $first->modality,
                'level' => $first->level,
                'audience' => $first->audience ?? 'all_employees',
                'description' => $first->description,

                'workday_url' => $workdayUrl ?? null,

                'start_date_iso' => $minDate,
                'end_date_iso' => $maxDate,

                'sessions_count' => $sessions->count(),
                'sessions' => $sessions,
            ];
        })->values();

        return response()->json(['classes' => $classes]);
    }

    /**
     * ACTUALIZAR CLASE (ADMIN – PUT /api/admin/classes/{id})
     * ✅ start_time/end_time opcionales para permitir 0 sesiones
     */
    public function update(Request $request, $id)
    {
        $class = ClassSession::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string',
            'trainer_name' => 'required|string|max:255',

            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',

            'modality' => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'workday_url' => 'nullable|string|max:2000',
            'audience' => 'nullable|in:sales,all_employees,new_hires,hr,it,legal,records',
        ]);

        $class->title = $validated['title'];
        $class->trainer_name = $validated['trainer_name'];

        if (!empty($validated['start_date'])) {
            $class->date_iso = $validated['start_date'];
        }
        if (!empty($validated['end_date'])) {
            $class->end_date_iso = $validated['end_date'];
        }

        $start = $validated['start_time'] ?? null;
        $end   = $validated['end_time'] ?? null;
        $class->time_range = ($start && $end) ? "{$start} - {$end}" : $class->time_range;

        $class->modality = $validated['modality'];
        $class->level = 'General';
        $class->spots_left = $validated['spots_left'];
        $class->description = $validated['description'] ?? null;

        $class->workday_url = $validated['workday_url'] ?? null;
        $class->audience = $validated['audience'] ?? 'all_employees';

        if (!$class->group_code) {
            $class->group_code = (string) Str::uuid();
        }

        $class->save();

        return response()->json(['class' => $class]);
    }

    /**
     * ELIMINAR CLASE (ADMIN – DELETE /api/admin/classes/{id})
     */
    public function destroy($id)
    {
        $class = ClassSession::findOrFail($id);
        $class->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * ✅ SYNC SESIONES (PUT /api/admin/classes/{id}/sessions)
     * ✅ Ahora permite sessions: [] (o no mandar sessions)
     * ✅ Propaga workday_url + audience a todo el grupo
     */
    public function syncSessions(Request $request, $id)
    {
        $base = ClassSession::findOrFail($id);

        $validated = $request->validate([
            'sessions' => 'sometimes|array',
            'sessions.*.id' => 'nullable|integer',
            'sessions.*.date_iso' => 'required_with:sessions|date_format:Y-m-d',
            'sessions.*.start_time' => 'required_with:sessions|date_format:H:i',
            'sessions.*.end_time' => 'required_with:sessions|date_format:H:i',

            'workday_url' => 'nullable|string|max:2000',
            'audience' => 'nullable|in:sales,all_employees,new_hires,hr,it,legal,records',
        ]);

        if (!$base->group_code) {
            $base->group_code = (string) Str::uuid();
            $base->save();
        }

        $groupCode = $base->group_code;

        $workdayUrl = array_key_exists('workday_url', $validated)
            ? ($validated['workday_url'] ?: null)
            : $base->workday_url;

        $audience = array_key_exists('audience', $validated)
            ? ($validated['audience'] ?: null)
            : $base->audience;

        $incoming = collect($validated['sessions'] ?? []);

        // ✅ Si NO vienen sesiones (0), NO borramos nada. Solo propagamos meta.
        if ($incoming->isEmpty()) {
            return DB::transaction(function () use ($base, $groupCode, $workdayUrl, $audience) {
                $base->workday_url = $workdayUrl;
                $base->audience = $audience ?? $base->audience ?? 'all_employees';
                $base->save();

                // opcional: propaga a todo el grupo
                ClassSession::where('group_code', $groupCode)->update([
                    'workday_url' => $base->workday_url,
                    'audience' => $base->audience,
                ]);

                $fresh = ClassSession::where('group_code', $groupCode)
                    ->orderBy('date_iso')
                    ->orderBy('time_range')
                    ->get();

                return response()->json([
                    'ok' => true,
                    'group_code' => $groupCode,
                    'sessions_count' => $fresh->count(),
                    'sessions' => $fresh,
                    'workday_url' => $fresh->pluck('workday_url')->filter()->first(),
                    'audience' => $fresh->pluck('audience')->filter()->first() ?? 'all_employees',
                ]);
            });
        }

        // ✅ calcular rango real con sesiones
        $dates = $incoming->pluck('date_iso')->sort()->values();
        $rangeStart = $dates->first();
        $rangeEnd = $dates->last();

        $incomingIds = $incoming
            ->pluck('id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->values();

        return DB::transaction(function () use ($incoming, $base, $groupCode, $incomingIds, $rangeStart, $rangeEnd, $workdayUrl, $audience) {
            // ✅ delete seguro: solo si hay IDs (y nunca borres la base)
            if ($incomingIds->isNotEmpty()) {
                ClassSession::where('group_code', $groupCode)
                    ->whereNotIn('id', $incomingIds)
                    ->where('id', '!=', $base->id)
                    ->delete();
            }

            // ✅ actualiza base con rango real + time_range de la primera sesión
            $first = $incoming->first();
            $base->date_iso = $rangeStart;
            $base->end_date_iso = $rangeEnd;
            $base->time_range = $first['start_time'] . ' - ' . $first['end_time'];
            $base->workday_url = $workdayUrl;
            $base->audience = $audience ?? $base->audience ?? 'all_employees';
            $base->save();

            foreach ($incoming as $s) {
                $timeRange = $s['start_time'] . ' - ' . $s['end_time'];

                if (!empty($s['id'])) {
                    $row = ClassSession::where('group_code', $groupCode)
                        ->where('id', (int) $s['id'])
                        ->firstOrFail();

                    $row->title = $base->title;
                    $row->trainer_name = $base->trainer_name;
                    $row->modality = $base->modality;
                    $row->level = $base->level ?? 'General';
                    $row->spots_left = $base->spots_left;
                    $row->description = $base->description;
                    $row->workday_url = $workdayUrl;
                    $row->audience = $base->audience;

                    $row->date_iso = $s['date_iso'];
                    $row->end_date_iso = $rangeEnd;
                    $row->time_range = $timeRange;
                    $row->group_code = $groupCode;

                    $row->save();
                } else {
                    ClassSession::create([
                        'title' => $base->title,
                        'trainer_name' => $base->trainer_name,
                        'date_iso' => $s['date_iso'],
                        'end_date_iso' => $rangeEnd,
                        'time_range' => $timeRange,
                        'modality' => $base->modality,
                        'level' => $base->level ?? 'General',
                        'spots_left' => $base->spots_left,
                        'description' => $base->description,
                        'workday_url' => $workdayUrl,
                        'audience' => $base->audience,
                        'group_code' => $groupCode,
                    ]);
                }
            }

            $fresh = ClassSession::where('group_code', $groupCode)
                ->orderBy('date_iso')
                ->orderBy('time_range')
                ->get();

            return response()->json([
                'ok' => true,
                'group_code' => $groupCode,
                'range' => ['start' => $rangeStart, 'end' => $rangeEnd],
                'sessions_count' => $fresh->count(),
                'sessions' => $fresh,
                'workday_url' => $fresh->pluck('workday_url')->filter()->first(),
                'audience' => $fresh->pluck('audience')->filter()->first() ?? 'all_employees',
            ]);
        });
    }
}
