<?php

namespace App\Http\Controllers;

use App\Models\AvailableClass;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\ClassSession;

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
            'title' => 'required|string',
            'trainer_name' => 'nullable|string|max:255',   // 👈 nombre, no id
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'modality' => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0',
        ]);

        $cls = AvailableClass::create($validated);

        return response()->json(['class' => $cls], 201);
    }

    // ACTUALIZAR CLASE
    public function update(Request $request, $id)
    {
        $cls = AvailableClass::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string',
            'trainer_name' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'modality' => 'required|in:Online,Presencial',
            'spots_left' => 'required|integer|min:0',
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

    public function mySessions(Request $request)
    {
        $email = $request->query('email');
        if (!$email) {
            return response()->json(['ok' => false, 'message' => 'email is required'], 422);
        }

        $booking = Booking::where('email', $email)
            ->where('status', 'accepted')
            ->latest()
            ->first();

        if (!$booking || !$booking->class_id) {
            return response()->json(['ok' => true, 'booking_id' => null, 'sessions' => []]);
        }

        $firstSession = ClassSession::find($booking->class_id);
        if (!$firstSession) {
            return response()->json(['ok' => true, 'booking_id' => $booking->id, 'sessions' => []]);
        }

        $sessions = ClassSession::where('group_code', $firstSession->group_code)
            ->orderBy('date_iso')
            ->orderBy('time_range')
            ->get();

        // ✅ attendanceMap usando pivot (booking_sessions)
        $attendanceMap = $booking->sessions()
            ->pluck('booking_sessions.attended', 'class_sessions.id'); // [sessionId => attended]

        $out = [];
        $prevAttended = true; // para que la sesión 1 siempre esté unlocked

        foreach ($sessions as $idx => $s) {
            $attended = $attendanceMap[$s->id] ?? null;

            $unlocked = ($idx === 0) ? true : ($prevAttended === true);

            $out[] = [
                'id' => $s->id,
                'date_iso' => $s->date_iso,
                'time_range' => $s->time_range,
                'attended' => $attended,
                'unlocked' => $unlocked,
            ];

            $prevAttended = ($attended === true);
        }

        return response()->json([
            'ok' => true,
            'booking_id' => $booking->id,
            'sessions' => $out,
        ]);
    }
}
