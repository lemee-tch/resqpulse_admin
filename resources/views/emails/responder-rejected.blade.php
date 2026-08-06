<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, Arial, sans-serif; background: #f4f6fb; margin: 0; padding: 24px; }
        .card { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; }
        .brand { font-weight: 800; color: #1a3c8f; font-size: 1.1rem; margin-bottom: 18px; }
        h2 { color: #111827; font-size: 1.1rem; margin-bottom: 12px; }
        p { color: #4b5563; font-size: .9rem; line-height: 1.6; }
        .reason-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 14px; color: #991b1b; font-size: .85rem; margin: 16px 0; }
        .footer { margin-top: 24px; font-size: .75rem; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">RESQPULSE — MDRRMO Rosales</div>
        <h2>Hi {{ $responderName }},</h2>
        <p>
            Thank you for applying for a responder account on ResQPulse. After review,
            we're unable to verify your application at this time and your account has
            been removed.
        </p>
        <div class="reason-box"><strong>Reason:</strong> {{ $reason }}</div>
        <p>
            If you believe this was a mistake, or you'd like to correct the issue and
            re-apply, please contact your MDRRMO agency coordinator or register again
            with updated information.
        </p>
        <div class="footer">This is an automated message from the ResQPulse Responder system.</div>
    </div>
</body>
</html>