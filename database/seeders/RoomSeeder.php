<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            ['name' => 'TOTVS',          'slug' => 'totvs',          'color' => '#4f8ef7', 'description' => 'Sala de reuniones TOTVS'],
            ['name' => 'RESIDENCIAL',     'slug' => 'residencial',    'color' => '#9b59f7', 'description' => 'Sala Residencial'],
            ['name' => 'MIDDLEBY',        'slug' => 'middleby',       'color' => '#2ecc71', 'description' => 'Sala Middleby'],
            ['name' => 'TRAINING CENTER', 'slug' => 'training-center','color' => '#f7a94f', 'description' => 'Centro de capacitación'],
        ];

        foreach ($rooms as $room) {
            Room::updateOrCreate(['slug' => $room['slug']], $room);
        }
    }
}
