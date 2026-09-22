<?php

/*
|--------------------------------------------------------------------------
| Guest Contact Page — verified business info, same facts already live on
| Home (resources/views/landing/index.blade.php's own Visit/Contact
| section) and the site footer (layouts/public.blade.php)
|--------------------------------------------------------------------------
|
| Intentionally duplicated rather than extracted into a shared include —
| Home's Contact section is already-completed, reviewed Website work and
| this task does not touch it. No phone number, email, address, or hour
| appears here that isn't already printed on Home/the footer; nothing was
| invented for this page. There is no verified public email address or
| social account anywhere in the project/reference material, so neither
| is included.
|
*/

return [
    'business_name' => 'Perfect Nails Wellness & Aesthetics',
    'address_line' => '237 A. Mabini St., Maypajo, Caloocan',
    'hours' => 'Monday – Sunday: 10:00 AM – 9:00 PM',
    'phones' => ['0905 556 3955', '0917 558 2122'],
    'map_url' => 'https://maps.google.com/?q=237+A.+Mabini+St.,+Maypajo,+Caloocan',
];
