<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Verification Code</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 24px; color: #1a1a1a;">
    <h2 style="margin: 0 0 16px;">Login Verification</h2>

    <p>You are signing in to WDEMS from a new device.</p>

    <p>Enter this code to continue:</p>

    <p style="font-family: monospace; font-size: 32px; letter-spacing: 8px; padding: 12px 0; margin: 16px 0;">
        {{ $code }}
    </p>

    <p>This code expires in {{ $expiresInMinutes }} minutes.</p>

    <p style="color: #666; font-size: 13px;">
        If this wasn't you, change your password immediately.
        Do not share this code with anyone.
    </p>
</body>
</html>
