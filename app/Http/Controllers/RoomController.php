<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RoomController extends Controller
{
    public function show(Room $room, Request $request)
    {
        // Week navigation
        $weekStart = $request->query('week')
            ? Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $weekEnd = $weekStart->copy()->addDays(4)->endOfDay();

        // Load events for the week (including recurring ones expanded)
        $baseEvents = $room->events()
            ->where(function ($q) use ($weekStart, $weekEnd) {
                $q->whereBetween('start_time', [$weekStart, $weekEnd])
                  ->orWhere(function ($q2) use ($weekStart, $weekEnd) {
                      $q2->whereNotNull('recurrence')
                         ->where('start_time', '<=', $weekEnd)
                         ->where(function ($q3) use ($weekEnd) {
                             $q3->whereNull('recurrence_end')
                                ->orWhere('recurrence_end', '>=', $weekEnd->toDateString());
                         });
                  });
            })
            ->get();

        $events = collect(\App\Models\Event::expandRecurringEvents($baseEvents, $weekStart, $weekEnd));

        $days = [];
        for ($i = 0; $i < 5; $i++) {
            $days[] = $weekStart->copy()->addDays($i);
        }

        return view('room.show', compact('room', 'events', 'weekStart', 'weekEnd', 'days'));
    }
}
