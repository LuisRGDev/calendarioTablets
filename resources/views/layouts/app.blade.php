<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Salas') — Room Booking</title>
    <meta name="description" content="Sistema de reserva de salas corporativas">
    <link rel="stylesheet" href="/css/app.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body>
<div class="app-shell {{ request()->has('kiosk') || request()->query('kiosk') === 'true' ? 'kiosk-mode' : '' }}" x-data="clockApp()" x-init="startClock()">

    <!-- Top Bar -->
    <header class="topbar">
        <div class="topbar-logo">
            <div class="logo-icon">📅</div>
            <span>MIDDLEBY</span>
        </div>

        <nav class="topbar-nav">
            <a href="{{ route('admin.dashboard') }}"
               class="{{ request()->routeIs('admin.*') ? 'active' : '' }}">
               🏢 Administrador
            </a>
            @foreach(App\Models\Room::all() as $r)
            <a href="{{ route('room.show', $r->slug) }}"
               class="{{ request()->routeIs('room.show') && request()->route('room')?->id === $r->id ? 'active' : '' }}"
               style="{{ request()->routeIs('room.show') && request()->route('room')?->id === $r->id ? 'color:'.$r->color : '' }}">
               {{ $r->name }}
            </a>
            @endforeach
        </nav>

        <div class="topbar-spacer"></div>

        <span class="topbar-time" x-text="currentTime"></span>

        @if(session('admin_authenticated'))
        <div style="display:flex;align-items:center;gap:8px;margin-left:12px;padding-left:12px;border-left:1px solid var(--border);">
            <span style="font-size:11px;color:var(--text-muted);">🔐 Admin</span>
            <form method="POST" action="{{ route('admin.logout') }}" style="margin:0;">
                @csrf
                <button type="submit"
                        style="padding:5px 10px;border-radius:6px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);color:#f87171;font-size:11px;font-weight:600;cursor:pointer;transition:all 0.2s;"
                        onmouseover="this.style.background='rgba(239,68,68,0.22)'"
                        onmouseout="this.style.background='rgba(239,68,68,0.12)'">
                    Cerrar sesión
                </button>
            </form>
        </div>
        @endif
    </header>

    <!-- Page Content -->
    <main class="content">
        @yield('content')
    </main>

</div>

<script>
function clockApp() {
    return {
        currentTime: '',
        startClock() {
            this.tick();
            setInterval(() => this.tick(), 1000);
        },
        tick() {
            const now = new Date();
            this.currentTime = now.toLocaleTimeString('es-MX', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
        }
    };
}
</script>

@yield('scripts')
</body>
</html>
