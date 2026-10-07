<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\Channels\SchoolMailChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\SchoolNotification;
use App\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class DeliverSchoolNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public string $tenantId,
        public string $recipientId,
        public SchoolNotification $notification,
    ) {
        $this->onConnection(config('notification_delivery.connection'));
        $this->onQueue(config('notification_delivery.queue'));
    }

    public function handle(TenantContext $context): void
    {
        $tenant = Tenant::find($this->tenantId);
        $recipient = User::find($this->recipientId);

        if (! $tenant || ! $recipient || ! $recipient->is_active) {
            return;
        }

        $stillWorksHere = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $recipient->id)
            ->where('is_active', true)
            ->exists();

        if (! $stillWorksHere) {
            return;
        }

        try {
            $context->runFor($tenant, function () use ($recipient): void {
                Notification::sendNow($recipient, $this->notification, [
                    WebPushChannel::class,
                    SchoolMailChannel::class,
                ]);
            });
        } catch (Throwable $exception) {
            Log::warning('Queued school notification delivery failed.', [
                'exception' => $exception,
                'tenant_id' => $this->tenantId,
                'recipient_id' => $this->recipientId,
                'notification' => $this->notification::class,
            ]);

            throw $exception;
        }
    }
}
