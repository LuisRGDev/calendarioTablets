<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    protected $fillable = [
        'room_id', 'title', 'description', 'organizer',
        'color', 'start_time', 'end_time', 'recurrence', 'recurrence_end',
    ];

    protected $casts = [
        'start_time'     => 'datetime',
        'end_time'       => 'datetime',
        'recurrence_end' => 'date',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Expand recurring events for a given week range.
     */
    public static function expandRecurringEvents($events, $weekStart, $weekEnd): array
    {
        $expanded = [];
        $weekStart = \Carbon\Carbon::parse($weekStart);
        $weekEnd = \Carbon\Carbon::parse($weekEnd);

        foreach ($events as $event) {
            if (!$event->recurrence) {
                if ($event->start_time->between($weekStart, $weekEnd)) {
                    $expanded[] = $event;
                }
                continue;
            }

            $current = $weekStart->copy();
            while ($current->lte($weekEnd)) {
                $originalDay  = $event->start_time->dayOfWeek;
                $matches = false;

                if ($event->recurrence === 'daily') {
                    $matches = $current->gte($event->start_time->startOfDay());
                } elseif ($event->recurrence === 'weekly') {
                    $matches = ($current->dayOfWeek === $originalDay)
                             && $current->gte($event->start_time->startOfDay());
                }

                if ($matches) {
                    if ($event->recurrence_end && $current->gt($event->recurrence_end)) {
                        $current->addDay();
                        continue;
                    }

                    $clone = clone $event;
                    $diff  = $event->start_time->diffInSeconds($event->end_time);
                    $clone->start_time = $current->copy()->setTimeFrom($event->start_time);
                    $clone->end_time   = $clone->start_time->copy()->addSeconds($diff);
                    $clone->id         = $event->id . '_' . $current->toDateString(); // virtual id
                    $expanded[] = $clone;
                }

                $current->addDay();
            }
        }

        return $expanded;
    }
}
