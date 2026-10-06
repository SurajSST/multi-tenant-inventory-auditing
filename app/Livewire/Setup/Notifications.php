<?php

namespace App\Livewire\Setup;

use App\Services\NotificationSettingsService;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

class Notifications extends Component
{
    private const MAIL_PRESETS = [
        'gmail' => ['host' => 'smtp.gmail.com', 'port' => '587', 'encryption' => 'tls'],
        'outlook' => ['host' => 'smtp-mail.outlook.com', 'port' => '587', 'encryption' => 'tls'],
        'microsoft365' => ['host' => 'smtp.office365.com', 'port' => '587', 'encryption' => 'tls'],
        'brevo' => ['host' => 'smtp-relay.brevo.com', 'port' => '587', 'encryption' => 'tls'],
        'mailgun' => ['host' => 'smtp.mailgun.org', 'port' => '587', 'encryption' => 'tls'],
        'resend' => ['host' => 'smtp.resend.com', 'port' => '465', 'encryption' => 'ssl'],
        'custom' => [],
    ];

    public string $providerPreset = 'custom';

    public string $mailer = 'smtp';

    public string $host = '';

    public string $port = '587';

    public string $username = '';

    public string $password = '';

    public string $encryption = 'tls';

    public string $from_address = '';

    public string $from_name = '';

    public string $testAddress = '';

    public ?string $pushPublicKey = null;

    public string $pushError = '';

    public function mount(NotificationSettingsService $service): void
    {
        $settings = $service->mailSettings();
        foreach ($settings as $key => $value) {
            $this->{$key} = (string) ($value ?? $this->{$key});
        }
        $this->password = '';
        $this->pushPublicKey = $service->publicVapidKey();
        $this->providerPreset = match ($this->host) {
            'smtp.gmail.com' => 'gmail',
            'smtp-mail.outlook.com' => 'outlook',
            'smtp.office365.com' => 'microsoft365',
            'smtp-relay.brevo.com' => 'brevo',
            'smtp.mailgun.org' => 'mailgun',
            'smtp.resend.com' => 'resend',
            default => 'custom',
        };
    }

    public function updatedHost(): void
    {
        $this->providerPreset = collect(self::MAIL_PRESETS)
            ->search(fn (array $preset) => ($preset['host'] ?? null) === $this->host) ?: 'custom';
    }

    public function selectPreset(string $preset): void
    {
        if (! array_key_exists($preset, self::MAIL_PRESETS)) {
            return;
        }

        $this->providerPreset = $preset;

        if ($preset === 'custom') {
            return;
        }

        $this->mailer = 'smtp';
        $this->host = self::MAIL_PRESETS[$preset]['host'];
        $this->port = self::MAIL_PRESETS[$preset]['port'];
        $this->encryption = self::MAIL_PRESETS[$preset]['encryption'];

        if ($preset === 'resend') {
            $this->username = 'resend';
        }
    }

    public function save(NotificationSettingsService $service): void
    {
        $this->validate([
            'mailer' => ['required', 'in:smtp,log'], 'host' => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:mailer,smtp', 'nullable', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'], 'password' => ['nullable', 'string', 'max:1000'],
            'encryption' => ['nullable', 'in:tls,ssl,'], 'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ]);
        $service->saveMail($this->only(['mailer', 'host', 'port', 'username', 'password', 'encryption', 'from_address', 'from_name']), auth()->user());
        $this->password = '';
        $this->dispatch('toast', message: 'Mail settings saved.', tone: 'success', title: 'Mail settings saved');
    }

    public function test(NotificationSettingsService $service): void
    {
        $this->validate(['testAddress' => ['required', 'email', 'max:255']]);
        $service->testMail($this->testAddress);
        $this->dispatch('toast', message: 'Test email sent.', tone: 'success', title: 'Test email sent');
    }

    public function enablePush(NotificationSettingsService $service): void
    {
        $this->pushError = '';

        try {
            $service->ensureVapidKeys(auth()->user());
            $this->pushPublicKey = $service->publicVapidKey();
            $this->dispatch('toast', message: 'Push is configured. Enable this device from the notification bell.', tone: 'success', title: 'Push configured');
        } catch (Throwable $exception) {
            report($exception);
            $this->pushError = 'Push setup failed. '.$exception->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.setup.notifications')->title('Mail & Push Notifications');
    }
}
