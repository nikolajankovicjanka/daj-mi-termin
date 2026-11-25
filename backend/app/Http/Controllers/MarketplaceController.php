<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ChatThread;
use App\Models\Salon;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceController
{
    public function __construct(private BookingService $calendar) {}

    private function discovery()
    {
        return Salon::listed()->select(['id', 'slug', 'name', 'category', 'city', 'address', 'latitude', 'longitude', 'official_email', 'plan', 'color', 'photo', 'cover'])
            ->withMin('services as min_price', 'price')->withAvg('reviews as rating', 'rating')
            ->withCount(['reviews', 'bookings' => fn ($q) => $q->where('status', '!=', 'cancelled')]);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:150', 'city' => 'nullable|string|max:100', 'category' => 'nullable|string|max:100',
            'sort' => 'nullable|in:recommended,rating,price_asc,price_desc,popular',
            'lat' => 'nullable|required_with:lng|numeric|between:-90,90', 'lng' => 'nullable|required_with:lat|numeric|between:-180,180',
            'date' => 'nullable|date_format:Y-m-d', 'time' => 'nullable|date_format:H:i',
        ]);
        $q = $this->discovery();
        if (! empty($data['category'])) {
            $q->where('category', $data['category']);
        }
        if (! empty($data['city'])) {
            $q->where('city', 'like', '%'.$data['city'].'%');
        }
        $term = empty($data['q']) ? null : '%'.$data['q'].'%';
        if ($term !== null) {
            $q->selectRaw('(salons.name LIKE ? OR salons.category LIKE ?) AS matches_profile', [$term, $term])
                ->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('category', 'like', $term)
                    ->orWhereHas('services', fn ($s) => $s->where('name', 'like', $term)));
        }
        $date = null;
        if (! empty($data['date']) || ! empty($data['time'])) {
            $date = $data['date'] ?? now(config('booking.timezone'))->toDateString();
            $day = CarbonImmutable::parse($date, config('booking.timezone'))->startOfDay();
            $q->with(['employees', 'bookings' => fn ($busy) => $busy->where('status', '!=', 'cancelled')
                ->where('start_at', '<', $day->addDay()->utc())->where('end_at', '>', $day->utc())
                ->select('id', 'salon_id', 'employee_id', 'start_at', 'end_at')]);
        }
        if ($term !== null || $date !== null) {
            $q->with(['services' => function ($services) use ($term) {
                if ($term !== null) {
                    $services->select('services.*')->selectRaw('(services.name LIKE ?) AS matches_search', [$term]);
                }
            }]);
        }
        $salons = $q->get()->filter(function ($salon) use ($data, $term, $date) {
            if (isset($data['lat'],$data['lng'])) {
                if ($salon->latitude === null || $salon->longitude === null) {
                    return false;
                }
                $a = sin(deg2rad($salon->latitude - $data['lat']) / 2) ** 2
                    + cos(deg2rad($data['lat'])) * cos(deg2rad($salon->latitude))
                    * sin(deg2rad($salon->longitude - $data['lng']) / 2) ** 2;
                $salon->distance_km = round(12742 * asin(sqrt(min(1, max(0, $a)))), 1);
                if ($salon->distance_km > config('booking.nearby_km')) {
                    return false;
                }
            }
            $services = $salon->relationLoaded('services') ? $salon->services : collect();
            if ($term !== null && ! $salon->matches_profile) {
                $services = $services->where('matches_search', 1);
                $salon->min_price = $services->min('price');
            }
            if ($date !== null) {
                foreach ($services as $service) {
                    $slots = $this->calendar->slots($salon, $service, $date);
                    if ($slots && (empty($data['time']) || in_array($data['time'], array_column($slots, 'label'), true))) {
                        return true;
                    }
                }

                return false;
            }

            return true;
        });
        $sort = $data['sort'] ?? 'recommended';
        $rank = function ($salon) use ($sort) {
            $tier = ['premium' => 0, 'business' => 1, 'basic' => 2][$salon->plan] ?? 2;

            return [
                match ($sort) {
                    'rating' => -($salon->rating ?? 0),
                    'popular' => -$salon->bookings_count,
                    'price_asc' => $salon->min_price === null ? INF : (float) $salon->min_price,
                    'price_desc' => $salon->min_price === null ? INF : -(float) $salon->min_price,
                    default => $tier,
                },
                $tier, $salon->distance_km ?? 0, -($salon->rating ?? 0), $salon->name, $salon->id,
            ];
        };

        return $salons->sort(fn ($a, $b) => $rank($a) <=> $rank($b))->each(function ($salon) {
            $salon->unsetRelations();
            unset($salon->matches_profile);
        })->values();
    }

    public function show(string $slug)
    {
        return $this->discovery()->with(['employees:id,salon_id,name', 'services:id,salon_id,name,duration,price',
            'reviews' => fn ($q) => $q->select('id', 'salon_id', 'rating', 'comment', 'created_at')->latest()->limit(50)])
            ->where('slug', $slug)->firstOrFail();
    }

    private function salon(string $slug): Salon
    {
        return Salon::listed()->where('slug', $slug)->firstOrFail();
    }

    public function slots(Request $request, string $slug)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'service_id' => 'required|integer', 'employee_id' => 'nullable|integer|min:1']);
        $salon = $this->salon($slug);

        return $this->calendar->slots($salon, $salon->services()->findOrFail($data['service_id']), $data['date'], $data['employee_id'] ?? null);
    }

    public function book(Request $request, string $slug)
    {
        $data = $request->validate(['service_id' => 'required|integer', 'employee_id' => 'required|integer',
            'customer_name' => 'required|string|max:120', 'customer_email' => 'required|email|max:254', 'start_at' => 'required|date']);
        $booking = $this->calendar->reserve($this->salon($slug), $data);

        return response()->json($booking->only(['id', 'start_at', 'end_at', 'status']), 201);
    }

    public function review(Request $request, string $token)
    {
        $data = $request->validate(['rating' => 'required|integer|between:1,5', 'comment' => 'nullable|string|max:2000']);
        DB::transaction(function () use ($token, $data) {
            $booking = Booking::where('review_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($booking->status === 'completed' && $booking->end_at->isPast(), 422, 'Ocjena je dostupna nakon završene posjete.');
            abort_if($booking->review()->exists(), 409, 'Ovaj termin je već ocijenjen.');
            $booking->review()->create($data + ['salon_id' => $booking->salon_id]);
        });

        return response()->json(['message' => 'Hvala na ocjeni.'], 201);
    }

    public function startChat(Request $request, string $slug)
    {
        $data = $request->validate(['customer_name' => 'required|string|max:120', 'body' => 'required|string|max:2000']);
        $salon = $this->salon($slug);
        abort_unless($salon->plan === 'premium', 403);
        $thread = DB::transaction(function () use ($salon, $data) {
            $thread = $salon->chatThreads()->create(['customer_name' => $data['customer_name'], 'token' => Str::random(64)]);
            $thread->messages()->create(['sender' => 'customer', 'body' => $data['body']]);

            return $thread;
        });

        return response()->json($this->conversation($thread), 201);
    }

    private function conversation(ChatThread $thread): array
    {
        return ['token' => $thread->token, 'messages' => $thread->messages()->get(['id', 'sender', 'body', 'created_at'])];
    }

    private function thread(string $token): ChatThread
    {
        return ChatThread::where('token', $token)->whereHas('salon', fn ($q) => $q->listed()->where('plan', 'premium'))->firstOrFail();
    }

    public function chat(string $token)
    {
        return $this->conversation($this->thread($token));
    }

    public function message(Request $request, string $token)
    {
        $data = $request->validate(['body' => 'required|string|max:2000']);
        $thread = $this->thread($token);
        $thread->messages()->create($data + ['sender' => 'customer']);

        return $this->conversation($thread);
    }
}
