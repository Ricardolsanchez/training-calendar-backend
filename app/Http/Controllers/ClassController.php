<?php

namespace App\Http\Controllers;

use App\Models\AvailableClass;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    // =============================
    // LISTAR TODAS LAS CLASES
    // =============================
    public function index()
    {
        $classes = AvailableClass::all();

        return [
            'classes' => $classes->map(function ($cls) {
                return [
                    'id'          => $cls->id,
                    'title'       => $cls->title,
                    'trainer_id'  => $cls->trainer_id,
                    'trainer_name'=> null, // por ahora lo manejas en frontend
                    'start_date'  => $cls->start_date,
                    'end_date'    => $cls->end_date,
                    'start_time'  => $cls->start_time,
                    'end_time'    => $cls->end_time,
                    'modality'    => $cls->modality,
                    'spots_left'  => $cls->spots_left,
                ];
            }),
        ];
    }

    // =============================
    // CREAR NUEVA CLASE
    // =============================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'      => 'required|string',
            // 🔴 AQUÍ EL CAMBIO: ya no validamos contra la tabla trainers
            'trainer_id' => 'nullable|integer',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
            'start_time' => 'required',
            'end_time'   => 'required',
            'modality'   => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0',
        ]);

        $cls = AvailableClass::create($validated);

        return response()->json(['class' => $cls], 201);
    }

    // =============================
    // ACTUALIZAR CLASE
    // =============================
    public function update(Request $request, $id)
    {
        $cls = AvailableClass::findOrFail($id);

        $validated = $request->validate([
            'title'      => 'required|string',
            // 🔴 MISMO CAMBIO AQUÍ
            'trainer_id' => 'nullable|integer',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
            'start_time' => 'required',
            'end_time'   => 'required',
            'modality'   => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0',
        ]);

        $cls->update($validated);

        return response()->json(['class' => $cls]);
    }

    // =============================
    // ELIMINAR CLASE
    // =============================
    public function destroy($id)
    {
        $cls = AvailableClass::findOrFail($id);
        $cls->delete();

        return response()->json(['deleted' => true]);
    }
}