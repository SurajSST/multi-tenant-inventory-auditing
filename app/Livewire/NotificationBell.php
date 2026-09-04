<?php

namespace App\Livewire;

use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * What is waiting for you, at the school you are working in.
 *
 * Deliberately small: it shows what needs doing and links to the screen where
 * it gets done. Reading a notification is not the same as acting on it, so
 * opening one marks it read but changes nothing about the underlying work.
 */
class NotificationBell extends Component
{
    /** How many to show before sending people to the full list. */
    private const SHOWN = 8;

    public bool $open = false;

    #[Computed]
    public function items(): Collection
    {
        $user = auth()->user();

        if (! $user || ! app(TenantContext::class)->has()) {
            return collect();
        }

        return $user->notificationsHere()
            ->latest()
            ->limit(self::SHOWN)
            ->get();
    }

    #[Computed]
    public function unread(): int
    {
        $user = auth()->user();

        if (! $user || ! app(TenantContext::class)->has()) {
            return 0;
        }

        return $user->notificationsHere()
            ->whereNull('read_at')
            ->count();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
        unset($this->items, $this->unread);
    }

    public function markRead(string $id): void
    {
        auth()->user()?->notificationsHere()
            ->whereKey($id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        unset($this->items, $this->unread);
    }

    public function markAllRead(): void
    {
        auth()->user()?->notificationsHere()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        unset($this->items, $this->unread);
    }

    public function render(): View
    {
        return view('livewire.notification-bell');
    }
}
