<?php

/*
|--------------------------------------------------------------------------
| Guest Packages Page — TEMPORARY frontend-only content
|--------------------------------------------------------------------------
|
| The Promo table is currently empty, so the dedicated /packages page has
| nothing real to render yet. This is the same situation Services solved
| with App\Support\ServiceCatalog — here there's no DB data to merge with
| at all, so this file is the whole source for now.
|
| Content is sourced verbatim from Perfect Nails' own approved Japanese
| Head Spa package flyers in NEXFLIO-FIGMA-REFERENCE — names, tier prices,
| and inclusions are copied as printed, nothing invented. The "Most Booked"
| badge on Perfect Package matches the badge already used on this same
| package in the Figma Website Home preview — not invented here either.
|
| These packages are NEVER written to MySQL and carry no real Promo id.
| Their booking CTA (partials/booking-cta.blade.php) never routes to a
| promo/service id — same safe pattern as the temporary Services content.
|
| FUTURE: once Admin-managed Promo/package content exists, replace the
| config('packages.items') call in PageController@packages with a real
| Promo query and delete this file.
|
*/

return [
    'tagline' => 'Experience the ultimate in relaxation and scalp care. Our Japanese Head Spa treatments combine deep cleansing, therapeutic massage, and advanced scalp therapy to revitalize your hair, mind, and body.',

    'disclaimer' => 'Prices listed may change due to cost or market conditions. We appreciate your understanding and respect.',

    'featured' => [
        'name' => 'Perfect Package',
        'badge' => 'Most Booked',
        'tiers' => [
            ['label' => 'Solo', 'price' => 2599],
            ['label' => 'Couple', 'price' => 4999],
            ['label' => 'Barkada', 'price' => 9799],
        ],
        'inclusions' => [
            '30 mins Japanese Head Spa',
            'Facial Treatment',
            'Pedicure',
            'Foot Spa',
            '1 hour Whole Body Massage',
            'w/ Body Scrub',
        ],
    ],

    'items' => [
        [
            'name' => 'Package EXA',
            'tiers' => [
                ['label' => 'Solo', 'price' => 999],
                ['label' => 'Couple', 'price' => 1699],
                ['label' => 'Barkada', 'price' => 3299],
            ],
            'inclusions' => [
                '30 mins Japanese Head Spa',
                'Facial Treatment',
            ],
        ],
        [
            'name' => 'Package DEE',
            'tiers' => [
                ['label' => 'Solo', 'price' => 1599],
                ['label' => 'Couple', 'price' => 2999],
                ['label' => 'Barkada', 'price' => 5899],
            ],
            'inclusions' => [
                '30 mins Japanese Head Spa',
                'Facial Treatment',
                '1 hour Whole Body Massage',
            ],
        ],
        [
            'name' => 'Package SAM',
            'tiers' => [
                ['label' => 'Solo', 'price' => 2299],
                ['label' => 'Couple', 'price' => 4399],
                ['label' => 'Barkada', 'price' => 8599],
            ],
            'inclusions' => [
                '30 mins Japanese Head Spa',
                'Facial Treatment',
                '1 hour Whole Body Massage',
                'w/ Body Scrub',
            ],
        ],
        [
            'name' => 'Package LIZ',
            'tiers' => [
                ['label' => 'Solo', 'price' => 1799],
                ['label' => 'Couple', 'price' => 3399],
                ['label' => 'Barkada', 'price' => 6699],
            ],
            'inclusions' => [
                '30 mins Japanese Head Spa',
                'Facial Treatment',
                'Pedicure',
                'Foot Spa',
                '1 hour Whole Body Massage',
            ],
        ],
    ],

    // Real add-on pricelist from the same approved reference flyer.
    'add_ons' => [
        ['name' => 'Body Scrub w/ Whitening Treatment', 'price' => 749],
        ['name' => 'Lymphatic Massage', 'price' => 249],
        ['name' => 'Herbal Balls (Anti-Stress)', 'price' => 299],
        ['name' => 'Full Face Carbon Laser', 'price' => 649],
        ['name' => 'Ventosa', 'price' => 249],
        ['name' => 'Hot Stone', 'price' => 249],
        ['name' => 'Ear Candling', 'price' => 199],
    ],
];
