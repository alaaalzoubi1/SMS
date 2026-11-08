<?php

namespace App\Jobs;

use App\Models\NurseReservation;
use App\Support\NurseRequestManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;

/**
 * Dispatched with a delay of NurseRequestManager::TIMEOUT_MINUTES when a
 * nurse request is created. If the nurse still has not answered, the request
 * becomes 'expired', the nurse is freed and the patient is told to pick
 * another nurse or contact support.
 */
class ExpireNurseRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $reservationId)
    {
    }

    public function handle(): void
    {
        $reservation = NurseReservation::find($this->reservationId);

        if (!$reservation || $reservation->status !== 'pending') {
            return;
        }

        // Guard for a sync queue (runs immediately) or a clock skew: only
        // expire once the deadline has really passed. The lazy check in
        // NurseRequestManager covers the request later in that case.
        if ($reservation->response_deadline_at && $reservation->response_deadline_at->isFuture()) {
            return;
        }

        NurseRequestManager::expire($reservation);
    }
}
