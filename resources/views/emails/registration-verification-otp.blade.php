<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify your registration email</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 24px; color: #1a1a1a;">
    <h2>Verify your registration email</h2>
    <p>You started a registration for <strong>{{ $eventName }}</strong>.</p>
    <p>Enter this code on the verification page in the browser where you registered:</p>
    <p style="font-family: monospace; font-size: 32px; letter-spacing: 8px; padding: 12px 0;">
        {{ $code }}
    </p>
    <p>This code expires in {{ $expiresInMinutes }} minutes and can be used once.</p>
    <p>Your registration is only complete after every required contact is verified and you select Complete registration.</p>
    <p style="color: #666; font-size: 13px;">If you did not request this code, ignore this email. Do not share the code.</p>
</body>
</html>
