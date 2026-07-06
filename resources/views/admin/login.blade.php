<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Admin — Room Booking</title>
    <meta name="description" content="Acceso al panel de administración de salas">
    <link rel="stylesheet" href="/css/app.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        /* ── Login page overrides ──────────────────────────────────── */
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-base);
            position: relative;
            overflow: hidden;
        }

        /* Animated background glow orbs */
        .login-page::before,
        .login-page::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.18;
            pointer-events: none;
        }
        .login-page::before {
            width: 500px; height: 500px;
            background: radial-gradient(circle, #4f8ef7, transparent);
            top: -100px; left: -100px;
            animation: floatA 8s ease-in-out infinite alternate;
        }
        .login-page::after {
            width: 400px; height: 400px;
            background: radial-gradient(circle, #9b59f7, transparent);
            bottom: -80px; right: -80px;
            animation: floatB 10s ease-in-out infinite alternate;
        }

        @keyframes floatA { from { transform: translate(0,0); } to { transform: translate(40px,30px); } }
        @keyframes floatB { from { transform: translate(0,0); } to { transform: translate(-30px,-20px); } }

        /* Card */
        .login-card {
            background: rgba(21, 24, 33, 0.85);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 24px;
            padding: 48px 44px 44px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.05);
            position: relative;
            z-index: 1;
            animation: cardIn 0.4s cubic-bezier(0.4,0,0.2,1);
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(24px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 32px;
        }

        .login-logo-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #4f8ef7 0%, #9b59f7 100%);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
            box-shadow: 0 8px 24px rgba(79,142,247,0.35);
        }

        .login-logo-text {
            display: flex; flex-direction: column;
        }

        .login-logo-title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.4px;
            color: var(--text-primary);
        }

        .login-logo-sub {
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 0.3px;
        }

        .login-divider {
            height: 1px;
            background: var(--border);
            margin-bottom: 28px;
        }

        .login-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.4px;
        }

        .login-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 28px;
        }

        /* PIN dots display */
        .pin-dots {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 24px;
        }

        .pin-dot {
            width: 16px; height: 16px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.15);
            background: transparent;
            transition: all 0.2s cubic-bezier(0.4,0,0.2,1);
        }

        .pin-dot.filled {
            background: var(--totvs);
            border-color: var(--totvs);
            box-shadow: 0 0 12px rgba(79,142,247,0.5);
            transform: scale(1.1);
        }

        .pin-dot.error {
            background: #ef4444;
            border-color: #ef4444;
            box-shadow: 0 0 12px rgba(239,68,68,0.5);
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%       { transform: translateX(-6px); }
            40%       { transform: translateX(6px); }
            60%       { transform: translateX(-4px); }
            80%       { transform: translateX(4px); }
        }

        /* Numpad */
        .numpad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }

        .numpad-btn {
            height: 58px;
            border-radius: 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.15s cubic-bezier(0.4,0,0.2,1);
            display: flex; align-items: center; justify-content: center;
            user-select: none;
        }

        .numpad-btn:hover {
            background: rgba(255,255,255,0.09);
            border-color: rgba(255,255,255,0.18);
            transform: translateY(-1px);
        }

        .numpad-btn:active {
            transform: scale(0.95);
            background: rgba(79,142,247,0.2);
            border-color: rgba(79,142,247,0.4);
        }

        .numpad-btn.delete-btn {
            font-size: 18px;
            color: var(--text-muted);
        }

        .numpad-btn.confirm-btn {
            background: linear-gradient(135deg, #4f8ef7, #7c6ef7);
            border: none;
            color: #fff;
            font-size: 20px;
            box-shadow: 0 4px 16px rgba(79,142,247,0.3);
        }
        .numpad-btn.confirm-btn:hover {
            box-shadow: 0 6px 24px rgba(79,142,247,0.45);
            transform: translateY(-1px);
        }

        /* Error alert */
        .login-error {
            display: flex; align-items: center; gap: 8px;
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.28);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            color: #f87171;
            margin-bottom: 16px;
            animation: fadeIn 0.2s ease;
        }

        /* Success alert */
        .login-success {
            display: flex; align-items: center; gap: 8px;
            background: rgba(46,204,113,0.12);
            border: 1px solid rgba(46,204,113,0.28);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            color: #2ecc71;
            margin-bottom: 16px;
        }

        .login-hint {
            text-align: center;
            font-size: 11px;
            color: var(--text-dim);
            margin-top: 16px;
        }

        /* Hidden real input (captures keyboard) */
        .pin-hidden-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
            width: 1px; height: 1px;
        }
    </style>
