<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reservation Fee (Down Payment)
    |--------------------------------------------------------------------------
    |
    | Fee-category services require a down payment (the "reservation fee") at
    | booking time; the remaining balance is settled at the branch. This is the
    | single source of truth for both the category list and the tier amounts —
    | every fee calculation reads it (App\Support\ReservationFee), never
    | hardcoded in a blade or controller.
    |
    | 'categories' — only services whose category is listed are fee services.
    | 'tiers'      — accommodation -> amount. Package variants map
    |                Solo->Single, Couple->Couple, Barkada->Barkada; a
    |                non-variant booking maps guests 1->Single, 2->Couple,
    |                3+->Barkada. The fee is min(tier amount, booked price), so
    |                it can never exceed the price.
    |
    */
    'reservation_fee' => [
        'categories' => ['Massage', 'Head Spa'],
        'tiers' => [
            'Single' => 300,
            'Couple' => 500,
            'Barkada' => 2000,
        ],
    ],

];
