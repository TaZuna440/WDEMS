<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your attendance code</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 24px; color: #1a1a1a;">
    <h2>You are registered for {{ $eventName }}.</h2>
    <p>Your attendance code is:</p>
    <p style="font-family: monospace; font-size: 32px; letter-spacing: 6px; padding: 12px 0; font-weight: bold;">
        {{ $code }}
    </p>
    <p>Show this code at the event table so staff can look you up and mark your attendance.</p>
    <p>Keep this email. The code does not change and does not expire.</p>
    <p style="color: #666; font-size: 13px;">If you did not register for this event, you can ignore this email.</p>
</body>
</html>
