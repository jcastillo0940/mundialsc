<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ContestRules
{
    public function registrationDeadline(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) config('contest.registration_deadline'), 'America/Panama');
    }

    public function winnerAnnouncementDate(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) config('contest.winner_announcement_date'), 'America/Panama');
    }

    public function minimumInvoiceAmount(): float
    {
        return (float) config('contest.minimum_invoice_amount', 25);
    }

    public function maxInvoiceAgeDays(): int
    {
        return (int) config('contest.max_invoice_age_days', 1);
    }

    public function winnerSlots(): int
    {
        return (int) config('contest.winner_slots', 3);
    }
}
