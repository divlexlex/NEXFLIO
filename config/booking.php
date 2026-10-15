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

];
