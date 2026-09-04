<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Notifications\Channels\TenantDatabaseChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Everything this system tells somebody has the same shape: a school it
 * happened at, a line saying what happened, and somewhere to go and deal with
 * it.
 *
 * Two channels to start with. In-app costs nothing and needs no provider, and
 * staff are signed in anyway; email reaches whoever is not. A third channel —
 * SMS, which is how a Nepali school actually reaches people — is a matter of
 * adding it to via(), because none of the notification bodies below know or
 * care how they travel.
 *
 * Sent inline, NOT queued — a deliberate choice, and worth the paragraph.
 *
 * Queueing these looks obviously right and is a trap here. QUEUE_CONNECTION is
 * `database`, so a queued notification is a row in `jobs` that sits there until
 * somebody runs `php artisan queue:work`. On a school server under a desk,
 * nobody ever will — the whole feature would silently do nothing while every
 * test passed, because phpunit.xml forces the queue to `sync`.
 *
 * So: written immediately. A bell row is one INSERT and the mail driver is
 * `log`. If real SMTP is configured later and proves slow, the fix is a queue
 * worker plus ShouldQueue here — a decision to take with a worker actually
 * running, not before.
 *
 * Nothing is lost by not being afterCommit: Notifier is only ever called once
 * the transaction has already closed.
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
        return [TenantDatabaseChannel::class, 'mail'];
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
