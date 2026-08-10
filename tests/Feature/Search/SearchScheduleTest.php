<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('schedules the search fetch command to run daily', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains($event->command ?? '', 'search:fetch'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *');
});
