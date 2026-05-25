<?php

namespace App\Support;

use App\Models\Campaign;
use Illuminate\Validation\ValidationException;

class CampaignManager
{
    public function activeOrFail(): Campaign
    {
        $campaign = Campaign::query()
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->latest('id')
            ->first();

        if (! $campaign) {
            throw ValidationException::withMessages([
                'campaign' => 'No hay una campaña activa en este momento.',
            ]);
        }

        return $campaign;
    }
}
