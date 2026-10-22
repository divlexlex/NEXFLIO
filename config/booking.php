<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cancellation Reason Categories (client side)
    |--------------------------------------------------------------------------
    |
    | Structured reason a client picks when requesting a cancellation
    | (the free-text details stay alongside it in the request's `reason`
    | column). Single source of truth for the client <select> and the admin
    | Change Requests Reason column — never hardcoded in a blade. Follows
    | the config/payment.php rejection_reasons pattern.
    |
    */

    'cancellation_reasons' => [
        'change_of_plans' => 'Change of plans',
        'schedule_conflict' => 'Schedule conflict',
        'personal_emergency' => 'Personal or family emergency',
        'health_reasons' => 'Health reasons',
        'booked_by_mistake' => 'Booked by mistake',
        'duplicate_booking' => 'Duplicate booking',
        'price_concerns' => 'Price concerns',
        'travel_issues' => 'Travel or transportation issues',
        'other' => 'Other (specify)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Concurrent Capacity (branch resources)
    |--------------------------------------------------------------------------
    |
    | How many clients may occupy the same resource pool at overlapping times.
    | Enforced when building the booking slot grid and on every store path
    | (web branch/home, API, walk-in). Categories listed under one pool share
    | that pool's limit (Facial + Aesthetics share the single esthetics room).
    |
    */
    'concurrent_capacity' => [
        'massage' => [
            'label' => 'massage beds',
            'limit' => 2,
            'categories' => ['Massage'],
        ],
        'head_spa' => [
            'label' => 'head spa beds',
            'limit' => 4,
            'categories' => ['Head Spa'],
        ],
        'esthetics' => [
            'label' => 'esthetics rooms',
            'limit' => 1,
            'categories' => ['Aesthetics'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Walk-in Closing Time
    |--------------------------------------------------------------------------
    |
    | Latest clock time a counter walk-in service may finish, relative to the
    | appointment date. When this is at or before that day's open (default
    | 01:00 with a noon/morning open), it means the following calendar
    | morning — so a 2-hour massage may start at 11:00 PM and end at 1:00 AM.
    |
    | ONLINE bookings ignore this entirely; they still use
    | BusinessHours::startFitsOnDate() / the shop close_at columns.
    |
    */
    'walk_in' => [
        'close_at' => env('WALK_IN_CLOSE_AT', '01:00'),
    ],

];
