<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sponsorship inquiry notifications
    |--------------------------------------------------------------------------
    |
    | Where a copy of every submitted "Sponsor Us" form is emailed. Left blank
    | in local/testing, the mail is skipped rather than erroring.
    |
    */

    'notify_email' => env('SPONSORSHIP_NOTIFY_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Express sponsorship PayPal link
    |--------------------------------------------------------------------------
    |
    | A static PayPal.me (or equivalent) URL. No PayPal SDK/checkout
    | integration — this is a plain outbound link.
    |
    */

    'paypal_url' => env('SPONSORSHIP_PAYPAL_URL', 'https://paypal.me/vertucio'),

];
