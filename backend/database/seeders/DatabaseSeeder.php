<?php

namespace Database\Seeders;

use App\Models\Salon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }
        $examples = [
            ['Kovač Barber Studio', 'kovac-barber', 'Berber', 'premium', 'Karađorđeva 12', 44.7561, 19.2145, 'Muško šišanje', 20, 30, 'Milan'],
            ['Atelje Lana', 'atelje-lana', 'Ženski frizer', 'premium', 'Gavrila Principa 8', 44.7537, 19.2122, 'Šišanje i feniranje', 40, 60, 'Lana'],
            ['Studio Iris', 'studio-iris', 'Manikir', 'business', 'Nušićeva 7', 44.7582, 19.2163, 'Klasični manikir', 28, 45, 'Una'],
            ['Fizio Centar Balans', 'fizio-balans', 'Fizioterapeut', 'business', 'Semberskih ratara 11', 44.7566, 19.2229, 'Fizioterapeutski tretman', 55, 45, 'Ivan'],
            ['Brow Bar Mia', 'brow-bar-mia', 'Obrve', 'basic', 'Patrijarha Pavla 5', 44.7542, 19.2181, 'Oblikovanje obrva', 15, 30, 'Mia'],
            ['Studio Korak', 'studio-korak', 'Pedikir', 'basic', 'Meše Selimovića 16', 44.7526, 19.2197, 'Klasični pedikir', 35, 45, 'Sara'],
            ['Salon Nika', 'salon-nika', 'Ženski frizer', 'basic', 'Dositeja Obradovića 4', 44.7581, 19.2116, 'Šišanje i feniranje', 30, 45, 'Nika'],
        ];
        $hours = array_fill(1, 5, ['09:00', '17:00']) + [6 => ['09:00', '14:00']];
        DB::transaction(function () use ($examples, $hours) {
            foreach ($examples as $index => [$name,$slug,$category,$plan,$address,$lat,$lng,$service,$price,$duration,$employee]) {
                $photo = '/images/salon-'.($index === 6 ? 2 : $index + 1).'.jpg';
                $salon = Salon::firstOrCreate(['slug' => $slug], [
                    'name' => $name, 'category' => $category, 'plan' => $plan, 'address' => $address, 'city' => 'Bijeljina',
                    'latitude' => $lat, 'longitude' => $lng, 'official_email' => $slug.'@example.test', 'billing_email' => $slug.'@example.test',
                    'photo' => $photo, 'cover' => $plan === 'basic' ? null : $photo,
                    'color' => '#396456', 'expires_at' => today()->addMonths(3),
                ]);
                $staff = $salon->employees()->firstOrCreate(['name' => $employee], ['working_hours' => $hours]);
                $offering = $salon->services()->firstOrCreate(['name' => $service], ['price' => $price, 'duration' => $duration]);
                // Seed a small history only on initial creation. Re-running the seeder preserves feedback.
                if ($salon->wasRecentlyCreated) {
                    foreach ([5, $index % 2 === 0 ? 4 : 5] as $i => $rating) {
                        $start = now(config('booking.timezone'))->subDays(8 + $i)->setTime(10, 0)->utc();
                        $booking = $salon->bookings()->create([
                            'employee_id' => $staff->id, 'service_id' => $offering->id, 'start_at' => $start,
                            'end_at' => $start->copy()->addMinutes($duration), 'price' => $price,
                            'customer_name' => 'Demo klijent '.($i + 1), 'source' => 'online',
                            'status' => 'completed', 'review_token' => Str::random(64),
                        ]);
                        $booking->review()->create(['salon_id' => $salon->id, 'rating' => $rating]);
                    }
                }
            }
        });
    }
}
