<?php

use App\Models\User;
use App\Services\Notices;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

Artisan::command('platform:admin {email}', function (): int {
    $data = ['email' => $this->argument('email'), 'name' => $this->ask('Ime administratora'),
        'password' => $this->secret('Lozinka (najmanje 12 znakova)')];
    $validator = Validator::make($data, ['email' => 'required|email|unique:users,email', 'name' => 'required|max:150',
        'password' => 'required|min:12|max:72']);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }
    User::create($data + ['role' => 'admin', 'salon_id' => null]);
    $this->info('Nalog administratora je kreiran.');

    return 0;
})->purpose('Kreira administratora platforme');

Artisan::command('notices:appointments', function (Notices $notices): void {
    $notices->appointments();
})->purpose('Šalje potvrde, podsjetnike i pozive za ocjenu');

Artisan::command('notices:subscriptions', function (Notices $notices): void {
    $notices->subscriptions();
})->purpose('Šalje mjesečne preglede i obavještenja o isteku');

Schedule::command('notices:appointments')->everyMinute()->withoutOverlapping(10);
Schedule::command('notices:subscriptions')->hourly()->withoutOverlapping(30);
