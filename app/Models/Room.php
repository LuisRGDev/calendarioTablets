<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'description'];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Check if the room is currently occupied.
     */
    public function isOccupiedNow(): bool
    {
        $now = now();
        return $this->events()
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->exists();
    }

    /**
     * Get the current or next event.
     */
    public function currentEvent()
    {
        $now = now();
        return $this->events()
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->first();
    }

    /**
     * Get the next upcoming event.
     */
    public function nextEvent()
    {
        return $this->events()
            ->where('start_time', '>', now())
            ->orderBy('start_time')
            ->first();
    }
}
