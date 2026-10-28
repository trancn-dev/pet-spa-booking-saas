<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * A shop's slug is its address: `/app/{slug}` for the CMS and `/{slug}` for its Site (task 7.1),
 * so it can never be the first segment of a central route. `ShopSlugTest` scans the route table
 * to keep this list complete when routes are added.
 */
final class ShopSlug
{
    public const RESERVED = [
        // routes that exist today
        'admin', 'app', 'invite', 'login', 'logout', 'register', 'dashboard', 'settings',
        'forgot-password', 'reset-password', 'verify-email', 'confirm-password', 'email',
        'storage', 'build', 'up', 'impersonation',
        // kept free for later or for infrastructure
        'api', 'www', 'site', 'help', 'support', 'docs', 'blog', 'status', 'pricing', 'about',
        'terms', 'privacy', 'contact', 'mail', 'static', 'assets', 'public', 'webhooks', 'health',
        'pawbook', 'account', 'billing', 'signup', 'sign-in', 'auth', 'oauth', 'otp', 'booking',
        'favicon.ico', 'robots.txt', 'sitemap.xml',
    ];

    /**
     * @return list<mixed>
     */
    public static function rules(): array
    {
        return [
            'required', 'string', 'min:3', 'max:63',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::notIn(self::RESERVED),
            Rule::unique('tenants', 'slug'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $attribute = 'slug'): array
    {
        return [
            "{$attribute}.regex" => 'Chỉ dùng chữ thường không dấu, số và dấu gạch ngang.',
            "{$attribute}.not_in" => 'Địa chỉ này đã được hệ thống dùng, chọn tên khác.',
            "{$attribute}.unique" => 'Địa chỉ này đã có shop dùng.',
        ];
    }

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, self::RESERVED, true);
    }
}
