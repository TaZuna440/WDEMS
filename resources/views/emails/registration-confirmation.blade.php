<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Registration Code</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 24px; color: #1a1a1a;">
    <h2>You are registered for {{ $eventName }}.</h2>
    <p>Your Registration Code is:</p>
    <p style="font-family: monospace; font-size: 32px; letter-spacing: 6px; padding: 12px 0; font-weight: bold;">
        {{ $code }}
    </p>
    <p>Show this Registration Code at the event table so staff can find your registration.</p>
    <p>Keep this email. Your Registration Code does not change and does not expire.</p>
    <p style="color: #666; font-size: 13px;">If you did not register for this event, you can ignore this email.</p>
</body>
</html>
