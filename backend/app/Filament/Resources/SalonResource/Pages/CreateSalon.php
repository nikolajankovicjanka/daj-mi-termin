<?php

namespace App\Filament\Resources\SalonResource\Pages;

use App\Filament\Resources\SalonResource;
use App\Models\Salon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSalon extends CreateRecord
{
    use ManagesOwner;

    protected static string $resource = SalonResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveSalonAndOwner(new Salon, $data);
    }
}
