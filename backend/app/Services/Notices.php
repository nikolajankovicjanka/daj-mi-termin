<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class Notices
{
    private const TIMEZONE = 'Europe/Sarajevo';

    public function confirmation(Booking $booking): void
    {
        if ($booking->status !== 'confirmed' || blank($booking->customer_email)) {
            return;
        }
        $this->deliverOnce("confirmation:{$booking->id}", function () use ($booking): void {
            $this->bookingMail($booking, 'Potvrda rezervacije', 'Vaš termin je rezervisan.');
        });
    }

    public function appointments(): void
    {
        $now = now('UTC');
        Booking::with(['salon', 'employee', 'service'])
            ->where('status', 'confirmed')->whereNotNull('customer_email')->where('start_at', '>', $now)
            ->whereNotExists(fn ($q) => $q->from('notice_dispatches')
                ->whereRaw("notice_key = CONCAT('confirmation:', bookings.id)"))
            ->chunkById(150, fn ($bookings) => $bookings->each(fn ($booking) => $this->confirmation($booking)));

        Booking::with(['salon', 'employee', 'service'])
            ->where('status', 'confirmed')->whereNotNull('customer_email')
            ->where('start_at', '>', $now)->where('start_at', '<=', $now->copy()->addMinutes(30))
            ->chunkById(150, function ($bookings): void {
                foreach ($bookings as $booking) {
                    $this->deliverOnce("reminder:{$booking->id}", fn () => $this->bookingMail($booking, 'Podsjetnik na termin', 'Uskoro počinje Vaš termin.'));
                }
            });

        Booking::with(['salon', 'employee', 'service'])
            ->where('status', 'completed')->whereNotNull('customer_email')->where('end_at', '<=', $now)
            ->whereDoesntHave('review')->whereNotExists(fn ($q) => $q->from('notice_dispatches')
            ->whereRaw("notice_key = CONCAT('review:', bookings.id)"))
            ->chunkById(150, function ($bookings): void {
                foreach ($bookings as $booking) {
                    $this->deliverOnce("review:{$booking->id}", function () use ($booking): void {
                        $url = rtrim(config('app.frontend_url', config('app.url')), '/').'/review/'.$booking->review_token;
                        $this->bookingMail($booking, 'Kako je prošao Vaš termin?', "Svoje iskustvo možete ocijeniti ovdje:\n".$url);
                    });
                }
            });
    }

    public function subscriptions(): void
    {
        $today = now(self::TIMEZONE)->startOfDay();
        Salon::chunkById(150, function ($salons) use ($today): void {
            foreach ($salons as $salon) {
                $until = collect([$salon->expires_at, $salon->free_until])->filter()
                    ->sortByDesc(fn ($date) => $date->format('Y-m-d'))->first();
                if (! $until) {
                    continue;
                }
                $days = (int) $today->diffInDays($until->copy()->shiftTimezone(self::TIMEZONE)->startOfDay(), false);
                if ($days >= 0 && filled($salon->billing_email)) {
                    $month = $today->format('Y-m');
                    $this->deliverOnce("summary:{$salon->id}:{$month}", function () use ($salon, $until): void {
                        $price = (float) $salon->monthly_price;
                        $annual = $price * 12 * (1 - (float) $salon->annual_discount / 100);
                        $priceLabel = number_format($price, 2, ',', '.');
                        $annualLabel = number_format($annual, 2, ',', '.');
                        $benefits = match ($salon->plan) {
                            'premium' => 'Istaknut profil na početnoj stranici i u kategoriji, naslovna fotografija i chat s klijentima.',
                            'business' => 'Istaknut profil u kategoriji, poseban okvir i naslovna fotografija.',
                            default => 'Profil s fotografijom, bojom salona i online rezervacijama.',
                        };
                        $this->mail($salon->billing_email, 'Mjesečni pregled pretplate',
                            "{$salon->name}\nPaket: {$salon->plan}\nVaži do: ".$until->format('d.m.Y.')
                            ."\nMjesečna cijena: {$priceLabel} KM\nGodišnja cijena uz popust od {$salon->annual_discount}%: {$annualLabel} KM\n\n{$benefits}");
                    });
                }
                if ($days < 0 || $days > 7) {
                    continue;
                }
                $expiry = $until->format('Y-m-d');
                $subject = "Ističe pretplata za {$salon->name}";
                $body = 'Pristup salonu važi do '.$until->format('d.m.Y.').'.';
                foreach (User::where('role', 'admin')->get() as $admin) {
                    $key = "{$salon->id}:{$expiry}:{$admin->id}";
                    $this->deliverOnce("expiry-email:{$key}", fn () => $this->mail($admin->email, $subject, $body));
                    $this->deliverOnce("expiry-panel:{$key}", fn () => $admin->notifyNow(
                        Notification::make()->title($subject)->body($body)->warning()->toDatabase()
                    ));
                }
            }
        });
    }

    private function bookingMail(Booking $booking, string $subject, string $opening): void
    {
        $business = $booking->salon;
        $localStart = $booking->start_at->copy()->setTimezone(self::TIMEZONE)->format('d.m.Y. H:i');
        $localEnd = $booking->end_at->copy()->setTimezone(self::TIMEZONE)->format('H:i');
        $this->mail($booking->customer_email, $subject.' · '.$business->name,
            "Poštovani/a {$booking->customer_name},\n\n{$opening}\n\n{$business->name}\n{$business->address}, {$business->city}"
            ."\nTermin: {$localStart}–{$localEnd}\nUsluga: {$booking->service->name}\nZaposleni: {$booking->employee->name}"
            ."\nKontakt salona: {$business->official_email}");
    }

    private function mail(string $recipient, string $subject, string $body): void
    {
        Mail::raw($body, fn ($message) => $message->to($recipient)->subject($subject));
    }

    private function deliverOnce(string $key, callable $send): void
    {
        try {
            Cache::lock("mail:{$key}", 180)->get(function () use ($key, $send): void {
                $table = DB::table('notice_dispatches');
                if ($table->where('notice_key', $key)->exists()) {
                    return;
                }
                $send();
                DB::table('notice_dispatches')->insert(['notice_key' => $key, 'sent_at' => now('UTC')]);
            });
        } catch (Throwable $failure) {
            report($failure);
        }
    }
}
