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

                // ✅ "No offerings" se considera cuando el time_range está en "00:00 - 00:00"
                $isNoOfferings = trim((string) $cls->time_range) === '00:00 - 00:00';

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

                    // ✅ Draft solo si realmente lo manejas aparte; aquí lo dejamos por compatibilidad
                    'is_draft' => ($cls->level === 'Draft'),

                    // ✅ útil si quieres mostrar badge o debug
                    'is_no_offerings' => $isNoOfferings,
                ];
            }),
        ]);
    }

    /**
     * LISTADO PÚBLICO (GET /api/classes)
     * ✅ mantiene compatibilidad: excluye Draft
     * ✅ PERO tus "sin horas" ya NO serán Draft, serán General con "00:00 - 00:00"
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
     * LISTADO ADMIN AGRUPADO (GET /api/admin/classes-grouped)
     * Incluye todo (incluso Draft si existieran)
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
     * ✅ Excluye Draft (compatibilidad con tu BookingCalendar)
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
     * ✅ NUEVO REQUISITO:
     * - Si NO se escogen horas, la clase queda PUBLICADA (General) pero debe salir sin sesiones (sessions_count=0)
     * - Para eso, detectamos grupos "sin offerings" por time_range "00:00 - 00:00"
     */
    private function groupedResponse($rows)
    {
        $grouped = $rows->groupBy(function ($c) {
            return $c->group_code ?: ('single-' . $c->id);
        });

        $classes = $grouped->map(function ($items, $groupCode) {
            $first = $items->first();

            // ✅ toma el primer workday_url no vacío del grupo
            $workdayUrl = $items->pluck('workday_url')->filter()->first();

            // ✅ ids para borrar desde admin (incluye base + sesiones)
            $allIds = $items->pluck('id')->values()->all();

            // ✅ Draft group (se mantiene por compatibilidad si aún existen Draft viejos)
            $isDraftGroup = $items->every(fn ($x) => $x->level === 'Draft');

            // ✅ NUEVO: "No offerings scheduled yet" => todos con 00:00 - 00:00 o vacío
            $isNoOfferingsGroup = $items->every(function ($x) {
                $tr = trim((string) ($x->time_range ?? ''));
                return $tr === '' || $tr === '00:00 - 00:00';
            });

            // Si es Draft "real" (por compatibilidad)
            if ($isDraftGroup) {
                return [
                    'group_code' => $groupCode,
                    'base_id' => $first->id,
                    'all_session_ids' => $allIds,
                    'is_draft' => true,

                    'title' => $first->title,
                    'trainer_name' => $first->trainer_name,
                    'modality' => $first->modality,
                    'level' => $first->level,
                    'audience' => $first->audience ?? 'all_employees',
                    'description' => $first->description,
                    'workday_url' => $workdayUrl ?? null,

                    'start_date_iso' => null,
                    'end_date_iso' => null,

                    'sessions_count' => 0,
                    'sessions' => [],
                ];
            }

            // ✅ NUEVO: publicado pero sin sesiones reales
            if ($isNoOfferingsGroup) {
                return [
                    'group_code' => $groupCode,
                    'base_id' => $first->id,
                    'all_session_ids' => $allIds,
                    'is_draft' => false, // ✅ publicado

                    'title' => $first->title,
                    'trainer_name' => $first->trainer_name,
                    'modality' => $first->modality,
                    'level' => $first->level, // típicamente General
                    'audience' => $first->audience ?? 'all_employees',
                    'description' => $first->description,
                    'workday_url' => $workdayUrl ?? null,

                    'start_date_iso' => null,
                    'end_date_iso' => null,

                    'sessions_count' => 0,
                    'sessions' => [],
                ];
            }

            // ✅ caso normal: hay sesiones reales
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

            return [
                'group_code' => $groupCode,
                'base_id' => $first->id,
                'all_session_ids' => $allIds,
                'is_draft' => false,

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
     * CREAR CLASE (ADMIN – POST /api/admin/classes)
     * ✅ NUEVO REQUISITO:
     * - Si NO se escogen horas: queda PUBLICADA (General), NO Draft
     * - Se guarda time_range NOT NULL como "00:00 - 00:00"
     */
    public function store(Request $request)
    {
        // Normaliza vacíos a null para evitar 422 por date_format
        $request->merge([
            'start_time' => $request->input('start_time') === '' ? null : $request->input('start_time'),
            'end_time' => $request->input('end_time') === '' ? null : $request->input('end_time'),
            'start_date' => $request->input('start_date') === '' ? null : $request->input('start_date'),
            'end_date' => $request->input('end_date') === '' ? null : $request->input('end_date'),
            'trainer_name' => $request->input('trainer_name') === '' ? null : $request->input('trainer_name'),
        ]);

        $validated = $request->validate([
            'title' => 'required|string',

            // ✅ puedes dejarlo nullable si quieres permitir crear sin trainer (si NO, cambia a required)
            'trainer_name' => 'sometimes|nullable|string',

            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'start_time' => 'sometimes|nullable|date_format:H:i',
            'end_time' => 'sometimes|nullable|date_format:H:i',

            'modality' => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'workday_url' => 'nullable|string|max:2000',
            'audience' => 'nullable|in:sales,all_employees,new_hires,hr,it,legal,manager_leaders,records',
        ]);

        $hasTimes = !empty($validated['start_time']) && !empty($validated['end_time']);

        $class = new ClassSession();
        $class->title = $validated['title'];
        $class->trainer_name = $validated['trainer_name'] ?? null;

        // si no hay start_date, usamos hoy para cumplir NOT NULL
        $class->date_iso = $validated['start_date'] ?? now()->toDateString();
        $class->end_date_iso = $validated['end_date'] ?? ($validated['start_date'] ?? $class->date_iso);

        // time_range siempre NOT NULL
        $class->time_range = $hasTimes
            ? ($validated['start_time'] . ' - ' . $validated['end_time'])
            : '00:00 - 00:00';

        $class->modality = $validated['modality'];

        // ✅ CLAVE: aunque no haya horas, queda PUBLICADA
        $class->level = 'General';

        $class->spots_left = (int) $validated['spots_left'];
        $class->description = $validated['description'] ?? null;

        $class->workday_url = $validated['workday_url'] ?? null;
        $class->audience = $validated['audience'] ?? 'all_employees';

        $class->group_code = (string) Str::uuid();

        $class->save();

        return response()->json(['class' => $class], 201);
    }

    /**
     * ACTUALIZAR CLASE (ADMIN – PUT /api/admin/classes/{id})
     * ✅ NUEVO REQUISITO:
     * - Si quitan horas, sigue PUBLICADA (General) con "00:00 - 00:00"
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

        $validated = $request->validate([
            'title' => 'required|string',
            'trainer_name' => 'sometimes|nullable|string',

            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'start_time' => 'sometimes|nullable|date_format:H:i',
            'end_time' => 'sometimes|nullable|date_format:H:i',

            'modality' => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'workday_url' => 'nullable|string|max:2000',
            'audience' => 'nullable|in:sales,all_employees,new_hires,hr,it,legal,manager_leaders,records',
        ]);

        $class->title = $validated['title'];
        if (array_key_exists('trainer_name', $validated)) {
            $class->trainer_name = $validated['trainer_name'] ?? null;
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

        // ✅ CLAVE: siempre publicado
        $class->level = 'General';

        $class->spots_left = (int) $validated['spots_left'];
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
     * SYNC SESIONES (PUT /api/admin/classes/{id}/sessions)
     * Requiere sessions >= 1 (para “publicar” sesiones reales)
     */
    public function syncSessions(Request $request, $id)
    {
        $base = ClassSession::findOrFail($id);

        $validated = $request->validate([
            'sessions' => 'required|array|min:1',
            'sessions.*.id' => 'nullable|integer',
            'sessions.*.date_iso' => ['required', 'date_format:Y-m-d'],
            'sessions.*.start_time' => ['required', 'date_format:H:i'],
            'sessions.*.end_time' => ['required', 'date_format:H:i'],
            'workday_url' => 'nullable|string|max:2000',
            'audience' => 'nullable|in:sales,all_employees,new_hires,hr,it,legal,manager_leaders,records',
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

        return DB::transaction(function () use ($validated, $base, $groupCode, $incomingIds, $rangeStart, $rangeEnd, $workdayUrl, $audience) {

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

            // ✅ CLAVE: siempre General
            $base->level = 'General';

            $base->save();

            foreach ($validated['sessions'] as $s) {
                $timeRange = $s['start_time'] . ' - ' . $s['end_time'];

                if (!empty($s['id'])) {
                    $row = ClassSession::where('group_code', $groupCode)
                        ->where('id', (int) $s['id'])
                        ->firstOrFail();

                    $row->title = $base->title;
                    $row->trainer_name = $base->trainer_name;
                    $row->modality = $base->modality;
                    $row->level = 'General';
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
                        'level' => 'General',
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
