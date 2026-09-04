<?php

namespace App\Notifications\Channels;

use App\Notifications\SchoolNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Laravel's database channel, plus the school the notification is about.
 *
 * The tenant is carried on the notification itself rather than read from the
 * request: these are queued, and a queued job has no session to ask. Whatever
 * school the work happened in is the school the row belongs to, however long
 * afterwards it is written.
 */
class TenantDatabaseChannel extends DatabaseChannel
{
    protected function buildPayload($notifiable, Notification $notification)
    {
        return array_merge(parent::buildPayload($notifiable, $notification), [
            'tenant_id' => $notification instanceof SchoolNotification
                ? $notification->tenantId
                : null,
        ]);
    }
}
