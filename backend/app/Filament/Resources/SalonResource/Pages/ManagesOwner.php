<?php

namespace App\Filament\Resources\SalonResource\Pages;

use App\Models\Salon;
use App\Models\User;
use Illuminate\Support\Arr;

trait ManagesOwner
{
    protected function saveSalonAndOwner(Salon $salon, array $data): Salon
    {
        if (! $salon->exists && (int) ($data['trial_months'] ?? 0) > 0) {
            $data['expires_at'] = null;
            $data['free_until'] = now('Europe/Sarajevo')->addMonthsNoOverflow((int) $data['trial_months'])->toDateString();
        }
        $salon->fill(Arr::except($data, ['owner_name', 'owner_email', 'owner_password', 'trial_months']))->save();
        $owner = User::firstOrNew(['salon_id' => $salon->id, 'role' => 'owner']);
        $owner->name = $data['owner_name'];
        $owner->email = $data['owner_email'];
        if (filled($data['owner_password'] ?? null)) {
            $owner->password = $data['owner_password'];
        }
        $owner->save();

        return $salon;
    }
}
