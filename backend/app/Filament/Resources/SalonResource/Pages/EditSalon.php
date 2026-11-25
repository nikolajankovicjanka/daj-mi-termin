<?php

namespace App\Filament\Resources\SalonResource\Pages;

use App\Filament\Resources\SalonResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSalon extends EditRecord
{
    use ManagesOwner;

    protected static string $resource = SalonResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $owner = User::where('salon_id', $this->record->id)->where('role', 'owner')->first();

        return $data + ['owner_name' => $owner?->name, 'owner_email' => $owner?->email];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveSalonAndOwner($record, $data);
    }
}
