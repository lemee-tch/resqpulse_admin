<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Admin Access Portal</title>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
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

        /* ── Card shell ── */
        .login-card {
            width: 100%;
            max-width: 860px;
            min-height: 480px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            display: flex;
        }

        /* ── Left panel ── */
        .left-panel {
            width: 44%;
            background: #1a3c8f;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 28px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .logo-wrap {
            width: 155px;
            height: 155px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 22px;
            padding: 10px;
        }

        .logo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 20%;
        }

        .logo-placeholder .logo-icon {
            font-size: 36px;
            color: #e8304a;
            margin-bottom: 4px;
        }

        .brand-name {
            font-family: 'Barlow', sans-serif;
            font-weight: 800;
            font-size: 2rem;
            color: #ffffff;
            letter-spacing: 2px;
            margin-bottom: 20px;
        }

        .brand-desc {
            font-family: 'Inter', sans-serif;
            font-weight: 400;
            font-size: .82rem;
            color: rgba(255, 255, 255, .75);
            line-height: 1.6;
            margin-bottom: 6px;
        }

        .brand-sub {
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: .82rem;
            color: rgba(255, 255, 255, .85);
            letter-spacing: .5px;
            margin-top: 8px;
        }

        .right-panel {
            width: 56%;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 44px;
        }

        .right-panel h2 {
            font-family: 'Barlow', sans-serif;
            font-weight: 800;
            font-size: 1.75rem;
            color: #111827;
            margin-bottom: 4px;
        }

        .right-panel p.subtitle {
            font-size: .9rem;
            color: #6b7280;
            margin-bottom: 32px;
        }

        /* Form labels */
        .form-label {
            font-weight: 600;
            font-size: .85rem;
            color: #374151;
            margin-bottom: 6px;
        }

        /* Inputs */
        .form-control {
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            padding: 11px 14px;
            font-size: .9rem;
            color: #111827;
            transition: border-color .2s, box-shadow .2s;
            background: #fafafa;
        }

        .form-control:focus {
            border-color: #1a3c8f;
            box-shadow: 0 0 0 3px rgba(26, 60, 143, .12);
            background: #fff;
            outline: none;
        }

        .form-control::placeholder { color: #9ca3af; }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-control {
            padding-right: 44px;
        }

        .toggle-pwd {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            font-size: 1.05rem;
            padding: 0;
            line-height: 1;
        }

        .toggle-pwd:hover { color: #374151; }

        .form-check-input:checked {
            background-color: #1a3c8f;
            border-color: #1a3c8f;
        }

        .form-check-label {
            font-size: .83rem;
            color: #6b7280;
        }

        .forgot-link {
            font-size: .83rem;
            color: #1a3c8f;
            text-decoration: none;
            font-weight: 600;
        }

        .forgot-link:hover {
            text-decoration: underline;
            color: #0d2e7a;
        }

        .btn-signin {
            background: #1a3c8f;
            border: none;
            border-radius: 50px;
            color: #fff;
            font-family: 'Barlow', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: .5px;
            padding: 13px;
            width: 100%;
            transition: background .2s, box-shadow .2s, transform .1s;
        }

        .btn-signin:hover {
            background: #152f72;
            box-shadow: 0 6px 20px rgba(26, 60, 143, .35);
            transform: translateY(-1px);
        }

        .btn-signin:active { transform: translateY(0); }

        @media (max-width: 640px) {
            .login-card {
                flex-direction: column;
                max-width: 400px;
                border-radius: 12px;
            }

            .left-panel,
            .right-panel {
                width: 100%;
            }

            .left-panel {
                padding: 32px 24px;
            }

            .right-panel {
                padding: 36px 28px;
            }
        }
    </style>
</head>
<body>

<div class="login-card">

    <!-- ════ LEFT PANEL ════ -->
    <div class="left-panel">
        <div class="logo-wrap">
                <img src="{{ asset('images/logo.png') }}" alt="RESQPULSE Logo">
        </div>

        <div class="brand-name">RESQPULSE</div>

        <p class="brand-desc">
            Disaster Risk Reduction<br>and Management Office
        </p>

        <p class="brand-sub">Admin Access Portal</p>
    </div>

    <!-- ════ RIGHT PANEL ════ -->
    <div class="right-panel">
        <h2>Welcome Back!</h2>
        <p class="subtitle">Sign in to your account</p>

        <form method="POST" action="{{ route('login') }}" class="w-100">
            @csrf

            <!-- Email -->
            <div class="mb-3">
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
            </div>

            <!-- Password -->
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        placeholder="Enter your password"
                        required
                    >
                    <button type="button" class="toggle-pwd" onclick="togglePassword()" aria-label="Toggle password visibility">
                        <i class="bi bi-eye-slash" id="toggleIcon"></i>
                    </button>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Remember me + Forgot password -->
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="remember"
                        name="remember"
                        {{ old('remember') ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="forgot-link">Forgot Password?</a>
                @else
                    <a href="#" class="forgot-link">Forgot Password?</a>
                @endif
            </div>

            <!-- Submit -->
            <button type="submit" class="btn btn-signin">Sign In</button>
        </form>
    </div>

</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function togglePassword() {
        const pwd  = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        } else {
            pwd.type = 'password';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        }
    }
</script>

</body>
</html>