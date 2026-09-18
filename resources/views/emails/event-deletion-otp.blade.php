<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Deletion Verification Code</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 24px; color: #1a1a1a;">
    <h2 style="margin: 0 0 16px;">Event Deletion Verification</h2>

    <p>You requested to delete the event:</p>
    <p><strong>{{ $eventName }}</strong></p>

    <p>Your verification code is:</p>

    <p style="font-family: monospace; font-size: 32px; letter-spacing: 8px; padding: 12px 0; margin: 16px 0;">
        {{ $code }}
    </p>

    <p>This code expires in {{ $expiresInMinutes }} minutes.</p>

    <p style="color: #666; font-size: 13px;">
        If you did not request this, you can safely ignore this email.
        Do not share this code with anyone.
    </p>
</body>
</html>
