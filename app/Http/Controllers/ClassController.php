<?php

namespace App\Http\Controllers;

use App\Models\AvailableClass;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    // LISTAR CLASES
    public function index()
    {
        $classes = AvailableClass::orderBy('start_date')->get();

        // Devuelve tal cual, el front ya sabe leer trainer_name
        return response()->json([
            'classes' => $classes,
        ]);
    }

    // CREAR CLASE
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'        => 'required|string',
            'trainer_name' => 'nullable|string|max:255',   // 👈 nombre, no id
            'start_date'   => 'required|date',
            'end_date'     => 'required|date',
            'start_time'   => 'required',
            'end_time'     => 'required',
            'modality'     => 'required|in:Online,Presencial',
            'spots_left'   => 'required|integer|min:0',
        ]);

        $cls = AvailableClass::create($validated);

        return response()->json(['class' => $cls], 201);
    }

    // ACTUALIZAR CLASE
    public function update(Request $request, $id)
    {
        $cls = AvailableClass::findOrFail($id);

        $validated = $request->validate([
            'title'        => 'required|string',
            'trainer_name' => 'nullable|string|max:255',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date',
            'start_time'   => 'required',
            'end_time'     => 'required',
            'modality'     => 'required|in:Online,Presencial',
            'spots_left'   => 'required|integer|min:0',
        ]);

        $cls->update($validated);

        return response()->json(['class' => $cls]);
    }

    // ELIMINAR CLASE
    public function destroy($id)
    {
        $cls = AvailableClass::findOrFail($id);
        $cls->delete();

        return response()->json(['deleted' => true]);
    }
}
