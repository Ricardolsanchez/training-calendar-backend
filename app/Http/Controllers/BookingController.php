<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Mail;
use App\Mail\TrainingMissedMail;
use App\Services\GoogleScriptMailer;

class BookingController extends Controller
{
    // ==================== CREAR RESERVA (PÚBLICO) ====================

    public function store(Request $request)
    {
        Log::info('GOOGLE URL ES:', [
            'url' => config('services.google_script_mailer.url'),
        ]);

        Log::info('GOOGLE SECRET ES:', [
            'secret_present' => config('services.google_script_mailer.secret') ? true : false,
        ]);

        $validated = $request->validate([
            'class_id' => 'nullable|integer',
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

        $validated['status'] = 'pending';

        if (!empty($validated['class_id'])) {
            $class = ClassSession::find($validated['class_id']);
        } else {
            $class = ClassSession::where('title', $validated['name'])
                ->where('date_iso', $validated['start_date'])
                ->first();
        }

        if (!$class) {
            return response()->json([
                'ok' => false,
                'message' => 'Class is not available anymore.',
            ], 422);
        }

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

        if ($class->spots_left <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'We’re sorry! We’ve run out of available seats for this class.',
            ], 422);
        }

        $bookingData = $validated;
        $bookingData['class_id'] = $validated['class_id'] ?? ($class->id ?? null);

        $booking = Booking::create($bookingData);

        $class->spots_left = $class->spots_left - 1;
        $class->save();

        try {
            Log::info('Intentando enviar correo de reserva via GoogleScriptMailer', [
                'booking_id' => $booking->id,
                'email' => $booking->email,
            ]);

            $html = View::make('emails.class_booked', [
                'booking' => $booking,
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
                'error' => $e->getMessage(),
            ]);
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
        $user = $request->user();

        if (!$user || !$user->is_admin) {
            return response()->json([
                'ok' => false,
                'message' => 'No autorizado',
            ], 403);
        }

        $bookings = Booking::orderBy('created_at', 'desc')->get();

        $bookings = $bookings->map(function (Booking $b) {
            if (!empty($b->class_id)) {
                $class = ClassSession::find($b->class_id);
            } else {
                $class = ClassSession::where('title', $b->name)
                    ->where('date_iso', $b->start_date)
                    ->first();
            }

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

        if (!empty($booking->class_id)) {
            $class = ClassSession::find($booking->class_id);
        } else {
            $class = ClassSession::where('title', $booking->name)
                ->where('date_iso', $booking->start_date)
                ->first();
        }

        if (!empty($class)) {
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
        if (!$trainerName)
            return null;

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
        try {
            $booking = Booking::findOrFail($id);

            $validated = $request->validate([
                'status' => 'required|in:accepted,denied',
                'calendar_url' => 'nullable|string|max:2048',
            ]);

            $booking->status = $validated['status'];
            $booking->save();

            if (!empty($booking->class_id)) {
                $class = ClassSession::find($booking->class_id);
            } else {
                $class = ClassSession::where('title', $booking->name)
                    ->where('date_iso', $booking->start_date)
                    ->first();
            }

            $calendarUrl = $validated['calendar_url'] ?? null;

            if ($class && $calendarUrl && Schema::hasColumn('class_sessions', 'calendar_url')) {
                $class->calendar_url = $calendarUrl;
                $class->save();
            } elseif ($class && $calendarUrl && !Schema::hasColumn('class_sessions', 'calendar_url')) {
                Log::warning('class_sessions no tiene columna calendar_url en producción', [
                    'class_id' => $class->id,
                ]);
            }

            if ($booking->status === 'accepted' && $class) {

                // participante
                try {
                    $htmlUser = View::make('emails.class_accepted', [
                        'booking' => $booking,
                        'class' => $class,
                        'calendarUrl' => $calendarUrl ?: $class->calendar_url,
                    ])->render();

                    GoogleScriptMailer::send(
                        $booking->email,
                        $booking->name,
                        '✅ Your class has been confirmed',
                        $htmlUser,
                        'Your class has been confirmed.'
                    );
                } catch (\Throwable $e) {
                    Log::error('Error enviando ClassAcceptedMail via GoogleScriptMailer', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // trainer
                try {
                    $trainerEmail = $this->getTrainerEmail($class->trainer_name);

                    if ($trainerEmail) {
                        $htmlTrainer = View::make('emails.trainer_class_accepted', [
                            'booking' => $booking,
                            'class' => $class,
                        ])->render();

                        GoogleScriptMailer::send(
                            $trainerEmail,
                            $class->trainer_name ?? 'Trainer',
                            'New training session assigned',
                            $htmlTrainer,
                            'New training session assigned.'
                        );
                    }
                } catch (\Throwable $e) {
                    Log::error('Error enviando TrainerClassAcceptedMail via GoogleScriptMailer', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (isset($class) && $class && Schema::hasColumn('class_sessions', 'calendar_url')) {
                $booking->setAttribute('calendar_url', $class->calendar_url);
            } else {
                $booking->setAttribute('calendar_url', $calendarUrl);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Booking Updated',
                'booking' => $booking,
            ]);

        } catch (\Throwable $e) {
            Log::error('Error en updateStatus', [
                'booking_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Server error updating booking status.',
            ], 500);
        }
    }

    // ==================== ADMIN: ASISTENCIA (TRUE / FALSE / NULL) ====================

    public function updateAttendance(Request $request, int $id)
    {
        try {
            $booking = Booking::findOrFail($id);

            // Acepta cualquiera de los dos para no romper tu front actual
            $validated = $request->validate([
                'attended' => 'nullable',
                'attendedbutton' => 'nullable',
            ]);

            // Normaliza a boolean/null aunque llegue string
            $raw = array_key_exists('attended', $validated)
                ? $validated['attended']
                : ($validated['attendedbutton'] ?? null);

            $attended = is_null($raw)
                ? null
                : filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            // Guarda en ambas columnas (así tu tabla queda consistente)
            $booking->attended = $attended;
            $booking->attendedbutton = $attended;
            $booking->save();

            // Enviar correo si attended es false o null
            if ($attended !== true) {
                try {
                    // Si NO tienes vista aún, usa html básico
                    $html = "<p>Hello {$booking->name},</p><p>We missed you in training.</p>";

                    $sent = GoogleScriptMailer::send(
                        $booking->email,
                        $booking->name,
                        'We missed you in training',
                        $html,
                        'We missed you in training'
                    );

                    if (!$sent) {
                        Log::warning('GoogleScriptMailer::send devolvió false en updateAttendance()', [
                            'booking_id' => $booking->id,
                        ]);
                    }
                } catch (\Throwable $e) {
                    // NO tumbes el endpoint por el correo
                    Log::error('Error enviando mail via GoogleScriptMailer (updateAttendance)', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'ok' => true,
                'message' => 'Attendance updated',
                'booking' => $booking,
            ]);

        } catch (\Throwable $e) {
            Log::error('Error updating attendance', [
                'booking_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Server error updating attendance.',
            ], 500);
        }
    }
}
