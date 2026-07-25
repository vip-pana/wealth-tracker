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
];
