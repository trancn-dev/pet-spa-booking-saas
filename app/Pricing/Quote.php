<?php

declare(strict_types=1);

namespace App\Pricing;

use App\Models\ServicePriceRule;

/**
 * Duration and reference price of a service for one pet. The price is only "tham khảo" on
 * appointments (db-schema 2.5); billing uses the price when the service is added to the Visit.
 */
final readonly class Quote
{
    public function __construct(
        public int $durationMin,
        public int $bufferMin,
        public string $price,
        public ?ServicePriceRule $appliedRule,
        /** "90 phút · áp dụng cho Chó cỡ L" */
        public string $explanation,
    ) {}

    /** Time the slot is held: the service plus its buffer. */
    public function blockedMin(): int
    {
        return $this->durationMin + $this->bufferMin;
    }

    /**
     * @return array{duration_min: int, buffer_min: int, price: string, rule_id: int|null, explanation: string}
     */
    public function toArray(): array
    {
        return [
            'duration_min' => $this->durationMin,
            'buffer_min' => $this->bufferMin,
            'price' => $this->price,
            'rule_id' => $this->appliedRule?->id,
            'explanation' => $this->explanation,
        ];
    }
}
