<?php

declare(strict_types=1);

namespace App\Booking\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * An online booking no longer fits the capacity of one of its groups. Nothing was saved. Carries
 * the nearest starts that still fit (HTTP 409).
 */
final class SlotTakenException extends RuntimeException
{
    public const MESSAGE = 'Khung giờ này vừa hết chỗ. Mời chọn giờ khác.';

    /**
     * @param  list<array{date: string, time: string, starts_at: string}>  $suggestions
     */
    public function __construct(public readonly array $suggestions = [])
    {
        parent::__construct(self::MESSAGE);
    }

    /**
     * @param  list<array{date: string, time: string, starts_at: string}>  $suggestions
     */
    public function withSuggestions(array $suggestions): self
    {
        return new self($suggestions);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'code' => 'slot_taken', 'suggestions' => $this->suggestions], 409);
        }

        return back()->withInput()->withErrors(['starts_at' => $this->getMessage()])->with('suggestions', $this->suggestions);
    }
}