</head>
<body>
<div class="login-page" x-data="pinApp()" @keydown.window="handleKey($event)" x-init="focusInput()">

    <div class="login-card">

        <!-- Logo -->
        <div class="login-logo">
            <div class="login-logo-icon">📅</div>
            <div class="login-logo-text">
                <span class="login-logo-title">Room Booking</span>
                <span class="login-logo-sub">Sistema de reserva de salas</span>
            </div>
        </div>

        <div class="login-divider"></div>

        <h1 class="login-title">🔐 Acceso Admin</h1>
        <p class="login-subtitle">Ingresa el PIN de administrador para continuar.</p>

        <!-- Success message (after logout) -->
        @if(session('message'))
        <div class="login-success">
            <span>✓</span> {{ session('message') }}
        </div>
        @endif

        <!-- Error (server-side validation) -->
        @error('pin')
        <div class="login-error">
            <span>✖</span> {{ $message }}
        </div>
        @enderror

        <!-- PIN dots indicator -->
        <div class="pin-dots" id="pin-dots">
            <template x-for="i in maxLen" :key="i">
                <div class="pin-dot"
                     :class="{
                         'filled': pin.length >= i && !hasError,
                         'error':  pin.length >= i &&  hasError
                     }">
                </div>
            </template>
        </div>

        <!-- Alpine error message -->
        <div x-show="hasError" class="login-error" x-cloak>
            <span>✖</span>
            <span x-text="errorText"></span>
        </div>

        <!-- Hidden form + input (for real submission) -->
        <form id="pin-form" method="POST" action="{{ route('admin.login.post') }}" style="display:none;">
            @csrf
            <input type="password" name="pin" id="real-pin" class="pin-hidden-input" x-ref="hiddenInput">
        </form>

        <!-- Numpad -->
        <div class="numpad">
            <template x-for="n in [1,2,3,4,5,6,7,8,9]" :key="n">
                <button type="button" class="numpad-btn" @click="addDigit(n.toString())">
                    <span x-text="n"></span>
                </button>
            </template>
            <!-- Delete -->
            <button type="button" class="numpad-btn delete-btn" @click="deleteDigit()">⌫</button>
            <!-- 0 -->
            <button type="button" class="numpad-btn" @click="addDigit('0')">0</button>
            <!-- Confirm -->
            <button type="button" class="numpad-btn confirm-btn" @click="submitPin()" :disabled="pin.length === 0">
                ✓
            </button>
        </div>

        <p class="login-hint">Las tablets de cada sala no requieren PIN.</p>
    </div>

</div>

<script>
function pinApp() {
    return {
        pin: '',
        maxLen: 4,
        hasError: false,
        errorText: '',

        focusInput() {
            this.$nextTick(() => {
                if (this.$refs.hiddenInput) this.$refs.hiddenInput.focus();
            });
        },

        addDigit(d) {
            if (this.pin.length >= this.maxLen) return;
            this.hasError = false;
            this.pin += d;
            if (this.$refs.hiddenInput) this.$refs.hiddenInput.value = this.pin;
            if (this.pin.length === this.maxLen) {
                // Auto-submit when PIN is complete
                setTimeout(() => this.submitPin(), 120);
            }
        },

        deleteDigit() {
            this.hasError = false;
            this.pin = this.pin.slice(0, -1);
            if (this.$refs.hiddenInput) this.$refs.hiddenInput.value = this.pin;
        },

        handleKey(e) {
            if (e.key >= '0' && e.key <= '9') { this.addDigit(e.key); return; }
            if (e.key === 'Backspace') { this.deleteDigit(); return; }
            if (e.key === 'Enter') { this.submitPin(); }
        },

        submitPin() {
            if (this.pin.length === 0) {
                this.hasError = true;
                this.errorText = 'Ingresa el PIN antes de continuar.';
                return;
            }
            document.getElementById('real-pin').value = this.pin;
            document.getElementById('pin-form').submit();
        },
    };
}
</script>
</body>
</html>
