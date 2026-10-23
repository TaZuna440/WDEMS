<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your {{ $eventName }} Registration Code</title>
</head>
<body style="margin:0; padding:32px 16px; background:#f3f5f7; color:#1b2733; font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" style="width:100%; max-width:560px; margin:0 auto; border-collapse:collapse; background:#ffffff; border-radius:12px; overflow:hidden;">
        <tr>
            <td style="padding:28px 32px; background:#13253b; color:#ffffff;">
                <p style="margin:0 0 8px; color:#c9e77a; font-size:12px; font-weight:bold; letter-spacing:1.5px; text-transform:uppercase;">WDEMS Registration</p>
                <h1 style="margin:0; font-size:24px; line-height:1.3;">You're registered</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:28px 32px;">
                <p style="margin:0 0 8px; font-size:16px; line-height:1.6;">Your registration for <strong>{{ $eventName }}</strong> is confirmed.</p>
                <p style="margin:20px 0 10px; color:#526170; font-size:14px;">Keep this Registration Code. Show it to event staff to look up your registration.</p>
                <div style="padding:20px 12px; border:1px solid #dce3e9; border-radius:8px; background:#f8fafb; text-align:center;">
                    <p style="margin:0 0 8px; color:#526170; font-size:11px; font-weight:bold; letter-spacing:1.4px; text-transform:uppercase;">Registration Code</p>
                    <p style="margin:0; color:#13253b; font-family:'Courier New',Courier,monospace; font-size:32px; font-weight:bold; letter-spacing:5px;">{{ $code }}</p>
                </div>
                <p style="margin:20px 0 0; color:#526170; font-size:13px; line-height:1.6;">The code does not expire. You can return to this email if you lose the code shown on your screen.</p>
            </td>
        </tr>
        <tr>
            <td style="padding:18px 32px; border-top:1px solid #e8edf1; color:#73808c; font-size:12px; line-height:1.5;">
                If you did not register for this event, you can ignore this email.
            </td>
        </tr>
    </table>
</body>
</html>
