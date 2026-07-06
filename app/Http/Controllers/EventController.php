<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Event::with('room');

        if ($request->room_id) {
            $query->where('room_id', $request->room_id);
        }

        $weekStart = $request->week_start;
        $weekEnd = $request->week_end;

        if ($weekStart && $weekEnd) {
            $query->where(function ($q) use ($weekStart, $weekEnd) {
                $q->whereBetween('start_time', [$weekStart, $weekEnd])
                  ->orWhere(function ($q2) use ($weekStart, $weekEnd) {
                      $q2->whereNotNull('recurrence')
                         ->where('start_time', '<=', $weekEnd)
                         ->where(function ($q3) use ($weekEnd) {
                             $q3->whereNull('recurrence_end')
                                ->orWhere('recurrence_end', '>=', $weekEnd);
                         });
                  });
            });
        }

        $events = $query->orderBy('start_time')->get();

        if ($weekStart && $weekEnd) {
            $expanded = Event::expandRecurringEvents($events, $weekStart, $weekEnd);
            $formatted = collect($expanded)->map(fn($e) => [
                'id'         => $e->id,
                'room_id'    => $e->room_id,
                'title'      => $e->title,
                'organizer'  => $e->organizer,
                'description'=> $e->description,
                'start_time' => $e->start_time->toIso8601String(),
                'end_time'   => $e->end_time->toIso8601String(),
                'recurrence' => $e->recurrence,
                'recurrence_end' => $e->recurrence_end?->toDateString(),
                'color'      => $e->color,
            ]);
            return response()->json($formatted);
        }

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id'        => 'required|exists:rooms,id',
            'title'          => 'required|string|max:200',
            'description'    => 'nullable|string',
            'organizer'      => 'nullable|string|max:100',
            'start_time'     => 'required|date',
            'end_time'       => 'required|date|after:start_time',
            'recurrence'     => 'nullable|in:daily,weekly',
            'recurrence_end' => 'nullable|date|after:start_time',
            'color'          => 'nullable|string|max:20',
        ]);

        // ── Conflict detection ──────────────────────────────────────
        $conflict = $this->findConflict(
            roomId:    $validated['room_id'],
            startTime: $validated['start_time'],
            endTime:   $validated['end_time'],
            excludeId: null,
        );

        if ($conflict) {
            return $this->conflictResponse($conflict);
        }

        $event = Event::create($validated);

        return response()->json($event->load('room'), 201);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'room_id'        => 'sometimes|exists:rooms,id',
            'title'          => 'sometimes|string|max:200',
            'description'    => 'nullable|string',
            'organizer'      => 'nullable|string|max:100',
            'start_time'     => 'sometimes|date',
            'end_time'       => 'sometimes|date',
            'recurrence'     => 'nullable|in:daily,weekly',
            'recurrence_end' => 'nullable|date',
            'color'          => 'nullable|string|max:20',
        ]);

        // ── Conflict detection (exclude self) ───────────────────────
        $roomId    = $validated['room_id']    ?? $event->room_id;
        $startTime = $validated['start_time'] ?? $event->start_time;
        $endTime   = $validated['end_time']   ?? $event->end_time;

        $conflict = $this->findConflict(
            roomId:    $roomId,
            startTime: $startTime,
            endTime:   $endTime,
            excludeId: $event->id,
        );

        if ($conflict) {
            return $this->conflictResponse($conflict);
        }

        $event->update($validated);

        return response()->json($event->load('room'));
    }

    public function destroy(Event $event): JsonResponse
    {
        $event->delete();
        return response()->json(['message' => 'Evento eliminado']);
    }

    // ── Private helpers ─────────────────────────────────────────────

    /**
     * Find an overlapping event in the same room.
     * Overlap condition: existing.start < new.end  AND  existing.end > new.start
     */
    private function findConflict(int|string $roomId, $startTime, $endTime, ?int $excludeId): ?Event
    {
        $query = Event::where('room_id', $roomId)
            ->where('start_time', '<', $endTime)
            ->where('end_time',   '>', $startTime);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

    /**
     * Build a user-friendly 409 Conflict JSON response.
     */
    private function conflictResponse(Event $conflict): JsonResponse
    {
        $start = Carbon::parse($conflict->start_time)->format('H:i');
        $end   = Carbon::parse($conflict->end_time)->format('H:i');
        $date  = Carbon::parse($conflict->start_time)->translatedFormat('l d \d\e F');

        return response()->json([
            'conflict' => true,
            'message'  => "Este horario ya está ocupado por «{$conflict->title}» ({$date}, {$start}–{$end}).",
            'event'    => [
                'id'         => $conflict->id,
                'title'      => $conflict->title,
                'organizer'  => $conflict->organizer,
                'start_time' => $conflict->start_time,
                'end_time'   => $conflict->end_time,
            ],
        ], 409);
    }
}
