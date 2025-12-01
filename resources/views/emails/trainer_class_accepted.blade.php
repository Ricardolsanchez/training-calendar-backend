<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Training Session Assigned</title>
</head>
<body>
    <p>Hola {{ $class->trainer_name }},</p>

    <p>
        You have a new training session assigned:
        <strong>{{ $class->title }}</strong>.
    </p>

    <p>
        <strong>📅 Date:</strong> {{ $class->date_iso }}<br>
        <strong>🕒 Time:</strong> {{ $class->time_range }}<br>
        <strong>👤 Participant:</strong> {{ $booking->name }} ({{ $booking->email }})<br>
        <strong>💻 Session:</strong> {{ $class->modality }}<br>
        @if($class->calendar_url)
            <strong>🔗 Calendar / Meet link:</strong>
            <a href="{{ $class->calendar_url }}" target="_blank">
                {{ $class->calendar_url }}
            </a>
            <br>
        @endif
    </p>

    <p>Saludos,<br>Alonso &amp; Alonso Academy</p>
</body>
</html>
