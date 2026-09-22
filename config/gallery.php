<?php

/*
|--------------------------------------------------------------------------
| Guest Gallery Page — TEMPORARY frontend-only content
|--------------------------------------------------------------------------
|
| There is no gallery/media table in the database, so this is the whole
| source for the public Gallery page. Categories match real Perfect Nails
| service categories already used on the Services page (Nails, Facial,
| Massage, Head Spa, Aesthetics) — no category invented that isn't an
| actual offered treatment type.
|
| Images live in public/images/gallery/ (kept out of storage/app so they
| are never confused with real, DB-tracked service/promo uploads). None
| are captioned as a specific real client — captions describe the
| treatment type only.
|
| FUTURE: once Admin-managed gallery/media content exists, replace the
| config('gallery.categories') call in PageController@gallery with a real
| query and delete this file.
|
*/

return [
    'categories' => [
        [
            'name' => 'Nails',
            'images' => [
                ['file' => 'images/hero-slider/nail-care.jpg', 'alt' => 'Gel manicure application at Perfect Nails'],
                ['file' => 'images/gallery/nails-pedicure.jpg', 'alt' => 'Pedicure treatment in progress at Perfect Nails'],
            ],
        ],
        [
            'name' => 'Facial',
            'images' => [
                ['file' => 'images/hero-slider/facial-treatment.jpg', 'alt' => 'Facial treatment at Perfect Nails'],
            ],
        ],
        [
            'name' => 'Massage',
            'images' => [
                ['file' => 'images/hero-slider/massage-wellness.jpg', 'alt' => 'Relaxing massage therapy at Perfect Nails'],
                ['file' => 'images/gallery/massage-hot-stone.jpg', 'alt' => 'Hot stone massage at Perfect Nails'],
            ],
        ],
        [
            'name' => 'Head Spa',
            'images' => [
                ['file' => 'images/gallery/headspa-rinse-1.jpg', 'alt' => 'Japanese head spa scalp rinse at Perfect Nails'],
                ['file' => 'images/gallery/headspa-rinse-2.jpg', 'alt' => 'Head spa treatment at Perfect Nails'],
            ],
        ],
        [
            'name' => 'Aesthetics',
            'images' => [
                ['file' => 'images/gallery/aesthetics-led-1.jpg', 'alt' => 'LED light therapy aesthetic treatment at Perfect Nails'],
                ['file' => 'images/gallery/aesthetics-led-2.jpg', 'alt' => 'Aesthetic light therapy treatment at Perfect Nails'],
            ],
        ],
    ],
];
