<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

// 👇 NUEVO: usamos el mailer por Google Apps Script
use App\Services\GoogleScriptMailer;

class BookingController extends Controller
{
    // ==================== CREAR RESERVA (PÚBLICO) ====================

    public function store(Request $request)
    {
        $validated = $request->validate([
            // class_id es OPCIONAL: si llega lo usamos, si no, usamos name + start_date
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
            // a) si viene class_id, buscamos por id
            $class = ClassSession::find($validated['class_id']);
        } else {
            // b) fallback: por título + fecha (como antes)
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
            // no rompemos el endpoint
        }

        return response()->json([
            'ok'      => true,
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
            'ok'       => true,
            'message'  => 'Listado de reservas',
            'bookings' => $bookings,
        ]);
    }

    // ==================== ADMIN: ELIMINAR RESERVA ====================

    public function destroy(string $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json([
                'ok'      => false,
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
            'ok'      => true,
            'message' => 'Reserva eliminada correctamente',
        ]);
    }

    // ==================== ADMIN: ACTUALIZAR RESERVA ====================

    public function update(Request $request, string $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json([
                'ok'      => false,
                'message' => 'Reserva no encontrada',
            ], 404);
        }

        $validated = $request->validate([
            'name'         => 'sometimes|required|string|max:255',
            'email'        => 'sometimes|required|email',
            'notes'        => 'nullable|string',
            'start_date'   => 'sometimes|required|date',
            'end_date'     => 'sometimes|required|date|after_or_equal:start_date',
            'trainer_name' => 'nullable|string|max:255',
        ]);

        $booking->update($validated);

        return response()->json([
            'ok'      => true,
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
            'Sergio Osorio'    => 'seosorio@alonsoalonsolaw.com',
            'Monica Mendoza'   => 'mmendoza@alonsoalonsolaw.com',
            'Kelvin Hodgson'   => 'kelvinh@alonsoalonsolaw.com',
            'Edma Murillo'     => 'emurillo@alonsoalonsolaw.com',
            'Dora Ramirez'     => 'dramirez@alonsoalonsolaw.com',
            'Ada Perez'        => 'adaperez@alonsoalonsolaw.com',
            'Josias Mendez'    => 'josias@alonsoalonsolaw.com',
            'Ricardo Sanchez'  => 'risanchez@alonsoalonsolaw.com',
            'Giselle Cárdenas' => 'giscardenas@alonsoalonsolaw.com',
        ];

        return $map[$trainerName] ?? null;
    }

    // ==================== ADMIN: CAMBIAR ESTADO (ACCEPT / DENY) ====================

    public function updateStatus(Request $request, string $id)
    {
        try {
            $booking = Booking::findOrFail($id);

            $validated = $request->validate([
                'status'       => 'required|in:accepted,denied',
                'calendar_url' => 'nullable|string|max:2048',
            ]);

            // 1) Actualizar estado de la reserva
            $booking->status = $validated['status'];
            $booking->save();

            // 2) Clase asociada
            $class = ClassSession::where('title', $booking->name)
                ->where('date_iso', $booking->start_date)
                ->first();

            $calendarUrl = $validated['calendar_url'] ?? null;

            // 3) Guardar calendar_url SOLO si la columna existe en la tabla
            if ($class && $calendarUrl && Schema::hasColumn('class_sessions', 'calendar_url')) {
                $class->calendar_url = $calendarUrl;
                $class->save();
            } elseif ($class && $calendarUrl && !Schema::hasColumn('class_sessions', 'calendar_url')) {
                Log::warning('class_sessions no tiene columna calendar_url en producción', [
                    'class_id' => $class->id,
                ]);
            }

            // 4) Enviar correos si se aceptó
            if ($booking->status === 'accepted' && $class) {

                // 4.1 Correo al participante
                try {
                    Log::info('Intentando enviar correo de aceptación al participante via GoogleScriptMailer', [
                        'booking_id' => $booking->id,
                        'email'      => $booking->email,
                    ]);

                    $htmlUser = View::make('emails.class_accepted', [
                        'booking'     => $booking,
                        'class'       => $class,
                        'calendarUrl' => $calendarUrl ?: $class->calendar_url,
                    ])->render();

                    $sentUser = GoogleScriptMailer::send(
                        $booking->email,
                        $booking->name,
                        '✅ Your class has been confirmed',
                        $htmlUser,
                        'Your class has been confirmed.'
                    );

                    if (!$sentUser) {
                        Log::warning('GoogleScriptMailer::send devolvió false para participante en updateStatus()', [
                            'booking_id' => $booking->id,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Error enviando ClassAcceptedMail via GoogleScriptMailer', [
                        'booking_id' => $booking->id,
                        'error'      => $e->getMessage(),
                    ]);
                }

                // 4.2 Correo al trainer
                try {
                    $trainerEmail = $this->getTrainerEmail($class->trainer_name);

                    if ($trainerEmail) {
                        Log::info('Intentando enviar correo al trainer via GoogleScriptMailer', [
                            'booking_id' => $booking->id,
                            'trainer'    => $class->trainer_name,
                            'email'      => $trainerEmail,
                        ]);

                        $htmlTrainer = View::make('emails.trainer_class_accepted', [
                            'booking' => $booking,
                            'class'   => $class,
                        ])->render();

                        $sentTrainer = GoogleScriptMailer::send(
                            $trainerEmail,
                            $class->trainer_name ?? 'Trainer',
                            'New training session assigned',
                            $htmlTrainer,
                            'New training session assigned.'
                        );

                        if (!$sentTrainer) {
                            Log::warning('GoogleScriptMailer::send devolvió false para trainer en updateStatus()', [
                                'booking_id' => $booking->id,
                                'trainer'    => $class->trainer_name,
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Error enviando TrainerClassAcceptedMail via GoogleScriptMailer', [
                        'booking_id' => $booking->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            // 5) Incluir calendar_url en la respuesta para que el front actualice la tabla
            if ($class && Schema::hasColumn('class_sessions', 'calendar_url')) {
                $booking->setAttribute('calendar_url', $class->calendar_url);
            } else {
                $booking->setAttribute('calendar_url', $calendarUrl);
            }

            return response()->json([
                'ok'      => true,
                'message' => 'Estado de la reserva actualizado',
                'booking' => $booking,
            ]);

        } catch (\Throwable $e) {
            Log::error('Error en updateStatus', [
                'booking_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'Server error updating booking status.',
            ], 500);
        }
    }
}
