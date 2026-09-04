<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Closure;
use RuntimeException;

/**
 * Which school the current request is working in.
 *
 * Bound as a singleton, so everything in one request — the global scope, the
 * services, the reference counters, the audit logger — agrees on the answer
 * without having to pass it down through every call.
 *
 * Nothing outside a request sets this implicitly. Seeders, exports and the
 * platform console say which school they mean with runFor().
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    /**
     * True while a platform owner is deliberately reading across schools.
     * Distinct from "no tenant set", which is a mistake rather than a mode.
     */
    private bool $unscoped = false;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->id;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function isUnscoped(): bool
    {
        return $this->unscoped;
    }

    /**
     * The tenant id, or a failure. Called where a missing tenant would mean
     * writing a row nobody owns — better a loud error than an orphan.
     */
    public function idOrFail(): string
    {
        return $this->id() ?? throw new RuntimeException(
            'No school is active for this request. Something tried to read or write '.
            'school data without saying which school it meant.'
        );
    }

    /**
     * Run a piece of work inside one school, then put back whatever was active
     * before. Used by the seeders, the exports, the platform console and any
     * job that has to act on a school it was told about rather than one a
     * session chose.
     */
    public function runFor(Tenant $tenant, Closure $work): mixed
    {
        $previous = $this->tenant;
        $previouslyUnscoped = $this->unscoped;

        $this->tenant = $tenant;
        $this->unscoped = false;

        try {
            return $work($tenant);
        } finally {
            $this->tenant = $previous;
            $this->unscoped = $previouslyUnscoped;
        }
    }

    /**
     * Read across every school at once. Only the platform console does this,
     * and only to count and summarise — never to write.
     */
    public function runUnscoped(Closure $work): mixed
    {
        $previous = $this->tenant;
        $previouslyUnscoped = $this->unscoped;

        $this->tenant = null;
        $this->unscoped = true;

        try {
            return $work();
        } finally {
            $this->tenant = $previous;
            $this->unscoped = $previouslyUnscoped;
        }
    }

    public function forget(): void
    {
        $this->tenant = null;
        $this->unscoped = false;
    }
}
