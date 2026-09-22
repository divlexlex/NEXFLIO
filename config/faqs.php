<?php

/*
|--------------------------------------------------------------------------
| Guest FAQs Page — content sourced from the SAME verified copy already
| live on Home (resources/views/landing/index.blade.php's own FAQ teaser)
|--------------------------------------------------------------------------
|
| Intentionally duplicated rather than extracted into a shared include:
| Home's FAQ section is already-completed, reviewed Website work and this
| task does not touch it. These are the identical questions/answers, not
| paraphrases — every policy statement here (reservation fee amount,
| grace-period existence, cancellation channel, home service coverage
| behavior) matches what's already approved and live, nothing new was
| invented. Categories are added purely as organizational labels; no new
| policy claim is attached to them.
|
| If Home's FAQ copy is ever revised, reconcile this file to match.
|
*/

return [
    'categories' => [
        [
            'category' => 'Payments',
            'question' => 'Do I need to pay before my appointment?',
            'answer' => 'A ₱200 reservation fee secures your slot. Upload your proof of payment in the booking portal and the branch verifies it, usually within the hour. The balance is settled at check-out.',
        ],
        [
            'category' => 'Before Your Appointment',
            'question' => 'What happens if I arrive late?',
            'answer' => 'A short grace period applies per service; arriving beyond it may require rescheduling depending on the day\'s bookings. Message the branch as soon as you know you\'ll be late.',
        ],
        [
            'category' => 'Booking',
            'question' => 'Can I request a specific therapist?',
            'answer' => 'Yes — preferred staff can be requested during booking, subject to their availability on your selected date and time.',
        ],
        [
            'category' => 'Cancellation',
            'question' => 'How do I cancel or reschedule?',
            'answer' => 'Manage your booking from the mobile app up to the cutoff shown on your appointment details.',
        ],
        [
            'category' => 'Home Service',
            'question' => 'Is home service available in my area?',
            'answer' => 'Home service coverage depends on your location. Availability is confirmed in the app when you book a home-service treatment.',
        ],
    ],
];
