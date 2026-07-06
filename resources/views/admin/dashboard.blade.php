@extends('layouts.app')

@section('title', 'Panel de Administración')

@section('content')
@php
    $startHour = 8; $startMin = 30;
    $endHour = 17;  $endMin = 30;
    $slots = [];
    $t = \Carbon\Carbon::today()->setHour($startHour)->setMinute($startMin)->setSecond(0);
    $end = \Carbon\Carbon::today()->setHour($endHour)->setMinute($endMin)->setSecond(0);
    while ($t->lte($end)) { $slots[] = $t->copy(); $t->addMinutes(30); }

    $prevWeek = $weekStart->copy()->subWeek()->toDateString();
    $nextWeek = $weekStart->copy()->addWeek()->toDateString();
    $todayWeek = \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
    $slotHeight = 30; // px per 30min in mini view
@endphp

<!-- Admin toolbar -->
<div class="flex items-center gap-2" style="padding: 14px 20px; border-bottom: 1px solid var(--border); background: var(--bg-surface);">
    <span class="page-title">🏢 Panel de Administración</span>

    <div class="week-nav" style="margin-left: 16px;">
        <a href="?week={{ $prevWeek }}" class="btn-icon">‹</a>
        <span class="week-nav-label">
            {{ $weekStart->translatedFormat('d M') }} – {{ $weekEnd->translatedFormat('d M Y') }}
        </span>
        <a href="?week={{ $nextWeek }}" class="btn-icon">›</a>
        @if($weekStart->toDateString() !== $todayWeek)
            <a href="?week={{ $todayWeek }}" class="btn-today">Hoy</a>
        @endif
    </div>

    <div class="flex gap-2 ml-auto" style="font-size:11px; color: var(--text-muted);">
        <span style="display:flex;align-items:center;gap:4px;">
            <span style="width:8px;height:8px;border-radius:50%;background:#2ecc71;display:inline-block;"></span> Disponible
        </span>
        <span style="display:flex;align-items:center;gap:4px;">
            <span style="width:8px;height:8px;border-radius:50%;background:#ef4444;display:inline-block;"></span> Ocupada
        </span>
    </div>
</div>

