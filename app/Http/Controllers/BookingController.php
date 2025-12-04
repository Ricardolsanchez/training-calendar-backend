<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use App\Services\GoogleScriptMailer;

class BookingController extends Controller
{
    // ==================== CREAR RESERVA (PÚBLICO) ====================

    public function store(Request $request)
    {
        // 👇 Logs para verificar que sí está leyendo las variables del .env
        Log::info('GOOGLE URL ES:', [
            'url' => config('services.google_script_mailer.url'),
        ]);

        Log::info('GOOGLE SECRET ES:', [
            'secret' => config('services.google_script_mailer.secret'),
        ]);

        $validated = $request->validate([
            'class_id' => 'nullable|integer',

            'name'   => 'required|string|max:255',
            'email'  => 'required|email',
            'notes'  => 'nullable|string',

            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',

            'trainer_name'           => 'nullable|string|max:255',
            'original_start_date'    => 'nullable|date',
            'original_end_date'      => 'nullable|date',
            'original_training_days' => 'nullable|integer|min:0',
            'new_training_days'      => 'nullable|integer|min:0',
        ]);

        // siempre creamos en pending
        $validated['status'] = 'pending';

        // 1) Buscar la clase asociada
        if (!empty($validated['class_id'])) {
            $class = ClassSession::find($validated['class_id']);
        } else {
            $class = ClassSession::where('title', $validated['name'])
                ->where('date_iso', $validated['start_date'])
                ->first();
        }

        if (!$class) {
            return response()->json([
                'ok'      => false,
                'message' => 'Class is not available anymore.',
            ], 422);
        }

        // 2) Verificar que este correo NO tenga ya una reserva para esta clase
        $alreadyBooked = Booking::where('email', $validated['email'])
            ->where('name', $validated['name'])
            ->where('start_date', $validated['start_date'])
            ->exists();

        if ($alreadyBooked) {
            return response()->json([
                'ok'      => false,
                'message' => 'You already have a reservation for this class.',
            ], 422);
        }

        // 3) Validar cupos
        if ($class->spots_left <= 0) {
            return response()->json([
                'ok'      => false,
                'message' => 'We’re sorry! We’ve run out of available seats for this class.',
            ], 422);
        }

        // 4) Crear la reserva
        $booking = Booking::create($validated);

        // 5) Descontar un cupo
        $class->spots_left = $class->spots_left - 1;
        $class->save();

        // 6) Enviar correo de confirmación usando GoogleScriptMailer (HTTP API)
        try {
            Log::info('Intentando enviar correo de reserva via GoogleScriptMailer', [
                'booking_id' => $booking->id,
                'email'      => $booking->email,
            ]);

            $html = View::make('emails.class_booked', [
                'booking'      => $booking,
                'classSession' => $class,
            ])->render();

            $sent = GoogleScriptMailer::send(
                $booking->email,
                $booking->name,
                'Your class reservation has been received! ✅',
                $html,
                'Your class reservation has been received!'
            );

            if (!$sent) {
                Log::warning('GoogleScriptMailer::send devolvió false en store()', [
                    'booking_id' => $booking->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Error enviando ClassBookedMail via GoogleScriptMailer', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Reserva creada correctamente',
            'booking' => $booking,
        ], 201);
    }

    // ... (resto del controller igual que lo tenías)
}
