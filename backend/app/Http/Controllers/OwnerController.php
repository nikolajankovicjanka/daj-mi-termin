<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Employee;
use App\Models\Salon;
use App\Models\Service;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OwnerController
{
    private function salon(Request $request): Salon
    {
        abort_unless($request->user()?->role === 'owner', 403);

        return Salon::findOrFail($request->user()->salon_id);
    }

    public function session(Request $request)
    {
        return response()->json(['logged_in' => $request->user()?->role === 'owner']);
    }

    public function login(Request $request)
    {
        $input = $request->validate(['email' => ['required', 'email'], 'password' => 'required|string']);
        if (! Auth::attempt([...$input, 'role' => 'owner'])) {
            abort(422, 'Provjerite email adresu i lozinku.');
        }
        $request->session()->regenerate();

        return $this->session($request);
    }

    public function logout(Request $request)
    {
        $this->salon($request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function workspace(Request $request)
    {
        $salon = $this->salon($request);

        return [
            'salon' => $salon->only(['id', 'name', 'slug', 'category', 'address', 'city', 'latitude', 'longitude', 'photo', 'cover', 'color', 'plan', 'expires_at', 'free_until', 'official_email', 'billing_email']),
            'email' => $request->user()->email,
            'services' => Service::where('salon_id', $salon->id)->orderBy('name')->get(),
            'employees' => Employee::where('salon_id', $salon->id)->orderBy('name')->get(),
        ];
    }

    public function calendar(Request $request)
    {
        $salon = $this->salon($request);
        $date = $request->validate(['date' => 'required|date_format:Y-m-d'])['date'];
        $start = CarbonImmutable::parse($date, config('booking.timezone'))->startOfDay();

        return Booking::query()->join('services', 'services.id', '=', 'bookings.service_id')
            ->where('bookings.salon_id', $salon->id)
            ->where('start_at', '<', $start->addDay()->utc())->where('end_at', '>', $start->utc())
            ->orderBy('start_at')->get(['bookings.id', 'employee_id', 'customer_name', 'customer_email', 'start_at', 'end_at', 'status', 'source', 'services.name as service_name']);
    }

    public function slots(Request $request, BookingService $engine)
    {
        $salon = $this->salon($request);
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'service_id' => 'required|integer', 'employee_id' => 'nullable|integer']);
        $service = Service::where('salon_id', $salon->id)->findOrFail($data['service_id']);

        return $engine->slots($salon, $service, $data['date'], $data['employee_id'] ?? null);
    }

    public function book(Request $request, BookingService $engine)
    {
        $salon = $this->salon($request);
        $data = $request->validate([
            'service_id' => 'required|integer', 'employee_id' => 'required|integer', 'start_at' => 'required|date',
            'customer_name' => 'required|string|max:120', 'customer_email' => 'nullable|email|max:255', 'source' => 'required|in:phone,walk_in',
        ]);
        $booking = $engine->reserve($salon, $data, $data['source']);

        return response()->json(['id' => $booking->id], 201);
    }

    public function bookingStatus(Request $request, int $id)
    {
        $salon = $this->salon($request);
        $status = $request->validate(['status' => 'required|in:completed,cancelled'])['status'];

        return DB::transaction(function () use ($salon, $id, $status) {
            $booking = Booking::where('salon_id', $salon->id)->lockForUpdate()->findOrFail($id);
            abort_if($booking->status !== 'confirmed', 409, 'Ovaj termin je već obrađen.');
            abort_if($status === 'completed' && $booking->end_at->isFuture(), 422, 'Termin još traje.');
            $booking->update(['status' => $status]);

            return ['ok' => true];
        });
    }

    public function service(Request $request, ?int $id = null)
    {
        $salon = $this->salon($request);
        $data = $request->validate(['name' => 'required|string|max:120', 'price' => 'required|numeric|min:0|max:99999.99', 'duration' => 'required|integer|min:5|max:720']);
        $service = $id ? Service::where('salon_id', $salon->id)->findOrFail($id) : new Service;
        $service->forceFill([...$data, 'salon_id' => $salon->id])->save();

        return ['id' => $service->id];
    }

    public function employee(Request $request, ?int $id = null)
    {
        $salon = $this->salon($request);
        $data = $request->validate(['name' => 'required|string|max:120', 'working_hours' => 'present|array:1,2,3,4,5,6,7', 'working_hours.*' => 'array|size:2', 'working_hours.*.*' => 'required|date_format:H:i']);
        foreach ($data['working_hours'] as $pair) {
            abort_unless(array_is_list($pair) && $pair[0] < $pair[1], 422, 'Kraj smjene mora biti poslije početka.');
        }
        $employee = $id ? Employee::where('salon_id', $salon->id)->findOrFail($id) : new Employee;
        $employee->forceFill([...$data, 'salon_id' => $salon->id])->save();

        return ['id' => $employee->id];
    }

    private function retire(Request $request, int $id, string $class, string $column)
    {
        $salon = $this->salon($request);

        return DB::transaction(function () use ($salon, $id, $class, $column) {
            $item = $class::where('salon_id', $salon->id)->lockForUpdate()->findOrFail($id);
            $future = Booking::where('salon_id', $salon->id)->where($column, $id)->where('status', 'confirmed')->where('end_at', '>', now())->exists();
            abort_if($future, 409, 'Postoje aktivni termini. Prvo obradite te termine.');
            $item->delete();

            return ['ok' => true];
        });
    }

    public function deleteService(Request $request, int $id)
    {
        return $this->retire($request, $id, Service::class, 'service_id');
    }

    public function deleteEmployee(Request $request, int $id)
    {
        return $this->retire($request, $id, Employee::class, 'employee_id');
    }

    public function profile(Request $request)
    {
        $salon = $this->salon($request);
        $data = $request->validate([
            'name' => 'required|string|max:150', 'address' => 'required|string|max:255', 'city' => 'required|string|max:100',
            'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90', 'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
            'color' => ['required', 'regex:/^#[0-9a-f]{6}$/i'], 'official_email' => 'required|email|max:255', 'billing_email' => 'required|email|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096', 'cover' => [$salon->plan === 'basic' ? 'prohibited' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ]);
        $replaced = [];
        foreach (['photo', 'cover'] as $field) {
            unset($data[$field]);
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store('salons/'.$salon->id, 'public');
                abort_unless($path, 500, 'Slika nije sačuvana.');
                $data[$field] = '/storage/'.$path;
                $previous = $salon->getRawOriginal($field);
                if (is_string($previous) && str_starts_with($previous, '/storage/salons/'.$salon->id.'/')) {
                    $replaced[] = substr($previous, strlen('/storage/'));
                }
            }
        }
        $salon->forceFill($data)->save();
        if ($replaced) {
            Storage::disk('public')->delete($replaced);
        }

        return ['ok' => true];
    }

    public function account(Request $request)
    {
        $this->salon($request);
        $user = $request->user();
        $data = $request->validate([
            'current_password' => 'required|current_password:web',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:10|max:72|confirmed',
        ]);
        $user->email = $data['email'];
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $request->session()->regenerate();

        return ['ok' => true];
    }

    private function premium(Request $request): Salon
    {
        $salon = $this->salon($request);
        abort_unless($salon->plan === 'premium' && Salon::listed()->whereKey($salon->id)->exists(), 403, 'Potreban je aktivan Premium paket.');

        return $salon;
    }

    public function threads(Request $request)
    {
        return ChatThread::where('salon_id', $this->premium($request)->id)->latest('id')->get(['id', 'customer_name']);
    }

    private function thread(Request $request, int $id): ChatThread
    {
        return ChatThread::where('salon_id', $this->premium($request)->id)->findOrFail($id);
    }

    public function messages(Request $request, int $id)
    {
        return ChatMessage::where('chat_thread_id', $this->thread($request, $id)->id)->orderBy('id')->get(['id', 'body', 'sender', 'created_at']);
    }

    public function reply(Request $request, int $id)
    {
        $thread = $this->thread($request, $id);
        $body = $request->validate(['body' => 'required|string|max:2000'])['body'];
        $message = new ChatMessage;
        $message->forceFill(['chat_thread_id' => $thread->id, 'body' => $body, 'sender' => 'salon'])->save();

        return response()->json(['id' => $message->id],201);
    }
}
