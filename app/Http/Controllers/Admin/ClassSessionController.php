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
                $endDate   = $cls->end_date_iso ?? $cls->date_iso;

                $startTime = null;
                $endTime   = null;

                if ($cls->time_range && str_contains($cls->time_range, '-')) {
                    [$startTime, $endTime] = array_map('trim', explode('-', $cls->time_range));
                }

                return [
                    'id'           => $cls->id,
                    'title'        => $cls->title,
                    'trainer_id'   => $cls->trainer_id,
                    'trainer_name' => $cls->trainer_name,

                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'start_time' => $startTime,
                    'end_time'   => $endTime,

                    'date_iso'   => $cls->date_iso,
                    'time_range' => $cls->time_range,

                    'modality'   => $cls->modality,
                    'level'      => $cls->level,
                    'spots_left' => $cls->spots_left,
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
                $endDate   = $cls->end_date_iso ?? $cls->date_iso;

                $startTime = null;
                $endTime   = null;

                if ($cls->time_range && str_contains($cls->time_range, '-')) {
                    [$startTime, $endTime] = array_map('trim', explode('-', $cls->time_range));
                }

                return [
                    'id'           => $cls->id,
                    'title'        => $cls->title,
                    'trainer_name' => $cls->trainer_name,

                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'start_time' => $startTime,
                    'end_time'   => $endTime,

                    'date_iso'   => $cls->date_iso,
                    'time_range' => $cls->time_range,

                    'modality'   => $cls->modality,
                    'level'      => $cls->level,
                    'spots_left' => $cls->spots_left,
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
            'title'        => 'required|string',
            'trainer_name' => 'required|string',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'start_time'   => 'required',
            'end_time'     => 'required',
            'modality'     => 'required|in:Online,Presencial',
            'spots_left'   => 'required|integer|min:0',
        ]);

        $class = new ClassSession();
        $class->title        = $validated['title'];
        $class->trainer_name = $validated['trainer_name'];
        $class->date_iso     = $validated['start_date'];
        $class->end_date_iso = $validated['end_date']; // 👈 AHORA GUARDAMOS EL FIN
        $class->time_range   = $validated['start_time'] . ' - ' . $validated['end_time'];
        $class->modality     = $validated['modality'];
        $class->level        = 'General';
        $class->spots_left   = $validated['spots_left'];
        $class->save();

        // refrescar por si hay casts/defaults
        $class->refresh();

        [$startTime, $endTime] = array_map('trim', explode('-', $class->time_range));

        return response()->json([
            'class' => [
                'id'           => $class->id,
                'title'        => $class->title,
                'trainer_id'   => $class->trainer_id,
                'trainer_name' => $class->trainer_name,
                'start_date'   => $class->date_iso,
                'end_date'     => $class->end_date_iso ?? $class->date_iso,
                'start_time'   => $startTime,
                'end_time'     => $endTime,
                'date_iso'     => $class->date_iso,
                'time_range'   => $class->time_range,
                'modality'     => $class->modality,
                'level'        => $class->level,
                'spots_left'   => $class->spots_left,
            ],
        ], 201);
    }

    /**
     * ACTUALIZAR CLASE (ADMIN – PUT /api/admin/classes/{id})
     */
    public function update(Request $request, $id)
    {
        $class = ClassSession::findOrFail($id);

        $validated = $request->validate([
            'title'        => 'required|string',
            'trainer_name' => 'required|string',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'start_time'   => 'required',
            'end_time'     => 'required',
            'modality'     => 'required|in:Online,Presencial',
            'spots_left'   => 'required|integer|min:0',
        ]);

        $class->title        = $validated['title'];
        $class->trainer_name = $validated['trainer_name'];
        $class->date_iso     = $validated['start_date'];
        $class->end_date_iso = $validated['end_date']; // 👈 TAMBIÉN AQUÍ
        $class->time_range   = $validated['start_time'] . ' - ' . $validated['end_time'];
        $class->modality     = $validated['modality'];
        $class->level        = 'General';
        $class->spots_left   = $validated['spots_left'];
        $class->save();

        $class->refresh();

        [$startTime, $endTime] = array_map('trim', explode('-', $class->time_range));

        return response()->json([
            'class' => [
                'id'           => $class->id,
                'title'        => $class->title,
                'trainer_id'   => $class->trainer_id,
                'trainer_name' => $class->trainer_name,
                'start_date'   => $class->date_iso,
                'end_date'     => $class->end_date_iso ?? $class->date_iso,
                'start_time'   => $startTime,
                'end_time'     => $endTime,
                'date_iso'     => $class->date_iso,
                'time_range'   => $class->time_range,
                'modality'     => $class->modality,
                'level'        => $class->level,
                'spots_left'   => $class->spots_left,
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
}
