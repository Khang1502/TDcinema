<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingHoldService
{
    public const HOLD_MINUTES = 10;

    public function start(int $userId, int $showtimeId, Collection $seatCodes): array
    {
        $seatCodes = $this->normalize($seatCodes);
        if ($seatCodes->isEmpty()) {
            throw ValidationException::withMessages(['seats' => 'Vui lòng chọn ít nhất một ghế.']);
        }

        $current = $this->active($showtimeId);
        if ($current && (int) $current['user_id'] !== $userId) {
            $this->release();
            $current = null;
        }
        if ($current && $this->normalize(collect($current['seats']))->all() === $seatCodes->all()) {
            return $current;
        }

        // Changes to the seat selection share the first hold's deadline.
        $token = $current['token'] ?? (string) Str::uuid();
        $expiresAt = $current['expires_at'] ?? now()->addMinutes(self::HOLD_MINUTES)->timestamp;
        $value = [
            'token' => $token,
            'user_id' => $userId,
            'showtime_id' => $showtimeId,
            'expires_at' => $expiresAt,
        ];
        $acquired = collect();

        try {
            foreach ($seatCodes as $code) {
                $key = $this->seatKey($showtimeId, $code);
                $existing = Cache::get($key);
                if (is_array($existing) && ($existing['expires_at'] ?? 0) <= now()->timestamp) {
                    Cache::forget($key);
                    $existing = null;
                }

                if (is_array($existing) && ($existing['token'] ?? null) === $token) {
                    continue;
                }

                if ($existing || ! Cache::add($key, $value, now()->setTimestamp($expiresAt))) {
                    throw ValidationException::withMessages([
                        'seats' => "Ghế {$code} vừa được khách khác giữ. Vui lòng chọn ghế khác.",
                    ]);
                }
                $acquired->push($code);
            }
        } catch (\Throwable $exception) {
            $this->releaseKeys($showtimeId, $acquired, $token);
            throw $exception;
        }

        if ($current) {
            $removed = collect($current['seats'])->diff($seatCodes);
            $this->releaseKeys($showtimeId, $removed, $token);
        }

        $hold = [
            'token' => $token,
            'user_id' => $userId,
            'showtime_id' => $showtimeId,
            'seats' => $seatCodes->all(),
            'expires_at' => $expiresAt,
        ];

        $this->forgetBookingSession();
        session()->put('booking.hold', $hold);
        session()->put('booking.seats', $seatCodes->implode(','));
        session()->put('booking.showtime_id', $showtimeId);

        return $hold;
    }

    public function active(int $showtimeId): ?array
    {
        $hold = session('booking.hold');
        if (! is_array($hold)
            || (int) ($hold['showtime_id'] ?? 0) !== $showtimeId
            || (int) ($hold['expires_at'] ?? 0) <= now()->timestamp) {
            if (is_array($hold)) {
                $this->release();
            }
            return null;
        }

        foreach ($hold['seats'] ?? [] as $code) {
            $cached = Cache::get($this->seatKey($showtimeId, $code));
            if (! is_array($cached) || ($cached['token'] ?? null) !== ($hold['token'] ?? null)) {
                $this->forgetBookingSession();
                return null;
            }
        }

        return $hold;
    }

    public function heldByOther(int $showtimeId, Collection $seatCodes): Collection
    {
        $ownToken = data_get(session('booking.hold'), 'token');

        return $this->normalize($seatCodes)->filter(function (string $code) use ($showtimeId, $ownToken) {
            $hold = Cache::get($this->seatKey($showtimeId, $code));
            return is_array($hold)
                && (int) ($hold['expires_at'] ?? 0) > now()->timestamp
                && ($hold['token'] ?? null) !== $ownToken;
        })->values();
    }

    public function remainingSeconds(?array $hold): int
    {
        return max(0, (int) ($hold['expires_at'] ?? 0) - now()->timestamp);
    }

    public function release(): void
    {
        $hold = session('booking.hold');
        if (is_array($hold)) {
            $this->releaseKeys(
                (int) ($hold['showtime_id'] ?? 0),
                collect($hold['seats'] ?? []),
                (string) ($hold['token'] ?? '')
            );
        }
        $this->forgetBookingSession();
    }

    private function releaseKeys(int $showtimeId, Collection $seatCodes, string $token): void
    {
        foreach ($seatCodes as $code) {
            $key = $this->seatKey($showtimeId, (string) $code);
            $cached = Cache::get($key);
            if (is_array($cached) && ($cached['token'] ?? null) === $token) {
                Cache::forget($key);
            }
        }
    }

    private function forgetBookingSession(): void
    {
        session()->forget([
            'booking.hold',
            'booking.seats',
            'booking.showtime_id',
            'booking.combo_total',
            'booking.combo_items',
            'booking.promo_code',
            'booking.pending_payment',
        ]);
    }

    private function normalize(Collection $seatCodes): Collection
    {
        return $seatCodes
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function seatKey(int $showtimeId, string $seatCode): string
    {
        return "booking-hold:{$showtimeId}:" . strtoupper($seatCode);
    }
}
