<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str; // ✅ IMPORTANTE

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

                    'group_code' => $cls->group_code ?? null,
                ];
            }),
        ]);
    }

    /**
     * CREAR CLASE (ADMIN – POST /api/admin/classes)
     * Mantiene compatibilidad con tu front: requiere start_time y end_time.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'trainer_name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required',
            'end_time' => 'required',
            'modality' => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $class = new ClassSession();
        $class->title = $validated['title'];
        $class->trainer_name = $validated['trainer_name'];
        $class->date_iso = $validated['start_date'];
        $class->end_date_iso = $validated['end_date'];
        $class->time_range = $validated['start_time'] . ' - ' . $validated['end_time'];
        $class->modality = $validated['modality'];
        $class->level = 'General';
        $class->spots_left = $validated['spots_left'];
        $class->description = $validated['description'] ?? null;

        // ✅ inicializa group_code para poder agrupar y luego añadir sesiones
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
                'title' => $first->title,
                'trainer_name' => $first->trainer_name,
                'modality' => $first->modality,
                'level' => $first->level,
                'description' => $first->description,
                'sessions_count' => $sessions->count(),
                'sessions' => $sessions,
            ];
        })->values();

        return response()->json(['classes' => $classes]);
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
            'description' => 'nullable|string',
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

        // ✅ si por alguna razón no tenía group_code, se lo ponemos
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
     * ✅ AÑADIR SESIONES (POST /api/admin/classes/{id}/sessions)
     * Crea nuevas filas en class_sessions con el mismo group_code.
     */
    public function addSessions(Request $request, $id)
    {
        $base = ClassSession::findOrFail($id);

        $validated = $request->validate([
            'sessions' => 'required|array|min:1',
            'sessions.*.start_time' => 'required|string',
            'sessions.*.end_time' => 'required|string',
        ]);

        // Asegurar group_code
        if (!$base->group_code) {
            $base->group_code = (string) Str::uuid();
            $base->save();
        }

        $created = [];

        foreach ($validated['sessions'] as $s) {
            $new = ClassSession::create([
                'title' => $base->title,
                'trainer_name' => $base->trainer_name,
                'date_iso' => $base->date_iso,
                'end_date_iso' => $base->end_date_iso,
                'time_range' => $s['start_time'] . ' - ' . $s['end_time'],
                'modality' => $base->modality,
                'level' => $base->level ?? 'General',
                'spots_left' => $base->spots_left,
                'description' => $base->description,
                'group_code' => $base->group_code, // ✅ clave
            ]);

            $created[] = $new;
        }

        return response()->json([
            'ok' => true,
            'group_code' => $base->group_code,
            'created_count' => count($created),
            'created' => $created,
        ], 201);
    }
}
