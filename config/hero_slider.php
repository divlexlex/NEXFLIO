<?php

/*
|--------------------------------------------------------------------------
| Guest Home — Hero Slider (TEMPORARY frontend-only content)
|--------------------------------------------------------------------------
|
| These slides are NOT stored in the database and are unrelated to the
| Service/Promo models — purely presentational placeholder imagery for the
| Guest Home hero while the Website redesign is in progress. Nothing here
| touches bookings, the admin panel, or any other business logic.
|
| Images live in public/images/hero-slider/ (kept out of storage/app so
| they are never confused with real, DB-tracked service/promo uploads).
|
| FUTURE: once Admin-managed Home content ships, replace the
| config('hero_slider.slides') call in LandingController@index with a
| query against the new slider model/table, keeping the same
| ['image' => ..., 'alt' => ...] shape so
| resources/views/partials/hero-slider.blade.php does not need to change.
|
*/

return [
    'slides' => [
        [
            'image' => 'images/hero-slider/nail-care.jpg',
            'alt' => 'Nail technician carefully finishing a gel manicure at Perfect Nails',
        ],
        [
            'image' => 'images/hero-slider/facial-treatment.jpg',
            'alt' => 'Client relaxing during a warm facial treatment at Perfect Nails',
        ],
        [
            'image' => 'images/hero-slider/massage-wellness.jpg',
            'alt' => 'Therapist performing a relaxing wellness massage at Perfect Nails',
        ],
    ],
];
