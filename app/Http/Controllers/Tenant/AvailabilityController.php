<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Authorization\CurrentBranch;
use App\Enums\PetSize;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Pet;
use App\Models\Service;
use App\Pricing\PetProfile;
use App\Scheduling\AvailabilityService;
use App\Scheduling\BookingLine;
use App\Scheduling\ShopClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Free start times for S2 (task 5.4). `mode=staff` (default): every start in opening hours with
 * people on duty, lines taken and an `over` flag; `mode=online`: what the Site would offer.
 * Each line is a service for a saved pet (`pet`) or a pet profile (species, breed, size).
 */
class AvailabilityController extends Controller
{
    public function __invoke(Request $request, AvailabilityService $availability): JsonResponse
    {
        Gate::authorize('viewAny', Appointment::class);

        $data = $request->validate([
            'mode' => ['nullable', Rule::in(['staff', 'online'])],
            'branch' => ['nullable', 'string'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'lines' => ['array', 'max:20'],
            'lines.*.service_id' => ['required', 'integer'],
            'lines.*.pet' => ['nullable', 'string'],
            'lines.*.species_id' => ['nullable', 'integer'],
            'lines.*.breed_id' => ['nullable', 'integer'],
            'lines.*.size' => ['nullable', Rule::enum(PetSize::class)],
        ]);

        $branch = isset($data['branch'])
            ? Branch::query()->where('public_id', $data['branch'])->firstOrFail()
            : (CurrentBranch::get() ?? abort(403, 'Chưa chọn cơ sở.'));
        CurrentBranch::authorize($branch);

        $from = isset($data['from']) ? (string) $data['from'] : ShopClock::current()->today()->format('Y-m-d');
        $to = isset($data['to']) ? (string) $data['to'] : $from;
        abort_if(CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) >= AvailabilityService::MAX_DAYS, 422, 'Tối đa '.AvailabilityService::MAX_DAYS.' ngày.');

        $lines = $this->lines($data['lines'] ?? []);

        $slots = ($data['mode'] ?? 'staff') === 'online'
            ? $availability->slotsForOnline($branch, $lines, $from, $to)
            : $availability->slotsForStaff($branch, $lines, $from, $to);

        return response()->json(['data' => $slots, 'timezone' => ShopClock::current()->timezone()]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $input
     * @return list<BookingLine>
     */
    private function lines(array $input): array
    {
        $services = Service::query()->whereKey(array_column($input, 'service_id'))->where('is_active', true)->get()->keyBy('id');
        $petIds = array_values(array_filter(array_column($input, 'pet'), 'is_string'));
        $pets = Pet::query()->whereIn('public_id', $petIds)->get()->keyBy('public_id');

        $lines = [];
        foreach ($input as $line) {
            $service = $services->get((int) $line['service_id']) ?? abort(422, 'Dịch vụ không hợp lệ.');
            if (isset($line['pet'])) {
                $pet = $pets->get((string) $line['pet']) ?? abort(422, 'Thú cưng không hợp lệ.');
                $lines[] = new BookingLine($service, $pet);

                continue;
            }
            $profile = new PetProfile(
                isset($line['species_id']) ? (int) $line['species_id'] : null,
                isset($line['breed_id']) ? (int) $line['breed_id'] : null,
                isset($line['size']) ? PetSize::from((string) $line['size']) : null,
            );
            $lines[] = new BookingLine($service, $profile);
        }

        return $lines;
    }
}
