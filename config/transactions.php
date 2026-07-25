<?php

declare(strict_types=1);

return [
    /*
     * The account holder's name as it appears in self-transfer notes (e.g. a
     * standing order to one's own account, or a Revolut top-up labelled
     * "Payment from <name>"). Used to classify those rows as internal transfers
     * rather than income. Set SELF_TRANSFER_NAME in .env to your surname.
     */
    'self_transfer_name' => env('SELF_TRANSFER_NAME'),

    /*
     * Extra note substrings that mark an internal transfer (e.g. a specific
     * card or account signature such as "Apple Pay Top-Up by *1234"). These are
     * account-specific identifiers, so they live in the env rather than in the
     * code. Comma-separated in TRANSFER_MARKERS; empty disables them.
     */
    'transfer_markers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRANSFER_MARKERS', ''))
    ))),
];
