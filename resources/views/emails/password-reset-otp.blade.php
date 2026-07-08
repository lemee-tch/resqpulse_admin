<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; background:#f4f6fb; padding:32px;">
    <div style="max-width:420px;margin:0 auto;background:#fff;border-radius:14px;padding:32px;">
        <h2 style="color:#1a3c8f;margin-top:0;">ResQPulse Password Reset</h2>
        <p>Hi {{ $fullName }},</p>
        <p>Use the code below to reset your password. This code expires in 10 minutes.</p>
        <div style="background:#f0f4ff;border-radius:10px;padding:16px;text-align:center;margin:20px 0;">
            <span style="font-size:32px;font-weight:bold;letter-spacing:6px;color:#1a3c8f;">{{ $otp }}</span>
        </div>
        <p style="color:#6b7280;font-size:13px;">If you didn't request this, you can safely ignore this email.</p>
    </div>
</body>
</html>