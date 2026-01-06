<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingSession;
use Illuminate\Http\Request;

class BookingSessionController extends Controller
{
    public function update(Request $request, $id)
{
  $validated = $request->validate([
    'attended' => 'nullable|boolean',
  ]);

  $bs = BookingSession::findOrFail($id);
  $bs->attended = $validated['attended'];
  $bs->save();

  $booking = $bs->booking;

  $anyMissed = $booking->bookingSessions()->where('attended', false)->exists();

  if ($anyMissed) {
    $booking->completion_status = 'failed';
  } else {
    $hasNull = $booking->bookingSessions()->whereNull('attended')->exists();
    $allTrue = !$booking->bookingSessions()->where('attended', '!=', true)->exists();
    $booking->completion_status = (!$hasNull && $allTrue) ? 'completed' : 'in_progress';
  }

  $booking->save();

  return response()->json(['ok' => true, 'booking' => $booking, 'booking_session' => $bs]);
}
}
