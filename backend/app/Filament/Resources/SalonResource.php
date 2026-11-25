<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalonResource\Pages;
use App\Models\Salon;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class SalonResource extends Resource
{
    protected static ?string $model = Salon::class;

    protected static ?string $modelLabel = 'salon';

    protected static ?string $pluralModelLabel = 'Saloni';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    public const PLANS = ['basic' => 'Osnovni', 'business' => 'Business', 'premium' => 'Premium'];

    public static function form(Form $form): Form
    {
        $categories = config('booking.categories', []);

        return $form->schema([
            Section::make('Firma')->columns(2)->schema([
                TextInput::make('name')->label('Naziv firme')->required()->maxLength(150),
                TextInput::make('slug')->label('Oznaka u adresi profila')->required()
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true)->maxLength(120),
                Select::make('category')->label('Kategorija')->options(array_combine($categories, $categories))->required(),
                TextInput::make('city')->label('Grad')->required()->maxLength(120),
                TextInput::make('address')->label('Adresa')->required()->columnSpanFull()->maxLength(220),
                TextInput::make('latitude')->label('Geografska širina')->numeric()->minValue(-90)->maxValue(90)->required(),
                TextInput::make('longitude')->label('Geografska dužina')->numeric()->minValue(-180)->maxValue(180)->required(),
                TextInput::make('official_email')->label('Zvanični email firme')->email()->required()->maxLength(200),
                TextInput::make('billing_email')->label('Email za pretplatu')->email()->required()->maxLength(200),
            ]),
            Section::make('Vlasnik')->columns(2)->schema([
                TextInput::make('owner_name')->label('Ime i prezime')->required()->maxLength(150),
                TextInput::make('owner_email')->label('Email za prijavu')->email()->required()->maxLength(200)
                    ->rules(fn (?Salon $record): array => [Rule::unique('users', 'email')->ignore(
                        $record ? User::where('salon_id', $record->id)->where('role', 'owner')->value('id') : null
                    )]),
                TextInput::make('owner_password')->label('Lozinka vlasnika')->password()->revealable()
                    ->minLength(10)->maxLength(72)->required(fn (?Salon $record): bool => ! $record ||
                        ! User::where('salon_id', $record->id)->where('role', 'owner')->exists())
                    ->helperText('Prilikom izmjene ostavite prazno za zadržavanje postojeće lozinke.'),
            ]),
            Section::make('Pretplata')->columns(2)->schema([
                Select::make('plan')->label('Paket')->options(self::PLANS)->default('basic')->required(),
                Select::make('trial_months')->label('Početni besplatni period')->visibleOn('create')->live()
                    ->options([0 => 'Bez probnog perioda', 1 => '1 mjesec', 2 => '2 mjeseca', 3 => '3 mjeseca'])->default(0),
                DatePicker::make('expires_at')->label('Plaćeni pristup do')
                    ->visible(fn (Get $get): bool => ! $get('trial_months'))
                    ->required(fn (Get $get): bool => ! $get('free_until') && ! $get('trial_months')),
                TextInput::make('monthly_price')->label('Mjesečna cijena (KM)')->numeric()->step('0.01')
                    ->minValue(0)->maxValue(99999)->default(0)->required(),
                TextInput::make('annual_discount')->label('Popust za godinu (%)')->numeric()->step(1)
                    ->minValue(0)->maxValue(100)->default(0)->required(),
                DatePicker::make('free_until')->label('Besplatni pristup do')->visibleOn('edit')->live(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Firma')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('city')->label('Grad')->searchable(),
            Tables\Columns\TextColumn::make('plan')->label('Paket')->badge()->formatStateUsing(fn ($state) => self::PLANS[$state] ?? $state),
            Tables\Columns\TextColumn::make('expires_at')->label('Plaćeno do')->date('d.m.Y.')->sortable(),
            Tables\Columns\TextColumn::make('free_until')->label('Besplatno do')->date('d.m.Y.'),
        ])->filters([Tables\Filters\SelectFilter::make('plan')->label('Paket')->options(self::PLANS)])
            ->actions([
                Tables\Actions\EditAction::make()->label('Uredi'),
                Tables\Actions\Action::make('free')->label('Odobri besplatne mjesece')
                    ->form([Select::make('months')->label('Broj mjeseci')->options([1 => '1', 2 => '2', 3 => '3'])->required()])
                    ->action(function (Salon $record, array $data): void {
                        $through = collect([now('Europe/Sarajevo')->startOfDay(), $record->free_until, $record->expires_at])
                            ->filter()->sortByDesc(fn ($date) => $date->format('Y-m-d'))->first();
                        $record->update(['free_until' => $through->copy()->addMonthsNoOverflow((int) $data['months'])]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSalons::route('/'), 'create' => Pages\CreateSalon::route('/create'),
            'edit' => Pages\EditSalon::route('/{record}/edit')];
    }
}
