<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Backoffice | Marea Roja</title>
    <style>
        :root {
            --red-950: #220206;
            --red-900: #3d050b;
            --red-800: #650713;
            --red-700: #8f0d1d;
            --red-600: #c1122a;
            --red-500: #e31b36;
            --red-100: #ffe7ea;
            --gold: #ffd166;
            --white: #fff8f3;
            --line: rgba(255, 255, 255, .18);
            --glass: rgba(38, 3, 9, .68);
            --shadow: 0 30px 90px rgba(0, 0, 0, .45);
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background: var(--red-950);
        }

        body {
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            color: var(--white);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            letter-spacing: 0;
        }

        button,
        input {
            font: inherit;
        }

        .login-shell {
            position: relative;
            display: grid;
            min-height: 100vh;
            isolation: isolate;
            background:
                radial-gradient(circle at 14% 22%, rgba(255, 209, 102, .16), transparent 26%),
                radial-gradient(circle at 78% 12%, rgba(255, 255, 255, .12), transparent 23%),
                linear-gradient(135deg, var(--red-950), var(--red-800) 48%, #120104);
        }

        .login-video,
        .login-video-tint,
        .pitch-lines,
        .cursor-trail {
            position: fixed;
            pointer-events: none;
        }

        .login-video {
            inset: 0;
            z-index: -5;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: .45;
            filter: saturate(1.15) contrast(1.08);
        }

        .login-video-tint {
            inset: 0;
            z-index: -4;
            background:
                linear-gradient(90deg, rgba(34, 2, 6, .9), rgba(80, 4, 16, .62) 47%, rgba(13, 1, 3, .88)),
                radial-gradient(circle at center, transparent 0 16%, rgba(0, 0, 0, .42) 74%);
        }

        .pitch-lines {
            inset: 0;
            z-index: -3;
            opacity: .24;
            background:
                linear-gradient(90deg, transparent 49.7%, rgba(255, 255, 255, .38) 49.7% 50.3%, transparent 50.3%),
                radial-gradient(circle at 50% 50%, transparent 0 9.4rem, rgba(255, 255, 255, .4) 9.5rem 9.65rem, transparent 9.75rem),
                repeating-linear-gradient(0deg, rgba(255, 255, 255, .12) 0 1px, transparent 1px 72px);
            transform: perspective(900px) rotateX(62deg) scale(1.5) translateY(9%);
            transform-origin: center bottom;
        }

        .pitch-lines::before,
        .pitch-lines::after {
            position: absolute;
            left: 50%;
            width: min(58vw, 640px);
            height: min(23vh, 220px);
            border: 2px solid rgba(255, 255, 255, .32);
            content: "";
            transform: translateX(-50%);
        }

        .pitch-lines::before {
            top: 7%;
        }

        .pitch-lines::after {
            bottom: 7%;
        }

        .cursor-trail {
            inset: 0;
            z-index: 4;
            overflow: hidden;
        }

        .trail-dot {
            position: absolute;
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--gold);
            box-shadow: 0 0 18px rgba(255, 209, 102, .8);
            animation: fade-kick .72s ease-out forwards;
            transform: translate(-50%, -50%);
        }

        .content {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(360px, 430px);
            gap: clamp(28px, 5vw, 72px);
            align-items: center;
            width: min(1120px, calc(100% - 40px));
            min-height: 100vh;
            margin: 0 auto;
            padding: 48px 0;
        }

        .brand-panel {
            animation: rise-in .7s ease-out both;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 36px;
            padding: 8px 12px;
            border: 1px solid rgba(255, 255, 255, .22);
            border-radius: 999px;
            background: rgba(255, 255, 255, .08);
            color: var(--red-100);
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .eyebrow::before {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: var(--gold);
            box-shadow: 0 0 20px rgba(255, 209, 102, .8);
            content: "";
        }

        h1 {
            max-width: 760px;
            margin: 22px 0 18px;
            color: #fff;
            font-size: clamp(44px, 7vw, 88px);
            line-height: .95;
            letter-spacing: 0;
            text-wrap: balance;
        }

        .brand-copy {
            max-width: 620px;
            margin: 0;
            color: rgba(255, 248, 243, .82);
            font-size: clamp(17px, 2vw, 21px);
            line-height: 1.55;
        }

        .match-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 30px;
        }

        .match-strip span {
            display: inline-flex;
            align-items: center;
            min-height: 38px;
            padding: 9px 13px;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 999px;
            background: rgba(255, 255, 255, .08);
            color: rgba(255, 248, 243, .88);
            font-size: 13px;
            font-weight: 800;
        }

        .login-card {
            position: relative;
            overflow: hidden;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 8px;
            background: var(--glass);
            box-shadow: var(--shadow);
            backdrop-filter: blur(22px);
            animation: rise-in .7s ease-out .12s both;
        }

        .login-card::before {
            position: absolute;
            inset: 0 0 auto;
            height: 5px;
            background: linear-gradient(90deg, var(--gold), #fff, var(--red-500), var(--gold));
            content: "";
        }

        .login-card::after {
            position: absolute;
            top: -120px;
            right: -110px;
            width: 240px;
            height: 240px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(255, 209, 102, .24), transparent 68%);
            content: "";
        }

        .form-inner {
            position: relative;
            z-index: 1;
            padding: clamp(24px, 4vw, 34px);
        }

        .form-header {
            margin-bottom: 24px;
        }

        .form-kicker {
            margin: 0 0 8px;
            color: var(--gold);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .form-header h2 {
            margin: 0;
            color: #fff;
            font-size: 28px;
            line-height: 1.12;
            letter-spacing: 0;
        }

        .form-header p {
            margin: 10px 0 0;
            color: rgba(255, 248, 243, .72);
            line-height: 1.45;
        }

        .error-box {
            margin: 0 0 18px;
            padding: 12px 14px;
            border: 1px solid rgba(255, 209, 102, .42);
            border-radius: 8px;
            background: rgba(255, 209, 102, .12);
            color: #fff4d0;
            font-size: 14px;
            line-height: 1.4;
        }

        .field {
            display: grid;
            gap: 8px;
            margin-top: 15px;
        }

        .field label {
            color: rgba(255, 248, 243, .82);
            font-size: 13px;
            font-weight: 800;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 14px;
            display: grid;
            width: 22px;
            height: 22px;
            place-items: center;
            color: rgba(255, 248, 243, .66);
            transform: translateY(-50%);
        }

        .input-icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        input {
            width: 100%;
            height: 52px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 8px;
            outline: none;
            background: rgba(12, 1, 3, .56);
            color: #fff;
            padding: 0 14px 0 48px;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        input::placeholder {
            color: rgba(255, 248, 243, .42);
        }

        input:focus {
            border-color: rgba(255, 209, 102, .82);
            background: rgba(24, 2, 6, .78);
            box-shadow: 0 0 0 4px rgba(255, 209, 102, .14);
        }

        .submit-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            min-height: 54px;
            margin-top: 22px;
            border: 0;
            border-radius: 8px;
            overflow: hidden;
            background: linear-gradient(135deg, #ff3349, var(--red-600) 46%, #8f0d1d);
            color: #fff;
            cursor: pointer;
            font-weight: 900;
            box-shadow: 0 18px 40px rgba(193, 18, 42, .38);
            transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
        }

        .submit-btn::before {
            position: absolute;
            inset: 0;
            background: linear-gradient(110deg, transparent, rgba(255, 255, 255, .28), transparent);
            content: "";
            transform: translateX(-120%);
            transition: transform .5s ease;
        }

        .submit-btn:hover,
        .submit-btn:focus-visible {
            transform: translateY(-2px);
            filter: saturate(1.08);
            box-shadow: 0 22px 48px rgba(193, 18, 42, .5);
        }

        .submit-btn:hover::before,
        .submit-btn:focus-visible::before {
            transform: translateX(120%);
        }

        .submit-btn svg {
            position: relative;
            width: 20px;
            height: 20px;
            stroke: currentColor;
            stroke-width: 2.2;
            fill: none;
        }

        .submit-btn span {
            position: relative;
        }

        .footer-note {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 22px;
            color: rgba(255, 248, 243, .58);
            font-size: 12px;
            line-height: 1.35;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            flex: 0 0 auto;
            color: rgba(255, 248, 243, .8);
            font-weight: 800;
        }

        .status-pill::before {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #35e07a;
            box-shadow: 0 0 14px rgba(53, 224, 122, .75);
            content: "";
        }

        @keyframes rise-in {
            from {
                opacity: 0;
                transform: translateY(22px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fade-kick {
            from {
                opacity: .92;
                transform: translate(-50%, -50%) scale(1);
            }
            to {
                opacity: 0;
                transform: translate(-50%, -50%) scale(.2);
            }
        }

        @media (max-width: 860px) {
            .content {
                grid-template-columns: 1fr;
                align-content: center;
                gap: 28px;
                width: min(100% - 28px, 520px);
                padding: 28px 0;
            }

            .brand-panel {
                text-align: center;
            }

            .eyebrow,
            .match-strip {
                justify-content: center;
            }

            .brand-copy {
                margin: 0 auto;
            }

            .pitch-lines {
                opacity: .16;
                transform: perspective(760px) rotateX(60deg) scale(1.9) translateY(12%);
            }
        }

        @media (max-width: 480px) {
            .content {
                width: min(100% - 20px, 430px);
            }

            h1 {
                font-size: 42px;
            }

            .match-strip span {
                min-height: 34px;
                padding: 8px 11px;
                font-size: 12px;
            }

            .form-inner {
                padding: 24px 18px;
            }

            .footer-note {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (hover: none), (pointer: coarse), (prefers-reduced-motion: reduce) {
            .cursor-trail {
                display: none;
            }

            .brand-panel,
            .login-card {
                animation: none;
            }
        }
    </style>
</head>
<body>
    <main class="login-shell">
        <video class="login-video" autoplay muted loop playsinline aria-hidden="true">
            <source src="{{ asset('videos/login-bg.mp4') }}" type="video/mp4">
        </video>
        <div class="login-video-tint" aria-hidden="true"></div>
        <div class="pitch-lines" aria-hidden="true"></div>
        <div class="cursor-trail" aria-hidden="true"></div>

        <section class="content" aria-label="Acceso al backoffice">
            <div class="brand-panel">
                <div class="eyebrow">FEPATUD | Marea Roja</div>
                <h1>Control total del torneo.</h1>
                <p class="brand-copy">
                    Administra partidos, reglas, premios y resultados desde un backoffice visual,
                    rápido y alineado con la energía de la Marea Roja.
                </p>
                <div class="match-strip" aria-label="Módulos del sistema">
                    <span>Partidos</span>
                    <span>Reglas</span>
                    <span>Premios</span>
                    <span>Ganadores</span>
                </div>
            </div>

            <form class="login-card" method="post" action="{{ route('admin.login.submit') }}">
                @csrf
                <div class="form-inner">
                    <div class="form-header">
                        <p class="form-kicker">Backoffice seguro</p>
                        <h2>Entra al panel</h2>
                        <p>Usa tus credenciales administrativas para continuar.</p>
                    </div>

                    @if($errors->any())
                        <p class="error-box">{{ $errors->first() }}</p>
                    @endif

                    <div class="field">
                        <label for="email">Correo</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z"></path><path d="m4 7 8 6 8-6"></path></svg>
                            </span>
                            <input id="email" type="email" name="email" placeholder="admin@fepatud.com" value="{{ old('email') }}" autocomplete="email" required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Contraseña</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path></svg>
                            </span>
                            <input id="password" type="password" name="password" placeholder="Tu contraseña" autocomplete="current-password" required>
                        </div>
                    </div>

                    <button class="submit-btn" type="submit">
                        <span>Entrar al backoffice</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"></path><path d="m13 6 6 6-6 6"></path></svg>
                    </button>

                    <div class="footer-note">
                        <span>Acceso exclusivo para administradores autorizados.</span>
                        <span class="status-pill">Sistema activo</span>
                    </div>
                </div>
            </form>
        </section>
    </main>

    <script>
        (() => {
            const trail = document.querySelector('.cursor-trail');

            if (!trail || window.matchMedia('(pointer: coarse)').matches || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            let lastTrail = 0;

            const addTrail = (x, y) => {
                const dot = document.createElement('span');
                dot.className = 'trail-dot';
                dot.style.left = `${x}px`;
                dot.style.top = `${y}px`;
                trail.appendChild(dot);
                setTimeout(() => dot.remove(), 760);
            };

            window.addEventListener('mousemove', (event) => {
                const now = performance.now();
                if (now - lastTrail > 46) {
                    addTrail(event.clientX, event.clientY);
                    lastTrail = now;
                }
            }, { passive: true });

        })();
    </script>
</body>
</html>
