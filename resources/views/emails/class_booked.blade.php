<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Classes Available</title>
</head>
<body>
    <h1>{{ $booking->name ?? '¡Hola!' }} class</h1>

    <p>We've received your reservation for the class. <strong>{{ $classSession->title }}</strong>.</p>

    <p>
        📅 Date: {{ \Carbon\Carbon::parse($classSession->date_iso)->isoFormat('dddd, D [de] MMMM [de] YYYY') }}<br>
        🕒 Time: {{ $classSession->time_range }}<br>
        👩‍🏫 Trainer: {{ $classSession->trainer_name }}<br>
        💻 Session: {{ $classSession->modality }}<br>
    </p>

    <p>
        You'll receive more information shortly, along with the access link if the class is online.
    </p>

    <p>Thank you for scheduling your training with Alonso & Alonso.</p>
</body>
</html>