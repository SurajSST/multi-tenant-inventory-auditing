<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use App\Notifications\SchoolNotification;
use App\Services\SettingService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof SchoolNotification) {
            return;
        }

        $settings = app(SettingService::class);
        $publicKey = $settings->get('push_vapid_public');
        $privateKey = $settings->get('push_vapid_private');
        if (! $publicKey || ! $privateKey) {
            return;
        }

        try {
            $auth = new WebPush(['VAPID' => [
                'subject' => config('app.url'),
                'publicKey' => $publicKey,
                'privateKey' => Crypt::decryptString($privateKey),
            ]]);
            $rows = PushSubscription::where('tenant_id', $notification->tenantId)
                ->where('user_id', $notifiable->id)->get();
            foreach ($rows as $row) {
                $auth->queueNotification(Subscription::create([
                    'endpoint' => $row->endpoint,
                    'publicKey' => $row->public_key,
                    'authToken' => Crypt::decryptString($row->auth_token),
                    'contentEncoding' => $row->content_encoding,
                ]), json_encode([
                    'title' => $notification->schoolName,
                    'body' => $notification->headline(),
                    'url' => url($notification->actionUrl()),
                ], JSON_THROW_ON_ERROR));
            }
            foreach ($auth->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                } elseif (! $report->isSuccess()) {
                    Log::warning('Web push delivery failed: '.$report->getReason());
                }
            }
        } catch (Throwable $e) {
            Log::warning('Web push delivery failed: '.$e->getMessage());
        }
    }
}
