<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client Payment Methods
    |--------------------------------------------------------------------------
    |
    | The methods a Client can pick on the booking payment step (both Branch
    | and Home Service), and the manual reading details shown for an online
    | method once filled in. Single source of truth for the labels and the
    | receiving details — nothing is hardcoded in the blades.
    |
    | 'methods'   — value => label, in display order.
    | 'receiving' — value => ['label' => ..., 'detail' => ...]. Values are
    |               intentionally EMPTY for now: fill 'detail' in and the
    |               payment step shows it for that method; leave it empty and
    |               the step simply omits the detail line.
    |
    */
    'methods' => [
        'gcash' => 'GCash',
        'maya' => 'Maya',
        'gotyme' => 'GoTyme',
        'bank_transfer' => 'Bank Transfer',
        'card' => 'Card',
        'cash' => 'Cash',
    ],

    'receiving' => [
        'gcash' => ['label' => 'GCash', 'detail' => ''],
        'maya' => ['label' => 'Maya', 'detail' => ''],
        'gotyme' => ['label' => 'GoTyme', 'detail' => ''],
    ],

];
