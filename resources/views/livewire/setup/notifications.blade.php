<div class="mx-auto max-w-7xl space-y-6">
    <x-page-header title="Mail & Push Notifications"
                   subtitle="Choose a mail provider, verify delivery, and enable browser notifications for this school.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('setup.index') }}" wire:navigate>Settings & Roles</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-errors />

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(19rem,0.8fr)]">
        <form wire:submit="save" class="space-y-6">
            <x-card title="SMTP configuration" subtitle="Settings are securely stored and encrypted in the database.">
                <x-slot:actions>
                    <div class="flex max-w-full flex-wrap items-center justify-end gap-1.5">
                        <span class="mr-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Presets:</span>
                        @foreach ([
                            'gmail' => 'Gmail',
                            'outlook' => 'Outlook',
                            'microsoft365' => 'Microsoft 365',
                            'brevo' => 'Brevo',
                            'mailgun' => 'Mailgun',
                            'resend' => 'Resend',
                            'custom' => 'Custom',
                        ] as $preset => $label)
                            <button type="button" wire:click="selectPreset('{{ $preset }}')"
                                    @class([
                                        'rounded-md border px-2 py-1 text-[11px] font-semibold leading-tight transition focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-sky-500',
                                        'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-300' => $providerPreset === $preset,
                                        'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:bg-white/[.03] dark:text-slate-300 dark:hover:bg-white/[.07]' => $providerPreset !== $preset,
                                    ])>{{ $label }}</button>
                        @endforeach
                    </div>
                </x-slot:actions>

                @if ($providerPreset === 'gmail')
                    <p class="mb-5 text-xs leading-relaxed text-slate-600 dark:text-slate-400">Use your full email address as the username. Google may require an app password with 2-Step Verification enabled. <a class="font-semibold text-indigo-600 underline underline-offset-2 dark:text-sky-400" href="https://support.google.com/mail/answer/185833?hl=en" target="_blank" rel="noopener noreferrer">App password help</a>.</p>
                @elseif ($providerPreset === 'outlook')
                    <p class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">Outlook.com requires Modern Auth/OAuth2. This SMTP screen uses username and password, so password-based sign-in may be rejected. <a class="font-semibold underline underline-offset-2" href="https://support.microsoft.com/en-us/outlook/pop-imap-and-smtp-settings-for-outlook-com" target="_blank" rel="noopener noreferrer">Provider details</a>.</p>
                @elseif ($providerPreset === 'microsoft365')
                    <p class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">Authenticated SMTP must be allowed for the mailbox and organization. OAuth-only organizations need a different mail integration. <a class="font-semibold underline underline-offset-2" href="https://learn.microsoft.com/en-us/exchange/mail-flow-best-practices/how-to-set-up-a-multifunction-device-or-application-to-send-email-using-microsoft-365-or-office-365" target="_blank" rel="noopener noreferrer">SMTP requirements</a>.</p>
                @elseif ($providerPreset === 'brevo')
                    <p class="mb-5 text-xs leading-relaxed text-slate-600 dark:text-slate-400">Use the SMTP login and SMTP key from Brevo settings. This is not your Brevo API key. <a class="font-semibold text-indigo-600 underline underline-offset-2 dark:text-sky-400" href="https://help.brevo.com/hc/en-us/articles/7924908994450-Send-transactional-emails-using-Brevo-SMTP" target="_blank" rel="noopener noreferrer">Brevo SMTP setup</a>.</p>
                @elseif ($providerPreset === 'mailgun')
                    <p class="mb-5 text-xs leading-relaxed text-slate-600 dark:text-slate-400">Use the SMTP credentials for your Mailgun sending domain. <a class="font-semibold text-indigo-600 underline underline-offset-2 dark:text-sky-400" href="https://documentation.mailgun.com/docs/mailgun/user-manual/sending-messages/send-smtp" target="_blank" rel="noopener noreferrer">Mailgun SMTP setup</a>.</p>
                @elseif ($providerPreset === 'resend')
                    <p class="mb-5 text-xs leading-relaxed text-slate-600 dark:text-slate-400">Username is set to <strong>resend</strong>. Use a Resend API key as the password and a verified sender address. <a class="font-semibold text-indigo-600 underline underline-offset-2 dark:text-sky-400" href="https://resend.com/docs/send-with-smtp" target="_blank" rel="noopener noreferrer">Resend SMTP setup</a>.</p>
                @else
                    <p class="mb-5 text-xs leading-relaxed text-slate-600 dark:text-slate-400">Choose a preset to fill recommended server details, or enter SMTP settings from your email provider.</p>
                @endif

                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Connection</h3>
                <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                    <x-field label="Mailer" for="mailer" required :error="$errors->first('mailer')">
                        <x-select id="mailer" wire:model.live="mailer">
                            <option value="smtp">SMTP</option>
                            <option value="log">Log (development only)</option>
                        </x-select>
                    </x-field>

                    @if ($mailer === 'smtp')
                        <x-field label="SMTP host" for="host" required :error="$errors->first('host')">
                            <x-input id="host" wire:model="host" placeholder="smtp.example.com" autocomplete="off" />
                        </x-field>
                        <x-field label="Port" for="port" required :error="$errors->first('port')">
                            <x-input id="port" type="number" min="1" max="65535" wire:model="port" placeholder="587" />
                        </x-field>
                        <x-field label="Encryption" for="encryption" :error="$errors->first('encryption')">
                            <x-select id="encryption" wire:model="encryption">
                                <option value="tls">STARTTLS (recommended)</option>
                                <option value="ssl">SSL</option>
                                <option value="">None</option>
                            </x-select>
                        </x-field>
                        <x-field label="Username" for="username" :error="$errors->first('username')">
                            <x-input id="username" wire:model="username" autocomplete="off" placeholder="SMTP username" />
                        </x-field>
                        <x-field label="Password" for="password" hint="Blank keeps the saved password." :error="$errors->first('password')">
                            <x-input id="password" type="password" wire:model="password" autocomplete="new-password" placeholder="SMTP or app password" />
                        </x-field>
                    @else
                        <div class="sm:col-span-1 flex items-center rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-3 text-xs leading-relaxed text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                            Log mailer writes messages to the application log. Use it only for development.
                        </div>
                    @endif
                </div>

                <div class="my-6 border-t border-slate-200 dark:border-white/10"></div>

                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Sender identity</h3>
                <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                    <x-field label="From address" for="from_address" required :error="$errors->first('from_address')">
                        <x-input id="from_address" type="email" wire:model="from_address" placeholder="inventory@example.com" autocomplete="email" />
                    </x-field>
                    <x-field label="From name" for="from_name" required :error="$errors->first('from_name')">
                        <x-input id="from_name" wire:model="from_name" placeholder="School name" />
                    </x-field>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-white/10">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Changes apply to future email notifications.</p>
                    <x-button type="submit" busy="save">
                        <span wire:loading.remove wire:target="save">Save mail settings</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </x-button>
                </div>
            </x-card>
        </form>

        <div class="space-y-6">
            <form wire:submit="test">
                <x-card title="Test email" subtitle="Confirm that the saved mail configuration can deliver.">
                    <p class="mb-4 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Save the mail settings first. The test is sent to one address and does not notify other staff.</p>
                    <div class="space-y-4">
                        <x-field label="Recipient address" for="testAddress" required :error="$errors->first('testAddress')">
                            <x-input id="testAddress" type="email" wire:model="testAddress" placeholder="you@example.com" autocomplete="email" />
                        </x-field>
                        <x-button type="submit" variant="secondary" busy="test" class="w-full justify-center">
                            <span wire:loading.remove wire:target="test">Send test email</span>
                            <span wire:loading wire:target="test">Sending…</span>
                        </x-button>
                    </div>
                    <p class="mt-4 border-t border-slate-200 pt-3 text-[11px] leading-relaxed text-slate-500 dark:border-white/10 dark:text-slate-400">
                        Delivery failures are logged with the school, recipient, and notification type. A failure for one recipient does not stop delivery to others.
                    </p>
                </x-card>
            </form>

            <x-card title="Browser push" subtitle="Web Push for supported browsers and installed PWAs.">
                <div class="flex items-start gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl {{ $pushPublicKey ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300' }}" aria-hidden="true">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 18.75a2.25 2.25 0 0 1-4.5 0m10.5-1.5H3.75l1.62-1.62A2.25 2.25 0 0 0 6 14.04V9a6 6 0 1 1 12 0v5.04c0 .597.237 1.169.659 1.591L20.25 17.25Z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $pushPublicKey ? 'Push is configured' : 'Push setup is required' }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            {{ $pushPublicKey ? 'Users can enable notifications for this school from the notification bell on each device.' : 'Generate this school’s VAPID keys to enable secure browser push subscriptions.' }}
                        </p>
                    </div>
                </div>

                @if ($pushError)
                    <p role="alert" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs leading-relaxed text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">{{ $pushError }}</p>
                @endif

                @if (! $pushPublicKey)
                    <x-button type="button" wire:click="enablePush" busy="enablePush" class="mt-4 w-full justify-center">
                        <span wire:loading.remove wire:target="enablePush">Set up push notifications</span>
                        <span wire:loading wire:target="enablePush">Setting up…</span>
                    </x-button>
                @endif

                <div class="mt-5 border-t border-slate-200 pt-4 dark:border-white/10">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Platform requirements</p>
                    <ul class="mt-2 space-y-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                        <li class="flex gap-2"><span class="text-emerald-500">✓</span><span>Android and desktop browsers that support Web Push.</span></li>
                        <li class="flex gap-2"><span class="text-emerald-500">✓</span><span>iPhone and iPad on iOS/iPadOS 16.4 or later, after adding the site to the Home Screen.</span></li>
                        <li class="flex gap-2"><span class="text-amber-500">!</span><span>HTTPS is required outside localhost. Users must allow notifications on each device.</span></li>
                    </ul>
                </div>
            </x-card>
        </div>
    </div>
</div>
