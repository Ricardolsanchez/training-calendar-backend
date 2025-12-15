<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use Illuminate\Http\Request;

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
                    'trainer_id' => $cls->trainer_id,
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
                ];
            }),
        ]);
    }

    /**
     * CREAR CLASE (ADMIN – POST /api/admin/classes)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'trainer_name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'modality' => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0',
            'description' => 'nullable|string',

            // 🔥 NUEVO (opcional): sesiones
            'sessions' => 'nullable|array|min:1',
            'sessions.*.start_time' => 'required_with:sessions|string',
            'sessions.*.end_time' => 'required_with:sessions|string',
        ]);

        $groupCode = Str::uuid()->toString();

        // ✅ Si no mandan sessions, crea 1 como hoy (compatibilidad)
        $sessions = $validated['sessions'] ?? [
            [
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
            ]
        ];

        $created = [];

        foreach ($sessions as $s) {
            $class = new ClassSession();
            $class->title = $validated['title'];
            $class->trainer_name = $validated['trainer_name'];
            $class->date_iso = $validated['start_date'];
            $class->end_date_iso = $validated['end_date'];
            $class->time_range = $s['start_time'] . ' - ' . $s['end_time'];
            $class->modality = $validated['modality'];
            $class->level = 'General';
            $class->spots_left = $validated['spots_left'];
            $class->description = $validated['description'] ?? null;

            // 🔥 agrupar
            $class->group_code = $groupCode;

            $class->save();
            $created[] = $class;
        }

        return response()->json([
            'group_code' => $groupCode,
            'sessions_created' => count($created),
            'classes' => $created,
        ], 201);
    }

    public function indexPublicGrouped()
    {
        $rows = ClassSession::orderBy('date_iso')
            ->orderBy('time_range')
            ->get();

        // Agrupa por group_code (si es null, usa "single-{id}" para que igual funcione)
        $grouped = $rows->groupBy(function ($c) {
            return $c->group_code ?: ('single-' . $c->id);
        });

        $classes = $grouped->map(function ($items, $groupCode) {

            $first = $items->first();

            $sessions = $items->map(function ($s) {
                return [
                    'id' => $s->id,
                    'date_iso' => $s->date_iso,
                    'end_date_iso' => $s->end_date_iso,
                    'time_range' => $s->time_range,
                    'spots_left' => (int) $s->spots_left,
                ];
            })->values();

            return [
                'group_code' => $groupCode,
                'title' => $first->title,
                'trainer_name' => $first->trainer_name,
                'modality' => $first->modality,
                'level' => $first->level,
                'description' => $first->description,
                'sessions_count' => $sessions->count(),
                'sessions' => $sessions,
            ];
        })->values();

        return response()->json([
            'classes' => $classes,
        ]);
    }

    /**
     * ACTUALIZAR CLASE (ADMIN – PUT /api/admin/classes/{id})
     */
    public function update(Request $request, $id)
    {
        $class = ClassSession::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string',
            'trainer_name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required',
            'end_time' => 'required',
            'modality' => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0',
            'description' => 'nullable|string', // 👈 AGREGAR
        ]);

        $class->title = $validated['title'];
        $class->trainer_name = $validated['trainer_name'];
        $class->date_iso = $validated['start_date'];
        $class->end_date_iso = $validated['end_date'];
        $class->time_range = $validated['start_time'] . ' - ' . $validated['end_time'];
        $class->modality = $validated['modality'];
        $class->level = 'General';
        $class->spots_left = $validated['spots_left'];
        $class->description = $validated['description'] ?? null;
        $class->save();

        $class->refresh();

        [$startTime, $endTime] = array_map('trim', explode('-', $class->time_range));

        return response()->json([
            'class' => [
                'id' => $class->id,
                'title' => $class->title,
                'trainer_id' => $class->trainer_id,
                'trainer_name' => $class->trainer_name,
                'start_date' => $class->date_iso,
                'end_date' => $class->end_date_iso ?? $class->date_iso,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'date_iso' => $class->date_iso,
                'time_range' => $class->time_range,
                'modality' => $class->modality,
                'level' => $class->level,
                'spots_left' => $class->spots_left,
                'description' => $class->description, // 👈 devolverla también
            ],
        ]);
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

    public function addSessions(Request $request, $id)
    {
        $base = ClassSession::findOrFail($id);

        $validated = $request->validate([
            'sessions' => 'required|array|min:1',
            'sessions.*.start_time' => 'required|string',
            'sessions.*.end_time' => 'required|string',
        ]);

        // Asegurar que la clase base tenga group_code
        if (!$base->group_code) {
            $base->group_code = (string) Str::uuid();
            $base->save();
        }

        $created = [];

        foreach ($validated['sessions'] as $s) {
            $new = new ClassSession();
            $new->title = $base->title;
            $new->trainer_name = $base->trainer_name;
            $new->date_iso = $base->date_iso;              // misma fecha (si quieres, luego lo hacemos editable)
            $new->end_date_iso = $base->end_date_iso;
            $new->time_range = $s['start_time'] . ' - ' . $s['end_time'];
            $new->modality = $base->modality;
            $new->level = $base->level;
            $new->spots_left = $base->spots_left;
            $new->description = $base->description;
            $new->group_code = $base->group_code;
            $new->save();

            $created[] = $new;
        }

        return response()->json([
            'ok' => true,
            'group_code' => $base->group_code,
            'created' => $created,
        ], 201);
    }
}
