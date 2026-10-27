<?php

declare(strict_types=1);

namespace App\Platform;

use App\Models\Platform\PlatformAdmin;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) for platform admins: mandatory, 6 digits, 30 s steps, ±1 step tolerance.
 */
class TwoFactor
{
    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Demo convenience only: admins without 2FA may skip the code when APP_ENV is local / demo
     * and PLATFORM_SKIP_2FA is on. Never in production or any other environment.
     */
    public static function canSkip(): bool
    {
        return (bool) config('platform.skip_two_factor') && app()->environment(['local', 'demo']);
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function qrCodeSvg(PlatformAdmin $admin, string $secret): string
    {
        $uri = $this->google2fa->getQRCodeUrl('PawBook Admin', $admin->email, $secret);

        return (new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd)))->writeString($uri);
    }

    /**
     * Checks a code and refuses to accept the same code twice for the same admin (replay).
     */
    public function verify(PlatformAdmin $admin, string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== 6 || ! $this->google2fa->verifyKey($secret, $code, 1)) {
            return false;
        }

        return Cache::add('platform-2fa-used:'.$admin->getKey().':'.$code, true, 120);
    }
}
