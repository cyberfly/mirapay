<?php

return [
    /*
    | Days a subscription keeps access after a renewal falls due unpaid, before it expires.
    */
    'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 7),

    /*
    | Days between payment reminders while a renewal is unpaid.
    */
    'reminder_every_days' => (int) env('SUBSCRIPTION_REMINDER_DAYS', 3),
];