<!-- 4-room grid -->
<div class="admin-grid"
     x-data="adminApp({{ json_encode($rooms->map(fn($r) => [
         'id'    => $r->id,
         'name'  => $r->name,
         'slug'  => $r->slug,
         'color' => $r->color,
         'events'=> $r->events->map(fn($e) => [
             'id'         => $e->id,
             'title'      => $e->title,
             'start_time' => $e->start_time->toIso8601String(),
             'end_time'   => $e->end_time->toIso8601String(),
             'color'      => $e->color,
         ])->values(),
     ])->values()) }}, '{{ $weekStart->toDateString() }}', '{{ $weekEnd->toDateString() }}')">

    <template x-for="room in rooms" :key="room.id">
        <div class="admin-room-card">
            <!-- Card header -->
            <div class="admin-room-card-header">
                <div class="admin-room-dot" :style="`background:${room.color}`"></div>
                <span class="admin-room-name" x-text="room.name"></span>

                <!-- Status badge -->
                <span :class="isOccupied(room) ? 'status-badge busy' : 'status-badge free'"
                      style="font-size:10px; padding: 3px 8px;">
                    <span class="status-dot"></span>
                    <span x-text="isOccupied(room) ? 'Ocupada' : 'Libre'"></span>
                </span>

                <!-- Link to tablet view -->
                <a :href="`/room/${room.slug}`" class="admin-room-link" target="_blank">↗ Ver sala</a>
            </div>

            <!-- Mini Calendar -->
            <div class="mini-cal">
                <!-- Day names header -->
                <div class="mini-cal-header">
                    @foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d)
                    <div class="mini-cal-day-name">{{ $d }}</div>
                    @endforeach
                </div>

                <!-- Body -->
                <div class="mini-cal-body">
                    <!-- Time gutter -->
                    <div class="mini-time-gutter">
                        @foreach($slots as $slot)
                            <div class="mini-time-label">{{ $slot->format('H:i') }}</div>
                        @endforeach
                    </div>

                    <!-- Columns -->
                    <div class="mini-days-grid">
                        @foreach($days as $dayIdx => $day)
                        <div class="mini-day-col" style="position:relative;">
                            @foreach($slots as $slotIdx => $slot)
                            @php
                                $dt = $day->copy()->setHour($slot->hour)->setMinute($slot->minute)->toIso8601String();
                            @endphp
                            <div class="mini-slot"
                                 @click="openAdminCreate(room, '{{ $day->toDateString() }}', '{{ $slot->format('H:i') }}')">
                            </div>
                            @endforeach

                            <!-- Event blocks -->
                            <template x-for="ev in eventsForDay(room, '{{ $day->toDateString() }}')" :key="ev.id">
                                <div class="mini-event-block"
                                     :style="miniEventStyle(ev, room)"
                                     @click.stop="openAdminEdit(room, ev)">
                                    <span x-text="ev.title"></span>
                                </div>
                            </template>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- ── ADMIN CREATE / EDIT MODAL ── -->
    <div class="modal-backdrop" x-show="modalOpen" x-cloak @click.self="closeModal()"
         x-transition>
        <div class="modal" @click.stop>
            <div class="modal-header">
                <span class="modal-title" x-text="editingId ? '✏️ Editar Evento' : '＋ Nuevo Evento'"></span>
                <button class="modal-close" @click="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Sala</label>
                    <select class="form-input" x-model="form.room_id">
                        <template x-for="r in rooms" :key="r.id">
                            <option :value="r.id" x-text="r.name"></option>
                        </template>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Título *</label>
                    <input class="form-input" type="text" x-model="form.title" placeholder="Ej. Junta mensual" />
                </div>
                <div class="form-group">
                    <label class="form-label">Responsable</label>
                    <input class="form-input" type="text" x-model="form.organizer" />
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
                        <input class="form-input" type="time" x-model="form.start_time" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hora fin</label>
                        <input class="form-input" type="time" x-model="form.end_time" />
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
                    <textarea class="form-input" rows="2" x-model="form.description"></textarea>
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
@endsection

