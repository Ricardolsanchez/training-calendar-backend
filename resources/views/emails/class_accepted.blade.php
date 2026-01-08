<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Your Class Request Has been Accepted</title>
</head>
<body>
  <p>Hola {{ $booking->name }},</p>

  <p>
    Your class <strong>{{ $booking->name }}</strong> has been
    <strong>accepted</strong> 🎉
  </p>

  <p><strong>Sesiones:</strong></p>

  @if (empty($sessions))
    <p>No hay sesiones asociadas a esta reserva.</p>
  @else
    <ul>
      @foreach ($sessions as $item)
        <li style="margin-bottom:12px;">
          <div>
            <strong>📅 Date:</strong> {{ $item['date_iso'] ?? '—' }}<br>
            <strong>🕒 Time:</strong> {{ $item['time_range'] ?? '—' }}<br>
            <strong>👩‍🏫 Trainer:</strong> {{ $item['trainer_name'] ?? '—' }}<br>
          </div>

          @if (!empty($item['calendar_url']))
            <div style="margin-top:6px;">
              <a href="{{ $item['calendar_url'] }}" target="_blank">➕ Add to Google Calendar</a>
            </div>
          @endif
        </li>
      @endforeach
    </ul>
  @endif

  <p>Saludos,<br>Alonso & Alonso Academy</p>
</body>
</html>