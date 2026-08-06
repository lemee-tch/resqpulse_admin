<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Forgot Password</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-card {
            width: 100%;
            max-width: 440px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,.18);
            background: #fff;
            padding: 40px 40px 36px;
        }
        .icon-wrap {
            width: 64px; height: 64px; border-radius: 50%;
            background: #eaf0fb; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .icon-wrap i { font-size: 1.6rem; color: #1a3c8f; }
        h2 {
            font-family: 'Barlow', sans-serif; font-weight: 800;
            font-size: 1.5rem; color: #111827; text-align: center; margin-bottom: 6px;
        }
        p.subtitle { font-size: .86rem; color: #6b7280; text-align: center; margin-bottom: 28px; line-height: 1.5; }
        .form-label { font-weight: 600; font-size: .85rem; color: #374151; margin-bottom: 6px; }
        .form-control {
            border: 1.5px solid #d1d5db; border-radius: 8px; padding: 11px 14px;
            font-size: .9rem; color: #111827; background: #fafafa;
        }
        .form-control:focus { border-color: #1a3c8f; box-shadow: 0 0 0 3px rgba(26,60,143,.12); background: #fff; outline: none; }
        .btn-signin {
            background: #1a3c8f; border: none; border-radius: 50px; color: #fff;
            font-family: 'Barlow', sans-serif; font-weight: 700; font-size: 1rem;
            letter-spacing: .5px; padding: 13px; width: 100%; margin-top: 8px;
            transition: background .2s;
        }
        .btn-signin:hover { background: #152f72; color: #fff; }
        .back-link { display: block; text-align: center; margin-top: 20px; font-size: .83rem; color: #1a3c8f; text-decoration: none; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="icon-wrap"><i class="bi bi-shield-lock"></i></div>
    <h2>Forgot your password?</h2>
    <p class="subtitle">Enter the email linked to your admin account and we'll send you a 6-digit code to reset your password.</p>

    @if (session('success'))
        <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="email" class="form-label">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            class="form-control @error('email') is-invalid @enderror"
            placeholder="Enter your email"
            value="{{ old('email') }}"
            required
            autofocus
        >
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror

        <button type="submit" class="btn btn-signin">Send Code</button>
    </form>

    <a href="{{ route('login') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Login</a>
</div>

</body>
</html>