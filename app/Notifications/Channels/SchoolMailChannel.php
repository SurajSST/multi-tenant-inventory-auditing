<?php

namespace App\Notifications\Channels;

use App\Notifications\SchoolNotification;
use App\Services\NotificationSettingsService;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;

/** Apply this school's SMTP settings only while sending its mail channel. */
class SchoolMailChannel
{
    public function __construct(
        private MailChannel $mail,
        private NotificationSettingsService $settings,
    ) {}

    public function send(object $notifiable, Notification $notification): mixed
    {
        if ($notification instanceof SchoolNotification) {
            $this->settings->applyMailConfig();
        }

        return $this->mail->send($notifiable, $notification);
    }
}
