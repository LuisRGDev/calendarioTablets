<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $weekStart = $request->query('week')
            ? Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $weekEnd = $weekStart->copy()->addDays(4)->endOfDay();

        $rooms = Room::with(['events' => function ($q) use ($weekStart, $weekEnd) {
            $q->where(function ($inner) use ($weekStart, $weekEnd) {
                $inner->whereBetween('start_time', [$weekStart, $weekEnd])
                      ->orWhere(function ($q2) use ($weekStart, $weekEnd) {
                          $q2->whereNotNull('recurrence')
                             ->where('start_time', '<=', $weekEnd)
                             ->where(function ($q3) use ($weekEnd) {
                                 $q3->whereNull('recurrence_end')
                                    ->orWhere('recurrence_end', '>=', $weekEnd->toDateString());
                             });
                      });
            });
        }])->get();

        foreach ($rooms as $room) {
            $expanded = \App\Models\Event::expandRecurringEvents($room->events, $weekStart, $weekEnd);
            $room->setRelation('events', collect($expanded));
        }

        $days = [];
        for ($i = 0; $i < 5; $i++) {
            $days[] = $weekStart->copy()->addDays($i);
        }

        return view('admin.dashboard', compact('rooms', 'weekStart', 'weekEnd', 'days'));
    }
}
