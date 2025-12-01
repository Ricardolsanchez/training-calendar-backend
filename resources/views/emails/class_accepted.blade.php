<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your Class Request Has been Accepted</title>
</head>
<body>
    <p>Hola {{ $booking->name }},</p>

    <p>
        You'r class <strong>{{ $class->title }}</strong> has been
        <strong>accepted</strong> 🎉
    </p>

    <p>
        <strong>📅 Date:</strong> {{ $class->date_iso }}<br>
        <strong>🕒 Time:</strong> {{ $class->time_range }}<br>
        <strong>👩‍🏫 Trainer:</strong> {{ $class->trainer_name }}<br>
        <strong>💻 Session:</strong> {{ $class->modality }}
    </p>

    <p>
        Puedes agregarla a tu Google Calendar aquí:<br>
        <a href="{{ $calendarUrl }}" target="_blank">{{ $calendarUrl }}</a>
    </p>

    <p>Saludos,<br>Alonso & Alonso Academy</p>
</body>
</html>