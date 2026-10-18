<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reservation Fee (Down Payment)
    |--------------------------------------------------------------------------
    |
    | EVERY service requires a down payment (the "reservation fee") at booking
    | time; the remaining balance is settled at the branch. This is the
    | single source of truth for the tier amounts — every fee calculation
    | reads it (App\Support\ReservationFee), never hardcoded in a blade or
    | controller.
    |
    | 'categories' — the canonical category set (mirrors
    |                App\Support\ServiceCatalog::CATEGORY_ORDER), kept for
    |                reference. The fee itself applies to every service with
    |                no exemption — see ReservationFee::appliesTo().
    | 'tiers'      — accommodation -> amount for PACKAGE variants:
    |                Solo->Single, Couple->Couple, Barkada->Barkada. The fee
    |                is min(tier amount, booked price), so it can never
    |                exceed the price. Variant-less bookings instead follow
    |                the price rule in App\Support\ReservationFee (500+ pays
    |                a flat 300, below 500 pays exactly half).
    |
    */
    'reservation_fee' => [
        'categories' => ['Facial', 'Massage', 'Nails', 'Lashes & Brows', 'Aesthetics', 'Head Spa', 'Home Service'],
        'tiers' => [
            'Single' => 300,
            'Couple' => 500,
            'Barkada' => 2000,
        ],
    ],

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
            'categories' => ['Facial', 'Aesthetics'],
        ],
    ],

];
