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

    /*
    |--------------------------------------------------------------------------
    | Payment Rejection Categories
    |--------------------------------------------------------------------------
    |
    | Structured reason a manager picks when rejecting a payment (the
    | free-text note stays alongside it). Single source of truth for the
    | reject <select> and every rejected-payment display — never hardcoded
    | in a blade. The *_eligible flags will later drive the client's
    | follow-up options (refund / top-up); nothing reads them yet.
    |
    */

    'rejection_reasons' => [
        'insufficient_amount' => ['label' => 'Insufficient amount sent', 'refund_eligible' => true, 'topup_eligible' => true],
        'duplicate_booking' => ['label' => 'Duplicate booking', 'refund_eligible' => true, 'topup_eligible' => false],
        'invalid_proof' => ['label' => 'Proof is invalid or unreadable', 'refund_eligible' => false, 'topup_eligible' => false],
        'wrong_recipient' => ['label' => 'Payment sent to a wrong account', 'refund_eligible' => false, 'topup_eligible' => false],
        'no_payment' => ['label' => 'No payment received', 'refund_eligible' => false, 'topup_eligible' => false],
        'other' => ['label' => 'Other', 'refund_eligible' => false, 'topup_eligible' => false],
    ],

];
