<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Notifications\Channels\SchoolMailChannel;
use App\Notifications\Channels\TenantDatabaseChannel;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Everything this system tells somebody has the same shape: a school it
 * happened at, a line saying what happened, and somewhere to go and deal with
 * it.
 *
 * In-app, browser push, and email share the same notification body. Push is
 * skipped until the school has VAPID keys and the recipient has subscribed;
 * email uses the school's SMTP settings when configured.
 *
 * The in-app bell is written immediately. Mail and Web Push are dispatched by
 * DeliverSchoolNotification after the response so slow providers do not hold
 * up the action that created this notification. The delivery job restores this
 * notification's tenant context before invoking tenant-specific channels.
 */
abstract class SchoolNotification extends Notification
{
    public string $tenantId;

    public string $schoolName;

    public function __construct(Tenant $tenant)
    {
        $this->tenantId = $tenant->id;
        $this->schoolName = $tenant->name;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [TenantDatabaseChannel::class, WebPushChannel::class, SchoolMailChannel::class];
    }

    /** The one-line summary, the same wording in the bell and in the email. */
    abstract public function headline(): string;

    /** What the reader is being asked to do about it. */
    abstract public function actionLabel(): string;

    /** Where that action goes. Relative, so it works behind whatever host. */
    abstract public function actionUrl(): string;

    /** Extra lines for the email body. Empty is fine — the headline may say it all. */
    public function details(): array
    {
        return [];
    }

    /**
     * The bell reads this. `type` is a short slug rather than the class name so
     * the view can pick an icon without knowing about PHP namespaces.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => class_basename(static::class),
            'headline' => $this->headline(),
            'action_label' => $this->actionLabel(),
            'action_url' => $this->actionUrl(),
            'school' => $this->schoolName,
            'details' => $this->details(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->schoolName.' — '.$this->headline())
            ->greeting('Hello '.$notifiable->full_name.',')
            ->line($this->headline());

        foreach ($this->details() as $line) {
            $mail->line($line);
        }

        return $mail
            ->action($this->actionLabel(), url($this->actionUrl()))
            ->salutation('— '.$this->schoolName);
    }
}
