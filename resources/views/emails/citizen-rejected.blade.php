<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; background:#f4f6fb; padding:32px;">
    <div style="max-width:420px;margin:0 auto;background:#fff;border-radius:14px;padding:32px;">
        <h2 style="color:#1a3c8f;margin-top:0;">ResQPulse - ID Verification Update</h2>
        <p>Hi {{ $fullName }},</p>
        <p>We were unable to verify the valid ID submitted with your ResQPulse registration.</p>
        <div style="background:#fef2f2;border-radius:10px;padding:16px;margin:20px 0;border:1px solid #fecaca;">
            <strong style="color:#991b1b;">Reason:</strong>
            <p style="margin:6px 0 0;color:#7f1d1d;">{{ $reason }}</p>
        </div>
        <p>Your account has been removed. You're welcome to register again with a clear, valid ID showing your address within Rosales or a neighboring municipality.</p>
        <p style="color:#6b7280;font-size:13px;">— MDRRMO Rosales, ResQPulse Team</p>
    </div>
</body>
</html>