@section('scripts')
<script>
function adminApp(rooms, weekStartStr, weekEndStr) {
    return {
        rooms,
        weekStartStr,
        weekEndStr,
        modalOpen: false,
        editingId: null,
        activeRoomId: null,
        saving: false,
        errorMsg: '',
        isConflict: false,
        form: { room_id:'', title:'', organizer:'', date:'', start_time:'08:30', end_time:'09:30', recurrence:'', recurrence_end:'', description:'' },

        init() {
            // Polling every 30 seconds
            setInterval(() => this.fetchEvents(), 30000);
        },

        async fetchEvents() {
            try {
                const res = await fetch(`/api/events?week_start=${this.weekStartStr}&week_end=${this.weekEndStr}`);
                if (res.ok) {
                    const data = await res.json();
                    this.rooms.forEach(room => {
                        room.events = data.filter(e => e.room_id == room.id);
                    });
                }
            } catch(e) {
                console.error("Error polling events:", e);
            }
        },

        startMinutes() { return 8 * 60 + 30; },
        slotHeight()   { return 30; },

        isOccupied(room) {
            const now = new Date();
            return room.events.some(ev => {
                const s = new Date(ev.start_time), e = new Date(ev.end_time);
                return s <= now && e >= now;
            });
        },

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

        eventsForDay(room, dateStr) {
            const dayEvents = room.events.filter(ev => {
                const d = new Date(ev.start_time);
                return d.toISOString().slice(0,10) === dateStr;
            });
            return this.computeEventLayouts(dayEvents);
        },

        miniEventStyle(ev, room) {
            const start = new Date(ev.start_time), end = new Date(ev.end_time);
            const startMin = start.getHours() * 60 + start.getMinutes();
            const endMin   = end.getHours()   * 60 + end.getMinutes();
            const topPx    = (startMin - this.startMinutes()) * (this.slotHeight() / 30);
            const heightPx = Math.max((endMin - startMin) * (this.slotHeight() / 30), 14);
            const color    = ev.color || room.color;
            
            const width = ev.widthPct !== undefined ? `width: calc(${ev.widthPct}% - 2px);` : 'width: calc(100% - 2px);';
            const left = ev.leftPct !== undefined ? `left: calc(${ev.leftPct}% + 1px);` : 'left: 1px;';
            
            return `top:${topPx}px; height:${heightPx}px; background:${color}25; border-color:${color}; color:${color}; right:auto; ${width} ${left}`;
        },

        openAdminCreate(room, dateStr, timeStr) {
            this.editingId = null; this.errorMsg = ''; this.isConflict = false;
            this.activeRoomId = room.id;
            const [h, m] = timeStr.split(':').map(Number);
            const endH = h + Math.floor((m + 60) / 60);
            const endM = (m + 60) % 60;
            this.form = {
                room_id: room.id, title:'', organizer:'', date: dateStr,
                start_time: timeStr,
                end_time: `${String(endH).padStart(2,'0')}:${String(endM).padStart(2,'0')}`,
                recurrence:'', recurrence_end:'', description:''
            };
            this.modalOpen = true;
        },

        openAdminEdit(room, ev) {
            this.editingId = ev.id; this.activeRoomId = room.id; this.errorMsg = ''; this.isConflict = false;
            const start = new Date(ev.start_time), end = new Date(ev.end_time);
            this.form = {
                room_id: room.id, title: ev.title, organizer: ev.organizer || '',
                date: start.toISOString().slice(0,10),
                start_time: `${String(start.getHours()).padStart(2,'0')}:${String(start.getMinutes()).padStart(2,'0')}`,
                end_time:   `${String(end.getHours()).padStart(2,'0')}:${String(end.getMinutes()).padStart(2,'0')}`,
                recurrence: ev.recurrence || '', recurrence_end: ev.recurrence_end || '', description: ev.description || '',
            };
            this.modalOpen = true;
        },

        closeModal() { this.modalOpen = false; },

        async saveEvent() {
            if (!this.form.title.trim()) { this.errorMsg = 'El título es requerido.'; this.isConflict = false; return; }
            this.saving = true; this.errorMsg = ''; this.isConflict = false;
            const payload = {
                room_id: this.form.room_id, title: this.form.title, organizer: this.form.organizer,
                start_time: `${this.form.date} ${this.form.start_time}:00`,
                end_time:   `${this.form.date} ${this.form.end_time}:00`,
                recurrence: this.form.recurrence || null,
                recurrence_end: this.form.recurrence_end || null,
                description: this.form.description,
            };
            const isEdit = this.editingId && !String(this.editingId).includes('_');
            const url    = isEdit ? `/api/events/${this.editingId}` : '/api/events';
            const method = isEdit ? 'PUT' : 'POST';
            try {
                const res = await fetch(url, {
                    method,
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept':'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (res.status === 409) {
                    this.isConflict = true;
                    this.errorMsg   = data.message || 'Conflicto de horario detectado.';
                    return;
                }

                if (!res.ok) {
                    this.isConflict = false;
                    this.errorMsg = Object.values(data.errors || {})[0]?.[0] || data.message || 'Error al guardar';
                    return;
                }

                const room = this.rooms.find(r => r.id == this.form.room_id);
                if (room) {
                    if (isEdit) {
                        const idx = room.events.findIndex(e => e.id === this.editingId);
                        if (idx !== -1) room.events.splice(idx, 1, data);
                    } else {
                        room.events.push(data);
                    }
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
                const room = this.rooms.find(r => r.id == this.activeRoomId);
                if (room) room.events = room.events.filter(e => !String(e.id).startsWith(realId));
                this.closeModal();
            }
        },
    };
}
</script>
@endsection
