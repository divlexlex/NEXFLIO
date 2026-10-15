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
    | 'tiers'      — accommodation -> amount. Package variants map
    |                Solo->Single, Couple->Couple, Barkada->Barkada; a
    |                non-variant booking maps guests 1->Single, 2->Couple,
    |                3+->Barkada. The fee is min(tier amount, booked price), so
    |                it can never exceed the price.
    | 'flat_fees'  — exact service name -> flat amount. A listed service
    |                always pays min(flat amount, booked price) with no guest
    |                count involved (checked first in ReservationFee::for()).
    |
    */
    'reservation_fee' => [
        'categories' => ['Facial', 'Massage', 'Nails', 'Lashes & Brows', 'Aesthetics', 'Head Spa', 'Home Service'],
        'tiers' => [
            'Single' => 300,
            'Couple' => 500,
            'Barkada' => 2000,
        ],
        'flat_fees' => [
            'Kiddie Japanese Head Spa' => 300,
        ],
    ],

];
