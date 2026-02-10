<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClassSessionController extends Controller
{
    /**
     * LISTADO PARA ADMIN (GET /api/admin/classes)
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

                $isNoOfferings = trim((string) $cls->time_range) === '00:00 - 00:00';

                $trainerNames = is_array($cls->trainer_names) ? $cls->trainer_names : [];
                if (empty($trainerNames) && !empty($cls->trainer_name)) {
                    $trainerNames = [$cls->trainer_name];
                }

                return [
                    'id' => $cls->id,
                    'title' => $cls->title,

                    // ✅ compat + multi
                    'trainer_name' => $cls->trainer_name,
                    'trainer_names' => $trainerNames,

                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,

                    'date_iso' => $cls->date_iso,
                    'end_date_iso' => $cls->end_date_iso,
                    'time_range' => $cls->time_range,

                    'modality' => $cls->modality,
                    'level' => $cls->level,
                    'spots_left' => (int) $cls->spots_left, // ✅
                    'description' => $cls->description,

                    'workday_url' => $cls->workday_url ?? null,
                    'audience' => $cls->audience ?? 'all_employees',
                    'group_code' => $cls->group_code ?? null,

                    'is_draft' => ($cls->level === 'Draft'),
                    'is_no_offerings' => $isNoOfferings,
                ];
            }),
        ]);
    }

    /**
     * LISTADO PÚBLICO (GET /api/classes)
     * ✅ excluye Draft
     */
    public function indexPublic()
    {
        $classes = ClassSession::where('level', '!=', 'Draft')
            ->orderBy('date_iso')
            ->orderBy('time_range')
            ->get();

        return response()->json([
            'classes' => $classes->map(function (ClassSession $cls) {
                $startDate = $cls->date_iso;
                $endDate = $cls->end_date_iso ?? $cls->date_iso;

                $startTime = null;
                $endTime = null;

                if ($cls->time_range && str_contains($cls->time_range, '-')) {
                    [$startTime, $endTime] = array_map('trim', explode('-', $cls->time_range));
                }

                $trainerNames = is_array($cls->trainer_names) ? $cls->trainer_names : [];
                if (empty($trainerNames) && !empty($cls->trainer_name)) {
                    $trainerNames = [$cls->trainer_name];
                }

                return [
                    'id' => $cls->id,
                    'title' => $cls->title,

                    // ✅ compat + multi
                    'trainer_name' => $cls->trainer_name,
                    'trainer_names' => $trainerNames,

                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,

                    'date_iso' => $cls->date_iso,
                    'time_range' => $cls->time_range,

                    'modality' => $cls->modality,
                    'level' => $cls->level,
                    'spots_left' => (int) $cls->spots_left, // ✅
                    'description' => $cls->description,

                    'workday_url' => $cls->workday_url ?? null,
                    'audience' => $cls->audience ?? 'all_employees',
                    'group_code' => $cls->group_code ?? null,
                ];
            }),
        ]);
    }

    /**
     * LISTADO ADMIN AGRUPADO (GET /api/admin/classes-grouped)
     */
    public function indexAdminGrouped()
    {
        $rows = ClassSession::orderBy('date_iso')
            ->orderBy('time_range')
            ->get();

        return $this->groupedResponse($rows);
    }

    /**
     * LISTADO PÚBLICO AGRUPADO (GET /api/classes-grouped)
     * ✅ Excluye Draft
     */
    public function indexPublicGrouped()
    {
        $rows = ClassSession::where('level', '!=', 'Draft')
            ->orderBy('date_iso')
            ->orderBy('time_range')
            ->get();

        return $this->groupedResponse($rows);
    }

    /**
     * Helper: arma la respuesta agrupada
     * ✅ INCLUYE spots_left A NIVEL DE GRUPO SIEMPRE (inclusive Draft/NoOfferings)
     */
    private function groupedResponse($rows)
    {
        $grouped = $rows->groupBy(function ($c) {
            return $c->group_code ?: ('single-' . $c->id);
        });

        $classes = $grouped->map(function ($items, $groupCode) {
            $first = $items->first();

            $workdayUrl = $items->pluck('workday_url')->filter()->first();
            $allIds = $items->pluck('id')->values()->all();

            $isDraftGroup = $items->every(fn ($x) => $x->level === 'Draft');

            $isNoOfferingsGroup = $items->every(function ($x) {
                $tr = trim((string) ($x->time_range ?? ''));
                return $tr === '' || $tr === '00:00 - 00:00';
            });

            $trainerNames = $items->pluck('trainer_names')
                ->filter(fn ($v) => is_array($v) && count($v) > 0)
                ->first();

            if (!is_array($trainerNames)) {
                $trainerNames = [];
            }
            if (empty($trainerNames) && !empty($first->trainer_name)) {
                $trainerNames = [$first->trainer_name];
            }

            // ✅ spots_left a nivel de grupo:
            // - preferimos el valor del base (first)
            // - si por alguna razón viene null, fallback al máx de sesiones
            $groupSpots = (int) ($first->spots_left ?? $items->max('spots_left') ?? 0);

            // Base común del payload
            $basePayload = [
                'group_code' => $groupCode,
                'base_id' => $first->id,
                'all_session_ids' => $allIds,

                'title' => $first->title,
                'trainer_name' => $first->trainer_name,
                'trainer_names' => $trainerNames,

                'modality' => $first->modality,
                'level' => $first->level,
                'audience' => $first->audience ?? 'all_employees',
                'description' => $first->description,
                'workday_url' => $workdayUrl ?? null,

                // ✅ NUEVO: siempre presente
                'spots_left' => $groupSpots,
            ];

            if ($isDraftGroup) {
                return array_merge($basePayload, [
                    'is_draft' => true,
                    'start_date_iso' => null,
                    'end_date_iso' => null,
                    'sessions_count' => 0,
                    'sessions' => [],
                ]);
            }

            if ($isNoOfferingsGroup) {
                return array_merge($basePayload, [
                    'is_draft' => false,
                    'start_date_iso' => null,
                    'end_date_iso' => null,
                    'sessions_count' => 0,
                    'sessions' => [],
                ]);
            }

            $minDate = $items->min('date_iso');
            $maxDate = $items->max('date_iso');

            $sessions = $items->map(function ($s) {
                return [
                    'id' => $s->id,
                    'date_iso' => $s->date_iso,
                    'time_range' => $s->time_range,
                    'spots_left' => (int) $s->spots_left,
                ];
            })->values();

            return array_merge($basePayload, [
                'is_draft' => false,
                'start_date_iso' => $minDate,
                'end_date_iso' => $maxDate,
                'sessions_count' => $sessions->count(),
                'sessions' => $sessions,
            ]);
        })->values();

        return response()->json(['classes' => $classes]);
    }

    /**
     * CREAR CLASE (ADMIN – POST /api/admin/classes)
     */
    public function store(Request $request)
    {
        $request->merge([
            'start_time' => $request->input('start_time') === '' ? null : $request->input('start_time'),
            'end_time' => $request->input('end_time') === '' ? null : $request->input('end_time'),
            'start_date' => $request->input('start_date') === '' ? null : $request->input('start_date'),
            'end_date' => $request->input('end_date') === '' ? null : $request->input('end_date'),
            'trainer_name' => $request->input('trainer_name') === '' ? null : $request->input('trainer_name'),
        ]);

        if ($request->input('audience') === 'managers_leaders') {
            $request->merge(['audience' => 'manager_leaders']);
        }

        $validated = $request->validate([
            'title' => 'required|string',

            // ✅ multi trainers
            'trainer_names' => 'sometimes|array',
            'trainer_names.*' => 'string|max:255',

            // ✅ compat legacy
            'trainer_name' => 'sometimes|nullable|string|max:255',

            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'start_time' => 'sometimes|nullable|date_format:H:i',
            'end_time' => 'sometimes|nullable|date_format:H:i',

            'modality' => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0', // ✅
            'description' => 'nullable|string',
            'workday_url' => 'nullable|string|max:2000',

            'audience' => 'nullable|in:sales,all_employees,new_hires,hr,it,legal,manager_leaders,managers_leaders,records',
        ]);

        $trainerNames = [];
        if (array_key_exists('trainer_names', $validated) && is_array($validated['trainer_names'])) {
            $trainerNames = array_values(array_filter(
                $validated['trainer_names'],
                fn ($v) => is_string($v) && trim($v) !== ''
            ));
        } elseif (!empty($validated['trainer_name'])) {
            $trainerNames = [$validated['trainer_name']];
        }

        $trainerNameCompat = $trainerNames[0] ?? ($validated['trainer_name'] ?? null);

        $hasTimes = !empty($validated['start_time']) && !empty($validated['end_time']);

        $class = new ClassSession();
        $class->title = $validated['title'];

        // ✅ multi + compat
        $class->trainer_names = $trainerNames;
        $class->trainer_name = $trainerNameCompat;

        $class->date_iso = $validated['start_date'] ?? now()->toDateString();
        $class->end_date_iso = $validated['end_date'] ?? ($validated['start_date'] ?? $class->date_iso);

        $class->time_range = $hasTimes
            ? ($validated['start_time'] . ' - ' . $validated['end_time'])
            : '00:00 - 00:00';

        $class->modality = $validated['modality'];
        $class->level = 'General';

        $class->spots_left = (int) $validated['spots_left']; // ✅
        $class->description = $validated['description'] ?? null;

        $class->workday_url = $validated['workday_url'] ?? null;

        $aud = $validated['audience'] ?? 'all_employees';
        $class->audience = $aud === 'managers_leaders' ? 'manager_leaders' : $aud;

        $class->group_code = (string) Str::uuid();

        $class->save();

        return response()->json(['class' => $class], 201);
    }

    /**
     * ACTUALIZAR CLASE (ADMIN – PUT /api/admin/classes/{id})
     */
    public function update(Request $request, $id)
    {
        $class = ClassSession::findOrFail($id);

        $request->merge([
            'start_time' => $request->input('start_time') === '' ? null : $request->input('start_time'),
            'end_time' => $request->input('end_time') === '' ? null : $request->input('end_time'),
            'start_date' => $request->input('start_date') === '' ? null : $request->input('start_date'),
            'end_date' => $request->input('end_date') === '' ? null : $request->input('end_date'),
            'trainer_name' => $request->input('trainer_name') === '' ? null : $request->input('trainer_name'),
        ]);

        if ($request->input('audience') === 'managers_leaders') {
            $request->merge(['audience' => 'manager_leaders']);
        }

        $validated = $request->validate([
            'title' => 'required|string',

            // ✅ multi trainers
            'trainer_names' => 'sometimes|array',
            'trainer_names.*' => 'string|max:255',

            // ✅ compat legacy
            'trainer_name' => 'sometimes|nullable|string|max:255',

            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'start_time' => 'sometimes|nullable|date_format:H:i',
            'end_time' => 'sometimes|nullable|date_format:H:i',

            'modality' => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0', // ✅
            'description' => 'nullable|string',
            'workday_url' => 'nullable|string|max:2000',

            'audience' => 'nullable|in:sales,all_employees,new_hires,hr,it,legal,manager_leaders,managers_leaders,records',
        ]);

        $class->title = $validated['title'];

        // ✅ multi + compat: SOLO si lo mandaron (para no pisar sin querer)
        if (array_key_exists('trainer_names', $validated)) {
            $trainerNames = is_array($validated['trainer_names'])
                ? array_values(array_filter(
                    $validated['trainer_names'],
                    fn ($v) => is_string($v) && trim($v) !== ''
                ))
                : [];

            $class->trainer_names = $trainerNames;
            $class->trainer_name = $trainerNames[0] ?? null; // compat
        } elseif (array_key_exists('trainer_name', $validated)) {
            $class->trainer_name = $validated['trainer_name'] ?? null;
            if (!empty($class->trainer_name)) {
                $class->trainer_names = [$class->trainer_name];
            }
        }

        if (array_key_exists('start_date', $validated) && !empty($validated['start_date'])) {
            $class->date_iso = $validated['start_date'];
        }
        if (array_key_exists('end_date', $validated) && !empty($validated['end_date'])) {
            $class->end_date_iso = $validated['end_date'];
        }

        $hasTimes = !empty($validated['start_time']) && !empty($validated['end_time']);
        $class->time_range = $hasTimes
            ? ($validated['start_time'] . ' - ' . $validated['end_time'])
            : '00:00 - 00:00';

        $class->modality = $validated['modality'];
        $class->level = 'General';

        $class->spots_left = (int) $validated['spots_left']; // ✅
        $class->description = $validated['description'] ?? null;

        $class->workday_url = $validated['workday_url'] ?? null;

        $aud = $validated['audience'] ?? 'all_employees';
        $class->audience = $aud === 'managers_leaders' ? 'manager_leaders' : $aud;

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
     * 🔥 aplica spots_left a base + TODAS las sesiones del grupo
     */
    public function syncSessions(Request $request, $id)
    {
        $base = ClassSession::findOrFail($id);

        if ($request->input('audience') === 'managers_leaders') {
            $request->merge(['audience' => 'manager_leaders']);
        }

        $validated = $request->validate([
            'sessions' => 'required|array|min:1',
            'sessions.*.id' => 'nullable|integer',
            'sessions.*.date_iso' => ['required', 'date_format:Y-m-d'],
            'sessions.*.start_time' => ['required', 'date_format:H:i'],
            'sessions.*.end_time' => ['required', 'date_format:H:i'],

            // ✅ spots_left global
            'spots_left' => 'required|integer|min:0',

            'workday_url' => 'nullable|string|max:2000',
            'audience' => 'nullable|in:sales,all_employees,new_hires,hr,it,legal,manager_leaders,managers_leaders,records',
        ]);

        if (!$base->group_code) {
            $base->group_code = (string) Str::uuid();
            $base->save();
        }

        $groupCode = $base->group_code;

        $dates = collect($validated['sessions'])->pluck('date_iso')->sort()->values();
        $rangeStart = $dates->first();
        $rangeEnd = $dates->last();

        $incomingIds = collect($validated['sessions'])
            ->pluck('id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->values();

        $workdayUrl = array_key_exists('workday_url', $validated)
            ? ($validated['workday_url'] ?: null)
            : $base->workday_url;

        $audience = array_key_exists('audience', $validated)
            ? ($validated['audience'] ?: null)
            : $base->audience;

        if ($audience === 'managers_leaders') {
            $audience = 'manager_leaders';
        }

        $spotsLeft = (int) $validated['spots_left'];

        return DB::transaction(function () use (
            $validated,
            $base,
            $groupCode,
            $incomingIds,
            $rangeStart,
            $rangeEnd,
            $workdayUrl,
            $audience,
            $spotsLeft
        ) {
            // borra las que no vienen, pero nunca borra la base
            if ($incomingIds->isNotEmpty()) {
                ClassSession::where('group_code', $groupCode)
                    ->whereNotIn('id', $incomingIds)
                    ->where('id', '!=', $base->id)
                    ->delete();
            }

            $first = $validated['sessions'][0];

            // actualiza base con rango real y primera hora
            $base->date_iso = $rangeStart;
            $base->end_date_iso = $rangeEnd;
            $base->time_range = $first['start_time'] . ' - ' . $first['end_time'];
            $base->workday_url = $workdayUrl;
            $base->audience = $audience ?? $base->audience ?? 'all_employees';
            $base->level = 'General';

            // ✅ aplica cupos al base
            $base->spots_left = $spotsLeft;

            // asegura compat trainers
            if ((!is_array($base->trainer_names) || count($base->trainer_names) === 0) && !empty($base->trainer_name)) {
                $base->trainer_names = [$base->trainer_name];
            }

            $base->save();

            foreach ($validated['sessions'] as $s) {
                $timeRange = $s['start_time'] . ' - ' . $s['end_time'];

                if (!empty($s['id'])) {
                    $row = ClassSession::where('group_code', $groupCode)
                        ->where('id', (int) $s['id'])
                        ->firstOrFail();

                    $row->title = $base->title;

                    $row->trainer_names = is_array($base->trainer_names) ? $base->trainer_names : [];
                    $row->trainer_name = $base->trainer_name;

                    $row->modality = $base->modality;
                    $row->level = 'General';

                    // ✅ aplica cupos a cada sesión
                    $row->spots_left = $spotsLeft;

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

                        'trainer_names' => is_array($base->trainer_names) ? $base->trainer_names : [],
                        'trainer_name' => $base->trainer_name,

                        'date_iso' => $s['date_iso'],
                        'end_date_iso' => $rangeEnd,
                        'time_range' => $timeRange,
                        'modality' => $base->modality,
                        'level' => 'General',

                        'spots_left' => $spotsLeft,

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
                'spots_left' => (int) ($fresh->first()?->spots_left ?? $spotsLeft), // ✅ útil para el FE
            ]);
        });
    }
}
