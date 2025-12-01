<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Booking;
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

                // 1) Convertir date_iso → start_date y end_date
                $startDate = $cls->date_iso;
                $endDate = $cls->date_iso;

                // 2) Convertir time_range → start_time y end_time
                $startTime = null;
                $endTime = null;

                if ($cls->time_range && str_contains($cls->time_range, '-')) {
                    [$startTime, $endTime] =
                        array_map('trim', explode('-', $cls->time_range));
                }

                return [
                    'id' => $cls->id,
                    'title' => $cls->title,
                    'trainer_id' => $cls->trainer_id,
                    'trainer_name' => $cls->trainer_name,

                    // CAMPOS NUEVOS PARA REACT
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,

                    // CAMPOS ANTIGUOS (por compatibilidad)
                    'date_iso' => $cls->date_iso,
                    'time_range' => $cls->time_range,

                    'modality' => $cls->modality,
                    'level' => $cls->level,
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
                $endDate = $cls->date_iso;

                $startTime = null;
                $endTime = null;

                if ($cls->time_range && str_contains($cls->time_range, '-')) {
                    [$startTime, $endTime] =
                        array_map('trim', explode('-', $cls->time_range));
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
            'end_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'modality' => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0',
        ]);

        $data = [
            'title' => $validated['title'],
            'trainer_name' => $validated['trainer_name'],
            'date_iso' => $validated['start_date'], // usamos fecha de inicio
            'time_range' => $validated['start_time'] . ' - ' . $validated['end_time'],
            'modality' => $validated['modality'],
            'level' => 'General',
            'spots_left' => $validated['spots_left'],
        ];

        $class = ClassSession::create($data);

        // devolver en el mismo formato que index()
        [$startTime, $endTime] = array_map('trim', explode('-', $class->time_range));

        return response()->json([
            'class' => [
                'id' => $class->id,
                'title' => $class->title,
                'trainer_id' => $class->trainer_id,
                'trainer_name' => $class->trainer_name,
                'start_date' => $class->date_iso,
                'end_date' => $class->date_iso,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'date_iso' => $class->date_iso,
                'time_range' => $class->time_range,
                'modality' => $class->modality,
                'level' => $class->level,
                'spots_left' => $class->spots_left,
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
            'title' => 'required|string',
            'trainer_name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'modality' => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0',
        ]);

        $data = [
            'title' => $validated['title'],
            'trainer_name' => $validated['trainer_name'],
            'date_iso' => $validated['start_date'],
            'time_range' => $validated['start_time'] . ' - ' . $validated['end_time'],
            'modality' => $validated['modality'],
            'level' => 'General',
            'spots_left' => $validated['spots_left'],
        ];

        $class->update($data);

        [$startTime, $endTime] = array_map('trim', explode('-', $class->time_range));

        return response()->json([
            'class' => [
                'id' => $class->id,
                'title' => $class->title,
                'trainer_id' => $class->trainer_id,
                'trainer_name' => $class->trainer_name,
                'start_date' => $class->date_iso,
                'end_date' => $class->date_iso,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'date_iso' => $class->date_iso,
                'time_range' => $class->time_range,
                'modality' => $class->modality,
                'level' => $class->level,
                'spots_left' => $class->spots_left,
            ],
        ]);
    }

    /**
     * ELIMINAR CLASE (ADMIN – DELETE /api/admin/classes/{id})
     */
    public function destroy($id)
    {
        try {
            $class = ClassSession::findOrFail($id);

            // 2) Borrar la clase
            $class->delete();

            return response()->json(['deleted' => true]);
        } catch (\Throwable $e) {
            return response()->json([
                'deleted' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
