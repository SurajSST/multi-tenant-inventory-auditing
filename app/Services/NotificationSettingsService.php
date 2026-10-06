<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use App\Tenancy\TenantContext;
use Base64Url\Base64Url;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class NotificationSettingsService
{
    private const MAIL_KEYS = ['mailer', 'host', 'port', 'username', 'password', 'encryption', 'from_address', 'from_name'];

    private array $baseMailConfig;

    public function __construct()
    {
        // Capture the application's configured defaults before any school's
        // settings are applied. The service is a singleton for the lifetime of
        // the app worker so one tenant can never become the next tenant's base.
        $this->baseMailConfig = config('mail');
    }

    public function mailSettings(): array
    {
        $rows = AppSetting::whereIn('key', array_map(fn ($k) => 'mail_'.$k, self::MAIL_KEYS))->get()->keyBy('key');
        $result = [];
        foreach (self::MAIL_KEYS as $key) {
            $value = $rows->get('mail_'.$key)?->value;
            $result[$key] = $key === 'password' ? (is_string($value) && $value !== '' ? Crypt::decryptString($value) : '') : $value;
        }

        return $result;
    }

    public function saveMail(array $input, User $actor): void
    {
        $tenantId = app(TenantContext::class)->idOrFail();
        DB::transaction(function () use ($input, $actor, $tenantId) {
            foreach (self::MAIL_KEYS as $key) {
                $value = $input[$key] ?? '';
                if ($key === 'password' && $value === '') {
                    continue;
                }
                if ($key === 'password' && $value !== '') {
                    $value = Crypt::encryptString($value);
                }
                AppSetting::updateOrCreate(['tenant_id' => $tenantId, 'key' => 'mail_'.$key], ['value' => $value]);
            }
            app(AuditLogger::class)->record(action: 'NOTIFICATION_MAIL_SETTINGS_CHANGED', entity: 'app_settings', entityId: null,
                detail: 'Mail delivery settings updated (credentials redacted).', actor: $actor);
        });
        $this->applyMailConfig();
    }

    public function applyMailConfig(): void
    {
        $settings = $this->mailSettings();
        // Restore the app defaults first. Without this reset, a long-lived
        // worker can accidentally send a later school's mail through the
        // previous school's SMTP account when no sender is configured here.
        config(['mail' => $this->baseMailConfig]);

        if (! $settings['from_address']) {
            Mail::purge();

            return;
        }
        config(['mail.default' => $settings['mailer'] ?: 'smtp']);
        config(['mail.mailers.smtp.host' => $settings['host']]);
        config(['mail.mailers.smtp.port' => (int) ($settings['port'] ?: 587)]);
        config(['mail.mailers.smtp.username' => $settings['username'] ?: null]);
        config(['mail.mailers.smtp.password' => $settings['password'] ?: null]);
        config(['mail.mailers.smtp.scheme' => $settings['encryption'] === 'ssl' ? 'smtps' : 'smtp']);
        config(['mail.mailers.smtp.auto_tls' => $settings['encryption'] === 'tls']);
        config(['mail.from.address' => $settings['from_address']]);
        config(['mail.from.name' => $settings['from_name']]);
        Mail::purge();
    }

    public function ensureVapidKeys(User $actor): void
    {
        $tenantId = app(TenantContext::class)->idOrFail();
        $keysExist = AppSetting::where('tenant_id', $tenantId)->whereIn('key', ['push_vapid_public', 'push_vapid_private'])->count() === 2;
        if ($keysExist) {
            return;
        }
        $keys = $this->createVapidKeys();
        DB::transaction(function () use ($tenantId, $keys, $actor) {
            AppSetting::updateOrCreate(['tenant_id' => $tenantId, 'key' => 'push_vapid_public'], ['value' => $keys['publicKey']]);
            AppSetting::updateOrCreate(['tenant_id' => $tenantId, 'key' => 'push_vapid_private'], ['value' => Crypt::encryptString($keys['privateKey'])]);
            app(AuditLogger::class)->record(action: 'PUSH_KEYS_GENERATED', entity: 'app_settings', entityId: null,
                detail: 'Web Push application keys generated.', actor: $actor);
        });
    }

    public function publicVapidKey(): ?string
    {
        $tenant = app(TenantContext::class);

        // The bell is rendered by the shared app layout, including while a
        // platform owner is outside any school's context. Never let that
        // global render query tenant settings (or reveal one school's key).
        if (! $tenant->has() || $tenant->isUnscoped()) {
            return null;
        }

        return AppSetting::where('key', 'push_vapid_public')->first()?->value;
    }

    /** Create the 65-byte uncompressed public key and 32-byte private scalar Web Push needs. */
    private function createVapidKeys(): array
    {
        $options = [
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        $configCandidates = array_filter([
            getenv('OPENSSL_CONF') ?: null,
            PHP_OS_FAMILY === 'Windows' ? dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf' : null,
            PHP_OS_FAMILY === 'Windows' ? dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'openssl'.DIRECTORY_SEPARATOR.'openssl.cnf' : null,
            '/etc/ssl/openssl.cnf',
            '/etc/pki/tls/openssl.cnf',
        ]);
        foreach ($configCandidates as $path) {
            if (is_file($path)) {
                $options['config'] = $path;
                break;
            }
        }

        $key = openssl_pkey_new($options);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if (! is_array($details) || ! isset($details['ec']['d'], $details['ec']['x'], $details['ec']['y'])) {
            $errors = [];
            while ($error = openssl_error_string()) {
                $errors[] = $error;
            }
            throw new RuntimeException('Could not generate Web Push keys. Check that OpenSSL is enabled and OPENSSL_CONF points to a readable OpenSSL configuration file.'.($errors ? ' '.implode(' ', $errors) : ''));
        }

        $pad = static fn (string $part): string => str_pad($part, 32, "\0", STR_PAD_LEFT);
        $publicKey = "\x04".$pad($details['ec']['x']).$pad($details['ec']['y']);

        return [
            'publicKey' => Base64Url::encode($publicKey),
            'privateKey' => Base64Url::encode($pad($details['ec']['d'])),
        ];
    }

    public function testMail(string $address): void
    {
        $settings = $this->mailSettings();
        if (! $settings['from_address'] || ! $settings['host'] || $settings['mailer'] === 'log') {
            throw ValidationException::withMessages(['testAddress' => 'Configure an SMTP host and sender address before testing delivery.']);
        }
        $this->applyMailConfig();
        try {
            Mail::raw('This is a test message from '.config('app.name').'.', function ($message) use ($address) {
                $message->to($address)->subject('Mail configuration test');
            });
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['testAddress' => 'Mail could not be sent: '.$e->getMessage()]);
        }
    }
}
