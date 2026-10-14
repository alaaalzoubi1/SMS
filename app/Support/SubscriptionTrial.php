<?php

namespace App\Support;

use App\Models\Account;
use Carbon\Carbon;

/**
 * Automatic free-trial handling for provider accounts (doctor / nurse).
 */
class SubscriptionTrial
{
    public static function days(): int
    {
        return max(0, (int) config('subscription.trial_days', 45));
    }

    /**
     * Expiry date for a trial that starts now.
     */
    public static function expiryFromNow(): Carbon
    {
        return Carbon::now()->addDays(self::days());
    }

    public static function appliesTo(Account $account): bool
    {
        if (self::days() === 0) {
            return false;
        }

        return $account->hasAnyRole(config('subscription.trial_roles', ['doctor', 'nurse']));
    }

    /**
     * Called when the admin approves the account: the trial is counted from
     * the approval date. If the admin already gave the account a later expiry
     * by hand, that later date wins.
     */
    public static function anchorToApproval(Account $account): void
    {
        if (!self::appliesTo($account)) {
            return;
        }

        $trialEnd = self::expiryFromNow();
        $current = $account->subscription_expires_at
            ? Carbon::parse($account->subscription_expires_at)
            : null;

        if ($current === null || $current->lt($trialEnd)) {
            $account->subscription_expires_at = $trialEnd;
        }
    }
}
