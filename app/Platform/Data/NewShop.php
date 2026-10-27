<?php

declare(strict_types=1);

namespace App\Platform\Data;

use App\Models\Plan;
use Carbon\CarbonInterface;

/**
 * What A3 collects to provision a shop: (1) shop + first branch, (2) owner, (3) plan, either a
 * trial of N days or a sale (from, until, amount, transfer reference).
 */
final readonly class NewShop
{
    public function __construct(
        public string $name,
        public string $slug,
        public Plan $plan,
        public string $branchCode,
        public string $branchName,
        public string $ownerName,
        public string $ownerEmail,
        public ?string $ownerPhone = null,
        public ?int $trialDays = null,
        public ?CarbonInterface $paidFrom = null,
        public ?CarbonInterface $paidUntil = null,
        public ?string $amount = null,
        public ?string $paymentRef = null,
        public string $timezone = 'Asia/Ho_Chi_Minh',
        public ?string $branchAddress = null,
        public ?string $branchPhone = null,
        public ?string $note = null,
    ) {}

    public function isTrial(): bool
    {
        return $this->trialDays !== null;
    }
}
