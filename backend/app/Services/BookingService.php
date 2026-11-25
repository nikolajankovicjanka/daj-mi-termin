<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Employee;
use App\Models\Salon;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    /** Return only starts at which the entire service fits into one employee's shift. */
    public function slots(Salon $salon, Service $service, string $date, ?int $employeeId = null): array
    {
        $timezone = config('booking.timezone');
        $day = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $now = CarbonImmutable::now($timezone);
        if ($day->lt($now->startOfDay()) || $day->gt($now->addDays(config('booking.horizon_days')))) {
            return [];
        }
        $employees = $salon->relationLoaded('employees') ? $salon->employees
            : $salon->employees()->when($employeeId, fn ($q) => $q->whereKey($employeeId))->get();
        if ($employeeId) {
            $employees = $employees->where('id', $employeeId);
        }
        $bookings = $salon->relationLoaded('bookings') ? $salon->bookings
            : $salon->bookings()->whereIn('employee_id', $employees->modelKeys())
                ->where('status', '!=', 'cancelled')
                ->where('start_at', '<', $day->addDay()->utc())->where('end_at', '>', $day->utc())
                ->get(['employee_id', 'start_at', 'end_at']);
        $busy = $bookings->groupBy('employee_id');
        $slots = [];
        foreach ($employees as $employee) {
            $shift = $employee->working_hours[$day->isoWeekday()] ?? [];
            if (! isset($shift[0], $shift[1])) {
                continue;
            }
            $close = $day->setTimeFromTimeString($shift[1]);
            for ($start = $day->setTimeFromTimeString($shift[0]); $start->addMinutes($service->duration)->lte($close); $start = $start->addMinutes(15)) {
                $end = $start->addMinutes($service->duration);
                if ($start->lte($now) || $busy->get($employee->id, collect())->contains(fn ($b) => $b->start_at->lt($end) && $b->end_at->gt($start))) {
                    continue;
                }
                $slots[] = ['employee_id' => $employee->id, 'employee_name' => $employee->name,
                    'start_at' => $start->utc()->format('Y-m-d\TH:i:s\Z'), 'label' => $start->format('H:i')];
            }
        }
        usort($slots, fn ($a, $b) => [$a['start_at'], $a['employee_name']] <=> [$b['start_at'], $b['employee_name']]);

        return $slots;
    }

    public function reserve(Salon $salon, array $data, string $source = 'online'): Booking
    {
        $booking = DB::transaction(function () use ($salon, $data, $source) {
            // Lock the same employee for online, telephone and walk-in bookings, even on an empty day.
            $employee = $salon->employees()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $service = $salon->services()->whereKey($data['service_id'])->sharedLock()->firstOrFail();
            $start = CarbonImmutable::parse($data['start_at']);
            $date = $start->setTimezone(config('booking.timezone'))->toDateString();
            $available = collect($this->slots($salon, $service, $date, $employee->id))
                ->contains('start_at', $start->utc()->format('Y-m-d\TH:i:s\Z'));
            if (! $available || $start->micro !== 0) {
                throw ValidationException::withMessages(['start_at' => 'Termin je zauzet ili izvan radnog vremena. Odaberite drugi termin.']);
            }

            return $salon->bookings()->create([
                'employee_id' => $employee->id, 'service_id' => $service->id,
                'customer_name' => $data['customer_name'], 'customer_email' => $data['customer_email'] ?? null,
                'start_at' => $start->utc(), 'end_at' => $start->addMinutes($service->duration)->utc(),
                'price' => $service->price, 'source' => $source, 'status' => 'confirmed',
                'review_token' => Str::random(64),
            ]);
        }, 3);
        if ($booking->customer_email) {
            DB::afterCommit(function () use ($booking) {
                try {
                    app(Notices::class)->confirmation($booking);
                } catch (\Throwable $error) {
                    report($error); // Scheduler will retry delivery without duplicating a reservation.
                }
            });
        }

        return $booking;
    }
}
