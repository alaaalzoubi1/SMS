<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Free trial for new provider accounts
    |--------------------------------------------------------------------------
    |
    | Every new doctor / nurse account gets this many days of subscription
    | automatically, so the admin never has to remember to add the trial by
    | hand. The trial is written at registration and re-anchored to the
    | approval date when the admin approves the account (waiting for approval
    | must not eat the trial). A longer expiry the admin set manually is kept.
    |
    */

    'trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 45),

    /*
    | Roles that receive the automatic trial.
    */
    'trial_roles' => ['doctor', 'nurse'],
];
