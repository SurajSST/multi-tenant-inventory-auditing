<?php

namespace App\Notifications\Channels;

use App\Notifications\SchoolNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Laravel's database channel, plus the school the notification is about.
 *
 * The tenant is carried on the notification itself rather than inferred from
 * a global scope. That keeps the notification associated with the school the
 * work happened in, including when a shared user belongs to multiple schools.
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
