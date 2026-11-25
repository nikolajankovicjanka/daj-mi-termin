<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SalonResource;
use App\Models\Salon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ExpiringSubscriptions extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Pretplate koje ističu u narednih sedam dana';

    public function table(Table $table): Table
    {
        $today = now(config('booking.timezone'))->toDateString();
        $week = now(config('booking.timezone'))->addDays(7)->toDateString();

        return $table->query(Salon::query()
            ->where(fn ($q) => $q->whereBetween('expires_at', [$today, $week])->orWhereBetween('free_until', [$today, $week]))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '<=', $week))
            ->where(fn ($q) => $q->whereNull('free_until')->orWhere('free_until', '<=', $week))
            ->orderByRaw('CASE WHEN free_until IS NULL OR expires_at >= free_until THEN expires_at ELSE free_until END'))
            ->columns([
                TextColumn::make('name')->label('Firma'),
                TextColumn::make('plan')->label('Paket')->formatStateUsing(fn ($state) => SalonResource::PLANS[$state] ?? $state),
                TextColumn::make('ends_on')->label('Pristup do')->state(fn (Salon $record) => collect([$record->expires_at, $record->free_until])->filter()->max())->date('d.m.Y.'),
                TextColumn::make('billing_email')->label('Kontakt'),
            ])->recordUrl(fn (Salon $record) => SalonResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('Nema pretplata pred istekom');
    }
}
