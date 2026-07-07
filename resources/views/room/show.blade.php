@extends('layouts.app')

@section('title', $room->name)

@section('content')
@php
    // Build time slots: 8:00 to 17:00 in 1-hour increments (representing 8:00-18:00)
    $slots = [];
    $startHour = 8; $startMin = 0;
    $endHour = 17;  $endMin = 0;
    $t = \Carbon\Carbon::today()->setHour($startHour)->setMinute($startMin)->setSecond(0);
    $end = \Carbon\Carbon::today()->setHour($endHour)->setMinute($endMin)->setSecond(0);
    while ($t->lte($end)) {
        $slots[] = $t->copy();
        $t->addHours(1);
    }

    // Current status
    $isOccupied = $room->isOccupiedNow();
    $currentEvent = $room->currentEvent();
    $nextEvent = $room->nextEvent();

    // Week prev/next
    $prevWeek = $weekStart->copy()->subWeek()->toDateString();
    $nextWeek = $weekStart->copy()->addWeek()->toDateString();
    $todayWeek = \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
@endphp

<!-- Wrapper to share Alpine.js scope -->
<div x-data="calendarApp('{{ $room->id }}', '{{ $room->color }}', {{ json_encode($events->map(fn($e) => [
     'id'         => $e->id,
     'title'      => $e->title,
     'organizer'  => $e->organizer,
     'description'=> $e->description,
     'start_time' => $e->start_time->toIso8601String(),
     'end_time'   => $e->end_time->toIso8601String(),
     'recurrence' => $e->recurrence,
     'recurrence_end' => $e->recurrence_end?->toDateString(),
     'color'      => $e->color,
 ])->values()) }}, '{{ $weekStart->toDateString() }}', '{{ $weekEnd->toDateString() }}')">

    <!-- ── GRID VIEW (WEEKLY GRID) ── -->
    <div x-show="viewMode === 'grid'" style="display: flex; flex-direction: column; flex: 1; min-height: 0;">
        <!-- Room Header -->
        <div class="room-header">
            <div class="room-color-bar" style="background: {{ $room->color }}"></div>
            <div class="room-header-info">
                <div class="room-header-title">{{ $room->name }}</div>
                <div class="room-header-sub">{{ $room->description }}</div>
            </div>

            <template x-if="isOccupiedNow()">
                <span class="status-badge busy">
                    <span class="status-dot"></span>
                    <span x-text="'Ocupada · ' + (currentEvent() ? currentEvent().title : '')"></span>
                </span>
            </template>
            <template x-if="!isOccupiedNow()">
                <span class="status-badge free">
                    <span class="status-dot"></span>
                    Disponible
                </span>
            </template>

            <template x-if="nextEvent() && !isOccupiedNow()">
                <div class="room-next-event">
                    📌 Próximo: <strong x-text="nextEvent().title"></strong>
                    · <span x-text="formatTime(nextEvent().start_time)"></span>
                </div>
            </template>

            <!-- Kiosk Toggle Button -->
            <div style="margin-left: 12px;">
                @if(request()->has('kiosk') || request()->query('kiosk') === 'true')
                    <a href="?{{ http_build_query(request()->except('kiosk')) }}" class="btn btn-ghost" style="padding: 6px 12px; font-size:12px; display: inline-flex; align-items: center; gap: 4px;">
                        🚪 Salir Kiosko
                    </a>
                @else
                    <a href="?{{ http_build_query(array_merge(request()->query(), ['kiosk' => 'true'])) }}" class="btn btn-ghost" style="padding: 6px 12px; font-size:12px; display: inline-flex; align-items: center; gap: 4px;">
                        📺 Modo Kiosko
                    </a>
                @endif
            </div>
        </div>

        <!-- Calendar -->
        <div class="calendar-wrap">

            <!-- Week navigation -->
            <div class="flex items-center gap-2" style="padding: 12px 0 0;">
                <div class="week-nav">
                    <a href="?week={{ $prevWeek }}" class="btn-icon">‹</a>
                    <span class="week-nav-label">
                        {{ $weekStart->translatedFormat('d M') }} – {{ $weekEnd->translatedFormat('d M Y') }}
                    </span>
                    <a href="?week={{ $nextWeek }}" class="btn-icon">›</a>
                    @if($weekStart->toDateString() !== $todayWeek)
                        <a href="?week={{ $todayWeek }}" class="btn-today">Hoy</a>
                    @endif
                </div>

                <div class="ml-auto">
                    <button class="btn btn-primary" @click="openCreate(null, null)">
                        + Nuevo Evento
                    </button>
                    <button class="btn btn-secondary" @click="viewMode = 'agenda'" style="margin-left: 8px;">
                        📋 Vista Agenda
                    </button>
                </div>
            </div>

            <!-- Day Headers -->
            <div class="calendar-header">
                <div class="cal-header-spacer"></div>
                @foreach($days as $day)
                <div class="cal-day-header {{ $day->isToday() ? 'today' : '' }}">
                    <div class="day-name">{{ $day->locale('es')->isoFormat('ddd') }}</div>
                    <div class="day-number">{{ $day->format('d') }}</div>
                </div>
                @endforeach
            </div>

            <!-- Time Grid -->
            <div class="calendar-body">
                <!-- Time gutter -->
                <div class="time-gutter">
                    @foreach($slots as $slot)
                        <div class="time-slot-label">{{ $slot->format('H:i') }}</div>
                    @endforeach
                </div>

                <!-- Days columns -->
                <div class="days-grid">
                    @foreach($days as $dayIdx => $day)
                    <div class="day-column" style="position:relative;">

                        <!-- Current time line (only on today's column) -->
                        <template x-if="showNowLine && '{{ $day->toDateString() }}' === new Date().toISOString().slice(0, 10)">
                            <div class="current-time-line" :style="`top: ${nowOffsetPct}%;`"></div>
                        </template>

                        <!-- Slots -->
                        @foreach($slots as $slotIdx => $slot)
                        @php
                            $slotDatetime = $day->copy()->setHour($slot->hour)->setMinute($slot->minute)->setSecond(0)->toIso8601String();
                        @endphp
                        <div class="day-slot"
                             @click="openCreate('{{ $day->toDateString() }}', '{{ $slot->format("H:i") }}')">
                        </div>
                        @endforeach

                        <!-- Event blocks -->
                        <template x-for="ev in eventsForDay('{{ $day->toDateString() }}')" :key="ev.id">
                            <div class="event-block"
                                 :style="eventStyle(ev)"
                                 @click.stop="openEdit(ev)">
                                <div class="event-title" x-text="ev.title"></div>
                                <div class="event-time" x-text="formatTime(ev.start_time) + ' – ' + formatTime(ev.end_time)"></div>
                            </div>
                        </template>

                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- ── AGENDA VIEW (KIOSK AGENDA SPLIT SCREEN) ── -->
    <div x-show="viewMode === 'agenda'" class="kiosk-agenda-container" style="display: flex; height: 100%; width: 100%;" x-cloak>
        <!-- Left Panel (40% width): Status & Room details -->
        <div class="kiosk-left-panel" :class="isOccupiedNow() ? 'occupied' : 'available'">
            <div class="kiosk-room-info">
                <span class="kiosk-room-label">SALA</span>
                <h1 class="kiosk-room-name">{{ $room->name }}</h1>
                <p class="kiosk-room-desc">{{ $room->description }}</p>
            </div>

            <div class="kiosk-status-card">
                <template x-if="isOccupiedNow()">
                    <div>
                        <span class="kiosk-status-badge occupied">OCUPADA</span>
                        <h2 class="kiosk-status-title" x-text="currentEvent() ? currentEvent().title : ''"></h2>
                        <p class="kiosk-status-meta" x-text="'Organiza: ' + (currentEvent() ? currentEvent().organizer : '')"></p>
                        <p class="kiosk-status-time" x-text="currentEvent() ? (formatTime(currentEvent().start_time) + ' - ' + formatTime(currentEvent().end_time)) : ''"></p>
                    </div>
                </template>
                <template x-if="!isOccupiedNow()">
                    <div>
                        <span class="kiosk-status-badge available">DISPONIBLE</span>
                        <h2 class="kiosk-status-title">Disponible</h2>
                        <template x-if="nextEvent()">
                            <p class="kiosk-status-meta" x-text="'Próxima reunión: ' + nextEvent().title + ' (' + formatTime(nextEvent().start_time) + ')'"></p>
                        </template>
                        <template x-if="!nextEvent()">
                            <p class="kiosk-status-meta">Libre por el resto del día</p>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Clock & Buttons -->
            <div class="kiosk-left-footer">
                <div class="kiosk-clock">
                    <span class="kiosk-clock-time" x-text="currentTime"></span>
                    <span class="kiosk-clock-date" x-text="currentDate"></span>
                </div>
                
                <div class="kiosk-actions">
                    <button class="btn btn-primary" @click="openCreate(null, null)" style="width: 100%; justify-content: center; font-weight: 600; padding: 12px 24px; font-size: 15px; background: #ffffff; color: #000000; border: none;">
                        ⚡ Reservar Sala
                    </button>
                    <div style="display: flex; gap: 8px; margin-top: 8px; width: 100%;">
                        <button class="btn btn-secondary" @click="viewMode = 'grid'" style="flex: 1; justify-content: center; font-size: 12px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); color: #ffffff;">
                            📅 Vista Semanal
                        </button>
                        @if(request()->has('kiosk') || request()->query('kiosk') === 'true')
                            <a href="?{{ http_build_query(request()->except('kiosk')) }}" class="btn btn-ghost" style="flex: 1; justify-content: center; font-size: 12px; border: 1px solid rgba(255,255,255,0.25); color: #ffffff; background: rgba(255,255,255,0.05);">
                                🚪 Salir
                            </a>
                        @else
                            <button class="btn btn-ghost" @click="viewMode = 'grid'" style="flex: 1; justify-content: center; font-size: 12px; border: 1px solid rgba(255,255,255,0.25); color: #ffffff; background: rgba(255,255,255,0.05);">
                                Volver
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel (60% width): Today's Schedule -->
        <div class="kiosk-right-panel">
            <div class="kiosk-agenda-header">
                <h3>Agenda de Hoy</h3>
                <span class="kiosk-agenda-today" x-text="currentDate"></span>
            </div>
            
            <div class="kiosk-timeline">
                <!-- Fetch events for today -->
                @php
                    $todayStr = \Carbon\Carbon::today()->toDateString();
                @endphp
                
                <div class="kiosk-events-list">
                    <template x-if="eventsForDay('{{ $todayStr }}').length === 0">
                        <div class="kiosk-no-events">
                            <div class="kiosk-no-events-icon">🎉</div>
                            <h4>Sala libre todo el día</h4>
                            <p>No hay reuniones programadas para hoy.</p>
                        </div>
                    </template>
                    
                    <template x-if="eventsForDay('{{ $todayStr }}').length > 0">
                        <div class="timeline-wrapper">
                            <template x-for="(ev, idx) in eventsForDay('{{ $todayStr }}')" :key="ev.id">
                                <div class="timeline-item" :class="isCurrentEvent(ev) ? 'active' : ''" @click="openEdit(ev)">
                                    <div class="timeline-badge" :style="`background: ${ev.color || roomColor}`">
                                        <div class="timeline-badge-inner" x-show="isCurrentEvent(ev)"></div>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-time-col">
                                            <span class="time-range" x-text="formatTime(ev.start_time) + ' - ' + formatTime(ev.end_time)"></span>
                                            <span class="duration" x-text="getDurationMinutes(ev.start_time, ev.end_time) + ' min'"></span>
                                        </div>
                                        <div class="timeline-details-col">
                                            <h4 class="event-title" x-text="ev.title"></h4>
                                            <p class="event-meta">
                                                <span class="organizer" x-text="'Organiza: ' + (ev.organizer || 'N/A')"></span>
                                            </p>
                                            <p class="event-desc" x-show="ev.description" x-text="ev.description"></p>
                                        </div>
                                        <div class="timeline-status-col">
                                            <span class="now-badge" x-show="isCurrentEvent(ev)">AHORA</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- ── CREATE / EDIT MODAL ── -->
    <div class="modal-backdrop" x-show="modalOpen" x-cloak @click.self="closeModal()"
         x-transition:enter="fadeIn" x-transition:leave="fadeOut">
        <div class="modal" @click.stop>
            <div class="modal-header">
                <span class="modal-title" x-text="editingId ? '✏️ Editar Evento' : '＋ Nuevo Evento'"></span>
                <button class="modal-close" @click="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Título *</label>
                    <input class="form-input" type="text" x-model="form.title" placeholder="Ej. Junta de planeación" />
                </div>
                <div class="form-group">
                    <label class="form-label">Responsable</label>
                    <input class="form-input" type="text" x-model="form.organizer" placeholder="Nombre del organizador" />
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Fecha</label>
                        <input class="form-input" type="date" x-model="form.date" />
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Hora inicio</label>
                        <input class="form-input" type="time" x-model="form.start_time" min="08:00" max="17:30" step="1800" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hora fin</label>
                        <input class="form-input" type="time" x-model="form.end_time" min="08:30" max="18:00" step="1800" />
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Recurrencia</label>
                        <select class="form-input" x-model="form.recurrence">
                            <option value="">Sin recurrencia</option>
                            <option value="daily">Diaria</option>
                            <option value="weekly">Semanal</option>
                        </select>
                    </div>
                    <div class="form-group" x-show="form.recurrence">
                        <label class="form-label">Hasta</label>
                        <input class="form-input" type="date" x-model="form.recurrence_end" />
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-input" rows="2" x-model="form.description" placeholder="Notas adicionales..."></textarea>
                </div>
                <div x-show="errorMsg" style="display:flex;gap:8px;align-items:flex-start;padding:10px 12px;border-radius:8px;font-size:12px;"
                     :style="isConflict ? 'background:rgba(251,146,60,0.12);border:1px solid rgba(251,146,60,0.35);color:#fb923c;' : 'background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#f87171;'">
                    <span x-text="isConflict ? '⚠️' : '✖'"></span>
                    <span x-text="errorMsg"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button x-show="editingId" class="btn btn-danger" @click="deleteEvent()">🗑 Eliminar</button>
                <button class="btn btn-ghost" @click="closeModal()">Cancelar</button>
                <button class="btn btn-primary" @click="saveEvent()" :disabled="saving">
                    <span x-show="!saving">Guardar</span>
                    <span x-show="saving">Guardando…</span>
                </button>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@section('scripts')
<script>
function calendarApp(roomId, roomColor, initialEvents, weekStartStr, weekEndStr) {
    return {
        roomId,
        roomColor,
        events: initialEvents || [],
        weekStartStr,
        weekEndStr,
        modalOpen: false,
        editingId: null,
        saving: false,
        errorMsg: '',
        isConflict: false,
        form: { title:'', organizer:'', date:'', start_time:'08:00', end_time:'09:00', recurrence:'', recurrence_end:'', description:'' },
        nowOffsetPct: 0,
        showNowLine: false,
        viewMode: (new URLSearchParams(window.location.search).get('kiosk') === 'true') ? 'agenda' : 'grid',
        currentTime: '',
        currentDate: '',

        init() {
            this.updateNowLine();
            this.tick();
            // Update clock every second
            setInterval(() => this.tick(), 1000);
            // Update current time line every minute
            setInterval(() => this.updateNowLine(), 60000);
            // Start polling every 30 seconds
            setInterval(() => this.fetchEvents(), 30000);
        },

        tick() {
            const now = new Date();
            this.currentTime = now.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: false });
            this.currentDate = now.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
        },

        isCurrentEvent(ev) {
            const now = new Date();
            const start = new Date(ev.start_time);
            const end = new Date(ev.end_time);
            return start <= now && end >= now;
        },

        getDurationMinutes(startStr, endStr) {
            const start = new Date(startStr);
            const end = new Date(endStr);
            return Math.round((end - start) / 60000);
        },

        updateNowLine() {
            const now = new Date();
            const nowMin = now.getHours() * 60 + now.getMinutes();
            const startLimit = 8 * 60;   // 8:00 AM
            const endLimit   = 18 * 60;  // 6:00 PM
            const totalMins  = endLimit - startLimit;
            if (nowMin >= startLimit && nowMin <= endLimit) {
                this.nowOffsetPct = ((nowMin - startLimit) / totalMins) * 100;
                this.showNowLine = true;
            } else {
                this.showNowLine = false;
            }
        },

        async fetchEvents() {
            try {
                const res = await fetch(`/api/events?room_id=${this.roomId}&week_start=${this.weekStartStr}&week_end=${this.weekEndStr}`);
                if (res.ok) {
                    this.events = await res.json();
                }
            } catch(e) {
                console.error("Error polling events:", e);
            }
        },

        // ── Room State Helpers ────────────────────────────────────────
        isOccupiedNow() {
            const now = new Date();
            return this.events.some(ev => {
                const start = new Date(ev.start_time);
                const end = new Date(ev.end_time);
                return start <= now && end >= now;
            });
        },

        currentEvent() {
            const now = new Date();
            return this.events.find(ev => {
                const start = new Date(ev.start_time);
                const end = new Date(ev.end_time);
                return start <= now && end >= now;
            });
        },

        nextEvent() {
            const now = new Date();
            const futureEvents = this.events.filter(ev => new Date(ev.start_time) > now);
            if (futureEvents.length === 0) return null;
            futureEvents.sort((a, b) => new Date(a.start_time) - new Date(b.start_time));
            return futureEvents[0];
        },

        // ── Helpers ──────────────────────────────────────────────────
        startMinutes() { return 8 * 60; },  // 8:00
        endMinutes()   { return 18 * 60; }, // 18:00
        slotHeight()   { return 60; },      // (no longer strictly needed for px calculations)

        computeEventLayouts(dayEvents) {
            if (!dayEvents || dayEvents.length === 0) return [];
            const sorted = [...dayEvents].sort((a, b) => {
                const aStart = new Date(a.start_time).getTime();
                const bStart = new Date(b.start_time).getTime();
                if (aStart !== bStart) return aStart - bStart;
                return new Date(a.end_time).getTime() - new Date(b.end_time).getTime();
            });

            const columns = [];
            sorted.forEach(event => {
                let placed = false;
                const evStart = new Date(event.start_time).getTime();
                for (let i = 0; i < columns.length; i++) {
                    const col = columns[i];
                    const lastEventInCol = col[col.length - 1];
                    const lastEnd = new Date(lastEventInCol.end_time).getTime();
                    if (evStart >= lastEnd) {
                        col.push(event);
                        placed = true;
                        break;
                    }
                }
                if (!placed) {
                    columns.push([event]);
                }
            });

            sorted.forEach(event => {
                const colIdx = columns.findIndex(col => col.includes(event));
                event.colIdx = colIdx;
            });

            let groups = [];
            sorted.forEach(event => {
                let addedToGroup = false;
                for (let group of groups) {
                    const overlaps = group.some(ge => {
                        const s1 = new Date(event.start_time).getTime();
                        const e1 = new Date(event.end_time).getTime();
                        const s2 = new Date(ge.start_time).getTime();
                        const e2 = new Date(ge.end_time).getTime();
                        return s1 < e2 && e1 > s2;
                    });
                    if (overlaps) {
                        group.push(event);
                        addedToGroup = true;
                        break;
                    }
                }
                if (!addedToGroup) {
                    groups.push([event]);
                }
            });

            sorted.forEach(event => {
                const group = groups.find(g => g.includes(event));
                const groupCols = [];
                group.forEach(ge => {
                    if (!groupCols.includes(ge.colIdx)) {
                        groupCols.push(ge.colIdx);
                    }
                });
                groupCols.sort((a, b) => a - b);
                const totalCols = groupCols.length;
                const positionInGroupCols = groupCols.indexOf(event.colIdx);
                event.widthPct = 100 / totalCols;
                event.leftPct = positionInGroupCols * event.widthPct;
            });

            return sorted;
        },

        eventsForDay(dateStr) {
            const dayEvents = this.events.filter(ev => {
                const d = new Date(ev.start_time);
                return d.toISOString().slice(0,10) === dateStr;
            });
            return this.computeEventLayouts(dayEvents);
        },

        eventStyle(ev) {
            const start = new Date(ev.start_time);
            const end   = new Date(ev.end_time);
            const startMin = start.getHours() * 60 + start.getMinutes();
            const endMin   = end.getHours()   * 60 + end.getMinutes();
            
            const startLimit = this.startMinutes();
            const endLimit   = this.endMinutes();
            const totalMins  = endLimit - startLimit;
            
            const offsetMin = Math.max(startMin - startLimit, 0);
            const duration  = Math.min(endMin - startMin, totalMins - offsetMin);
            
            const topPct    = (offsetMin / totalMins) * 100;
            const heightPct = Math.max((duration / totalMins) * 100, 3.5);
            const color    = ev.color || this.roomColor;
            
            const width = ev.widthPct !== undefined ? `width: calc(${ev.widthPct}% - 6px);` : 'width: calc(100% - 6px);';
            const left = ev.leftPct !== undefined ? `left: calc(${ev.leftPct}% + 3px);` : 'left: 3px;';
            
            return `top:${topPct}%; height:${heightPct}%; background:${color}22; border-color:${color}; color:${color}; right:auto; ${width} ${left}`;
        },

        formatTime(iso) {
            const d = new Date(iso);
            return d.toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit', hour12: false });
        },

        // ── Modal ────────────────────────────────────────────────────
        openCreate(dateStr, timeStr) {
            this.editingId = null;
            this.errorMsg  = '';
            this.isConflict = false;
            const today = dateStr || new Date().toISOString().slice(0,10);
            const time  = timeStr || '08:00';
            const [h, m] = time.split(':').map(Number);
            const endH = h + 1;
            const endM = m;
            this.form = {
                title:'', organizer:'', date: today,
                start_time: time,
                end_time: `${String(endH).padStart(2,'0')}:${String(endM).padStart(2,'0')}`,
                recurrence:'', recurrence_end:'', description:''
            };
            this.modalOpen = true;
        },

        openEdit(ev) {
            this.editingId = ev.id;
            this.errorMsg  = '';
            this.isConflict = false;
            const start = new Date(ev.start_time);
            const end   = new Date(ev.end_time);
            this.form = {
                title: ev.title,
                organizer: ev.organizer || '',
                date: start.toISOString().slice(0,10),
                start_time: `${String(start.getHours()).padStart(2,'0')}:${String(start.getMinutes()).padStart(2,'0')}`,
                end_time:   `${String(end.getHours()).padStart(2,'0')}:${String(end.getMinutes()).padStart(2,'0')}`,
                recurrence: ev.recurrence || '',
                recurrence_end: ev.recurrence_end || '',
                description: ev.description || '',
            };
            this.modalOpen = true;
        },

        closeModal() { this.modalOpen = false; },

        // ── CRUD ─────────────────────────────────────────────────────
        async saveEvent() {
            if (!this.form.title.trim()) { this.errorMsg = 'El título es requerido.'; this.isConflict = false; return; }
            if (!this.form.date)        { this.errorMsg = 'La fecha es requerida.';   this.isConflict = false; return; }
            this.saving = true; this.errorMsg = ''; this.isConflict = false;

            const payload = {
                room_id:        this.roomId,
                title:          this.form.title,
                organizer:      this.form.organizer,
                start_time:     `${this.form.date} ${this.form.start_time}:00`,
                end_time:       `${this.form.date} ${this.form.end_time}:00`,
                recurrence:     this.form.recurrence || null,
                recurrence_end: this.form.recurrence_end || null,
                description:    this.form.description,
            };

            const isEdit = this.editingId && !String(this.editingId).includes('_');
            const url    = isEdit ? `/api/events/${this.editingId}` : '/api/events';
            const method = isEdit ? 'PUT' : 'POST';

            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await res.json();

                if (res.status === 409) {
                    // Conflict detected
                    this.isConflict = true;
                    this.errorMsg   = data.message || 'Conflicto de horario detectado.';
                    return;
                }

                if (!res.ok) {
                    this.isConflict = false;
                    this.errorMsg = Object.values(data.errors || {})[0]?.[0] || data.message || 'Error al guardar';
                    return;
                }

                if (isEdit) {
                    const idx = this.events.findIndex(e => e.id === this.editingId);
                    if (idx !== -1) this.events.splice(idx, 1, data);
                } else {
                    this.events.push(data);
                }
                this.closeModal();
            } catch(e) { this.isConflict = false; this.errorMsg = 'Error de red. Intenta de nuevo.'; }
            finally { this.saving = false; }
        },

        async deleteEvent() {
            if (!confirm('¿Eliminar este evento?')) return;
            const realId = String(this.editingId).split('_')[0];
            const res = await fetch(`/api/events/${realId}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            if (res.ok) {
                this.events = this.events.filter(e => !String(e.id).startsWith(realId));
                this.closeModal();
            }
        },
    };
}
</script>
@endsection
