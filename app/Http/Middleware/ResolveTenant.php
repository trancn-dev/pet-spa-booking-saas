<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private readonly CurrentTenant $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolve($request->getHost());

        abort_if($tenant === null, 404, 'Tenant not found.');

        $this->current->set($tenant);

        try {
            return $next($request);
        } finally {
            $this->current->forget();
        }
    }

    private function resolve(string $host): ?Tenant
    {
        $host = strtolower($host);

        $byDomain = Tenant::query()->where('domain', $host)->first();
        if ($byDomain !== null) {
            return $byDomain;
        }

        $suffix = '.'.strtolower((string) config('tenancy.central_domain'));
        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $slug = substr($host, 0, -strlen($suffix));
        if ($slug === '' || str_contains($slug, '.')) {
            return null;
        }

        return Tenant::query()->where('slug', $slug)->first();
    }
}
