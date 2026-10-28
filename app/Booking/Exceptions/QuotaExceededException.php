<?php

declare(strict_types=1);

namespace App\Booking\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** The shop used every appointment of its plan this month (HTTP 409). Nothing was saved. */
final class QuotaExceededException extends RuntimeException
{
    public const MESSAGE = 'Shop đã dùng hết số lịch hẹn của gói tháng này. Liên hệ để nâng gói.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'code' => 'quota_exceeded'], 409);
        }

        return back()->withInput()->withErrors(['starts_at' => $this->getMessage()]);
    }
}
