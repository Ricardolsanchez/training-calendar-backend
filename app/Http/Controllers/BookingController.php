<?php

namespace App\Http\Controllers;

use App\Mail\ClassBookedMail;
use App\Mail\ClassAcceptedMail;
use App\Mail\TrainerClassAcceptedMail;
use App\Models\Booking;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    // ==================== CREAR RESERVA (PÚBLICO) ====================

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'notes' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',

            'trainer_name' => 'nullable|string|max:255',
            'original_start_date' => 'nullable|date',
            'original_end_date' => 'nullable|date',
            'original_training_days' => 'nullable|integer|min:0',
            'new_training_days' => 'nullable|integer|min:0',
        ]);

        // nuevo campo
        $validated['status'] = 'pending';

        // 1) Buscar la clase asociada (por título + fecha)
        $class = ClassSession::where('title', $validated['name'])
            ->where('date_iso', $validated['start_date'])
            ->first();

        if (!$class) {
            return response()->json([
                'ok' => false,
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
                'ok' => false,
                'message' => 'You already have a reservation for this class.',
            ], 422);
        }

        // 3) Validar cupos
        if ($class->spots_left <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'We’re sorry! We’ve run out of available seats for this class.',
            ], 422);
        }

        // 4) Crear la reserva
        $booking = Booking::create($validated);

        // 5) Descontar un cupo
        $class->spots_left = $class->spots_left - 1;
        $class->save();

        // 6) Enviar correo de confirmación (ENVUELTO EN try/catch)
        try {
            Mail::to($booking->email)->send(
                new ClassBookedMail($booking, $class)
            );
        } catch (\Throwable $e) {
            Log::error('Error enviando ClassBookedMail', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            // NO lanzamos excepción → no rompemos el endpoint
        }

        return response()->json([
            'ok' => true,
            'message' => 'Reserva creada correctamente',
            'booking' => $booking,
        ], 201);
    }

    // ==================== ADMIN: LISTAR RESERVAS ====================

    public function index(Request $request)
    {
        if (!$request->user()->is_admin) {
            abort(403, 'No autorizado');
        }

        $bookings = Booking::orderBy('created_at', 'desc')->get();

        // Adjuntar calendar_url de la clase a cada booking
        $bookings = $bookings->map(function (Booking $b) {
            $class = ClassSession::where('title', $b->name)
                ->where('date_iso', $b->start_date)
                ->first();

            $b->setAttribute('calendar_url', $class?->calendar_url);

            return $b;
        });

        return response()->json([
            'ok' => true,
            'message' => 'Listado de reservas',
            'bookings' => $bookings,
        ]);
    }

    // ==================== ADMIN: ELIMINAR RESERVA ====================

    public function destroy(string $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json([
                'ok' => false,
                'message' => 'Reserva no encontrada',
            ], 404);
        }

        // Liberar cupo en la clase (si existe)
        $class = ClassSession::where('title', $booking->name)
            ->where('date_iso', $booking->start_date)
            ->first();

        if ($class) {
            $class->spots_left = $class->spots_left + 1;
            $class->save();
        }

        $booking->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Reserva eliminada correctamente',
        ]);
    }

    // ==================== ADMIN: ACTUALIZAR RESERVA ====================

    public function update(Request $request, string $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json([
                'ok' => false,
                'message' => 'Reserva no encontrada',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email',
            'notes' => 'nullable|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'trainer_name' => 'nullable|string|max:255',
        ]);

        $booking->update($validated);

        return response()->json([
            'ok' => true,
            'message' => 'Reserva actualizada correctamente',
            'booking' => $booking,
        ]);
    }

    // ==================== HELPERS ====================

    private function getTrainerEmail(?string $trainerName): ?string
    {
        if (!$trainerName) {
            return null;
        }

        $map = [
            'Sergio Osorio' => 'seosorio@alonsoalonsolaw.com',
            'Monica Mendoza' => 'mmendoza@alonsoalonsolaw.com',
            'Kelvin Hodgson' => 'kelvinh@alonsoalonsolaw.com',
            'Edma Murillo' => 'emurillo@alonsoalonsolaw.com',
            'Dora Ramirez' => 'dramirez@alonsoalonsolaw.com',
            'Ada Perez' => 'adaperez@alonsoalonsolaw.com',
            'Josias Mendez' => 'josias@alonsoalonsolaw.com',
            'Ricardo Sanchez' => 'risanchez@alonsoalonsolaw.com',
            'Giselle Cárdenas' => 'giscardenas@alonsoalonsolaw.com',
        ];

        return $map[$trainerName] ?? null;
    }

    // ==================== ADMIN: CAMBIAR ESTADO (ACCEPT / DENY) ====================

    public function updateStatus(Request $request, string $id)
    {
        $booking = Booking::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:accepted,denied',
            'calendar_url' => 'nullable|string|max:2048',
        ]);

        // 1) Actualizar estado
        $booking->status = $validated['status'];
        $booking->save();

        // 2) Traer clase asociada
        $class = ClassSession::where('title', $booking->name)
            ->where('date_iso', $booking->start_date)
            ->first();

        $calendarUrl = $validated['calendar_url'] ?? null;

        if ($class && $calendarUrl) {
            $class->calendar_url = $calendarUrl;
            $class->save();
        }

        // 3) Enviar correos si se aceptó
        if ($booking->status === 'accepted' && $class) {

            try {
                Mail::to($booking->email)->send(
                    new ClassAcceptedMail($booking, $class, $calendarUrl)
                );
            } catch (\Throwable $e) {
                Log::error('Error enviando ClassAcceptedMail', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $trainerEmail = $this->getTrainerEmail($class->trainer_name);

                if ($trainerEmail) {
                    Mail::to($trainerEmail)->send(
                        new TrainerClassAcceptedMail($booking, $class)
                    );
                }
            } catch (\Throwable $e) {
                Log::error('Error enviando TrainerClassAcceptedMail', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 4) Incluir calendar_url en la respuesta
        if ($class) {
            $booking->setAttribute('calendar_url', $class->calendar_url);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Estado de la reserva actualizado',
            'booking' => $booking,
        ]);
    }

}
