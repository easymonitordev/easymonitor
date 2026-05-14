<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Notifications')" :subheading="__('Configure how you want to be alerted when monitors go down or recover')">
        <div class="my-6 space-y-8">
            <!-- Channels list -->
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50 mb-3">{{ __('Your channels') }}</h3>

                <div class="space-y-2">
                    @foreach ($channels as $channel)
                        <div wire:key="channel-{{ $channel->id }}"
                             class="card bg-base-100 border border-base-300">
                            <div class="card-body py-4 flex-row items-center justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-medium">{{ $channel->type->label() }}</span>
                                        @if ($channel->label)
                                            <span class="text-base-content/60 text-sm">— {{ $channel->label }}</span>
                                        @endif
                                        @if ($channel->is_default)
                                            <span class="badge badge-primary badge-sm">{{ __('Default') }}</span>
                                        @endif
                                        @if (! $channel->is_active)
                                            <span class="badge badge-ghost badge-sm">{{ __('Inactive') }}</span>
                                        @elseif (! $channel->isConfigured())
                                            <span class="badge badge-warning badge-sm">{{ __('Not configured') }}</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-base-content/60 mt-0.5">
                                        @switch ($channel->type)
                                            @case (\App\Enums\NotificationChannelType::Email)
                                                {{ auth()->user()->email }}
                                                @break
                                            @case (\App\Enums\NotificationChannelType::Pushover)
                                                {{ __('User key set') }}{{ ($channel->config['device'] ?? null) ? ' · '.$channel->config['device'] : '' }}
                                                @break
                                            @case (\App\Enums\NotificationChannelType::Slack)
                                                {{ __('Webhook configured') }}
                                                @break
                                            @case (\App\Enums\NotificationChannelType::Discord)
                                                {{ __('Webhook configured') }}
                                                @break
                                            @case (\App\Enums\NotificationChannelType::Webhook)
                                                {{ __('Endpoint configured') }}
                                                @break
                                        @endswitch
                                    </p>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    @if (! $channel->is_default && $channel->is_active && $channel->isConfigured())
                                        <button type="button"
                                                wire:click="setDefault({{ $channel->id }})"
                                                class="btn btn-ghost btn-sm">
                                            {{ __('Make default') }}
                                        </button>
                                    @endif

                                    @if ($channel->is_active && $channel->isConfigured())
                                        <div x-data="{ sent: false, sending: false }"
                                             x-on:notifications-test-sent.window="if ($event.detail.channelId === {{ $channel->id }}) { sent = true; setTimeout(() => sent = false, 2500); }"
                                             class="flex items-center gap-2">
                                            <span x-show="sent" x-transition.opacity class="text-sm text-success" style="display:none">
                                                {{ __('Test sent') }}
                                            </span>
                                            <button type="button"
                                                    x-on:click="sending = true; $wire.sendTest({{ $channel->id }}).finally(() => sending = false)"
                                                    x-bind:disabled="sending"
                                                    class="btn btn-outline btn-sm">
                                                <span x-show="! sending">{{ __('Send test') }}</span>
                                                <span x-show="sending" style="display:none">{{ __('Sending…') }}</span>
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @error('test')
                    <p class="text-error text-sm mt-2">{{ $message }}</p>
                @enderror
            </div>

            <!-- Slack configuration -->
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body gap-5">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Slack') }}</h3>
                        <p class="text-xs text-base-content/60 mt-1">
                            {{ __('Create an') }}
                            <a href="https://api.slack.com/messaging/webhooks" target="_blank" rel="noopener" class="link link-primary">{{ __('incoming webhook') }}</a>
                            {{ __('for each Slack channel you want alerts delivered to. Add as many as you need — when creating a monitor you can pick which ones to alert.') }}
                        </p>
                    </div>

                    @foreach ($slackChannels as $existing)
                        <form wire:key="slack-edit-{{ $existing->id }}"
                              wire:submit="saveSlackChannel({{ $existing->id }})"
                              class="border border-base-300 rounded-lg p-4 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="form-control">
                                    <label class="label pb-1">
                                        <span class="label-text font-medium text-sm">{{ __('Label') }}</span>
                                    </label>
                                    <input type="text"
                                           wire:model="slackEdits.{{ $existing->id }}.label"
                                           maxlength="50"
                                           class="input input-bordered input-sm rounded-lg @error('slackEdits.'.$existing->id.'.label') input-error @enderror"
                                           placeholder="#alerts-api" />
                                    @error('slackEdits.'.$existing->id.'.label')
                                        <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-control sm:col-span-2">
                                    <label class="label pb-1">
                                        <span class="label-text font-medium text-sm">{{ __('Webhook URL') }}</span>
                                    </label>
                                    <input type="url"
                                           wire:model="slackEdits.{{ $existing->id }}.webhook_url"
                                           maxlength="500"
                                           autocomplete="off"
                                           class="input input-bordered input-sm rounded-lg font-mono text-xs @error('slackEdits.'.$existing->id.'.webhook_url') input-error @enderror"
                                           placeholder="https://hooks.slack.com/services/..." />
                                    @error('slackEdits.'.$existing->id.'.webhook_url')
                                        <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <label class="cursor-pointer flex items-center gap-2">
                                    <input type="checkbox"
                                           wire:model="slackEdits.{{ $existing->id }}.is_active"
                                           class="toggle toggle-success toggle-sm" />
                                    <span class="label-text text-sm">{{ __('Active') }}</span>
                                </label>

                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            wire:click="deleteSlackChannel({{ $existing->id }})"
                                            wire:confirm="{{ __('Delete this Slack channel?') }}"
                                            class="btn btn-ghost btn-sm text-error">
                                        {{ __('Delete') }}
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-sm rounded-lg">{{ __('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    @endforeach

                    <form wire:submit="addSlackChannel" class="border border-dashed border-base-300 rounded-lg p-4 space-y-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-base-content/50">
                            {{ $slackChannels->isEmpty() ? __('Add your first Slack channel') : __('Add another Slack channel') }}
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="form-control">
                                <label class="label pb-1">
                                    <span class="label-text font-medium text-sm">{{ __('Label') }}</span>
                                </label>
                                <input type="text"
                                       wire:model="newSlackLabel"
                                       maxlength="50"
                                       class="input input-bordered input-sm rounded-lg @error('newSlackLabel') input-error @enderror"
                                       placeholder="#alerts-api" />
                                @error('newSlackLabel')
                                    <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-control sm:col-span-2">
                                <label class="label pb-1">
                                    <span class="label-text font-medium text-sm">{{ __('Webhook URL') }}</span>
                                </label>
                                <input type="url"
                                       wire:model="newSlackWebhookUrl"
                                       maxlength="500"
                                       autocomplete="off"
                                       class="input input-bordered input-sm rounded-lg font-mono text-xs @error('newSlackWebhookUrl') input-error @enderror"
                                       placeholder="https://hooks.slack.com/services/..." />
                                @error('newSlackWebhookUrl')
                                    <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <label class="cursor-pointer flex items-center gap-2">
                                <input type="checkbox" wire:model="newSlackActive" class="toggle toggle-success toggle-sm" />
                                <span class="label-text text-sm">{{ __('Active') }}</span>
                            </label>

                            <div class="flex items-center gap-3">
                                <x-action-message on="notifications-saved">
                                    {{ __('Saved.') }}
                                </x-action-message>
                                <button type="submit" class="btn btn-primary btn-sm rounded-lg">{{ __('Add Slack channel') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Discord configuration -->
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body gap-5">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Discord') }}</h3>
                        <p class="text-xs text-base-content/60 mt-1">
                            {{ __('In your Discord server settings, open') }}
                            <span class="font-mono text-xs">{{ __('Integrations → Webhooks → New Webhook') }}</span>,
                            {{ __('pick a channel, and copy the URL. Add as many as you need.') }}
                        </p>
                    </div>

                    @foreach ($discordChannels as $existing)
                        <form wire:key="discord-edit-{{ $existing->id }}"
                              wire:submit="saveDiscordChannel({{ $existing->id }})"
                              class="border border-base-300 rounded-lg p-4 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="form-control">
                                    <label class="label pb-1">
                                        <span class="label-text font-medium text-sm">{{ __('Label') }}</span>
                                    </label>
                                    <input type="text"
                                           wire:model="discordEdits.{{ $existing->id }}.label"
                                           maxlength="50"
                                           class="input input-bordered input-sm rounded-lg @error('discordEdits.'.$existing->id.'.label') input-error @enderror"
                                           placeholder="#alerts" />
                                    @error('discordEdits.'.$existing->id.'.label')
                                        <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-control sm:col-span-2">
                                    <label class="label pb-1">
                                        <span class="label-text font-medium text-sm">{{ __('Webhook URL') }}</span>
                                    </label>
                                    <input type="url"
                                           wire:model="discordEdits.{{ $existing->id }}.webhook_url"
                                           maxlength="500"
                                           autocomplete="off"
                                           class="input input-bordered input-sm rounded-lg font-mono text-xs @error('discordEdits.'.$existing->id.'.webhook_url') input-error @enderror"
                                           placeholder="https://discord.com/api/webhooks/..." />
                                    @error('discordEdits.'.$existing->id.'.webhook_url')
                                        <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <label class="cursor-pointer flex items-center gap-2">
                                    <input type="checkbox"
                                           wire:model="discordEdits.{{ $existing->id }}.is_active"
                                           class="toggle toggle-success toggle-sm" />
                                    <span class="label-text text-sm">{{ __('Active') }}</span>
                                </label>

                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            wire:click="deleteDiscordChannel({{ $existing->id }})"
                                            wire:confirm="{{ __('Delete this Discord channel?') }}"
                                            class="btn btn-ghost btn-sm text-error">
                                        {{ __('Delete') }}
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-sm rounded-lg">{{ __('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    @endforeach

                    <form wire:submit="addDiscordChannel" class="border border-dashed border-base-300 rounded-lg p-4 space-y-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-base-content/50">
                            {{ $discordChannels->isEmpty() ? __('Add your first Discord channel') : __('Add another Discord channel') }}
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="form-control">
                                <label class="label pb-1">
                                    <span class="label-text font-medium text-sm">{{ __('Label') }}</span>
                                </label>
                                <input type="text"
                                       wire:model="newDiscordLabel"
                                       maxlength="50"
                                       class="input input-bordered input-sm rounded-lg @error('newDiscordLabel') input-error @enderror"
                                       placeholder="#alerts" />
                                @error('newDiscordLabel')
                                    <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-control sm:col-span-2">
                                <label class="label pb-1">
                                    <span class="label-text font-medium text-sm">{{ __('Webhook URL') }}</span>
                                </label>
                                <input type="url"
                                       wire:model="newDiscordWebhookUrl"
                                       maxlength="500"
                                       autocomplete="off"
                                       class="input input-bordered input-sm rounded-lg font-mono text-xs @error('newDiscordWebhookUrl') input-error @enderror"
                                       placeholder="https://discord.com/api/webhooks/..." />
                                @error('newDiscordWebhookUrl')
                                    <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <label class="cursor-pointer flex items-center gap-2">
                                <input type="checkbox" wire:model="newDiscordActive" class="toggle toggle-success toggle-sm" />
                                <span class="label-text text-sm">{{ __('Active') }}</span>
                            </label>

                            <div class="flex items-center gap-3">
                                <x-action-message on="notifications-saved">
                                    {{ __('Saved.') }}
                                </x-action-message>
                                <button type="submit" class="btn btn-primary btn-sm rounded-lg">{{ __('Add Discord channel') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Generic Webhook configuration -->
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body gap-5">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Webhook') }}</h3>
                        <p class="text-xs text-base-content/60 mt-1">
                            {{ __('POST a JSON payload to any HTTP endpoint when a monitor goes down or recovers. Each webhook is signed with HMAC-SHA256; verify the') }}
                            <code class="text-xs">X-EasyMonitor-Signature</code>
                            {{ __('header using the secret shown after saving.') }}
                        </p>
                    </div>

                    @foreach ($webhookChannels as $existing)
                        <form wire:key="webhook-edit-{{ $existing->id }}"
                              wire:submit="saveWebhookChannel({{ $existing->id }})"
                              class="border border-base-300 rounded-lg p-4 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="form-control">
                                    <label class="label pb-1">
                                        <span class="label-text font-medium text-sm">{{ __('Label') }}</span>
                                    </label>
                                    <input type="text"
                                           wire:model="webhookEdits.{{ $existing->id }}.label"
                                           maxlength="50"
                                           class="input input-bordered input-sm rounded-lg @error('webhookEdits.'.$existing->id.'.label') input-error @enderror"
                                           placeholder="PagerDuty / Zapier / …" />
                                    @error('webhookEdits.'.$existing->id.'.label')
                                        <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-control sm:col-span-2">
                                    <label class="label pb-1">
                                        <span class="label-text font-medium text-sm">{{ __('Endpoint URL') }}</span>
                                    </label>
                                    <input type="url"
                                           wire:model="webhookEdits.{{ $existing->id }}.url"
                                           maxlength="500"
                                           autocomplete="off"
                                           class="input input-bordered input-sm rounded-lg font-mono text-xs @error('webhookEdits.'.$existing->id.'.url') input-error @enderror"
                                           placeholder="https://example.com/hooks/easymonitor" />
                                    @error('webhookEdits.'.$existing->id.'.url')
                                        <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-control" x-data="{ copied: false }">
                                <label class="label pb-1">
                                    <span class="label-text font-medium text-sm">{{ __('Signing secret') }}</span>
                                    <span class="label-text-alt text-base-content/50 text-xs">
                                        {{ __('Use to verify') }} <code class="text-xs">X-EasyMonitor-Signature: sha256=hex(hmac(secret, body))</code>
                                    </span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <input type="text"
                                           readonly
                                           value="{{ $existing->config['secret'] ?? '' }}"
                                           class="input input-bordered input-sm rounded-lg font-mono text-xs flex-1" />
                                    <button type="button"
                                            x-on:click="navigator.clipboard.writeText('{{ $existing->config['secret'] ?? '' }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                            class="btn btn-ghost btn-sm">
                                        <span x-show="! copied">{{ __('Copy') }}</span>
                                        <span x-show="copied" class="text-success" style="display:none">{{ __('Copied') }}</span>
                                    </button>
                                    <button type="button"
                                            wire:click="regenerateWebhookSecret({{ $existing->id }})"
                                            wire:confirm="{{ __('Regenerate the signing secret? Receivers using the old secret will reject deliveries until updated.') }}"
                                            class="btn btn-ghost btn-sm">
                                        {{ __('Regenerate') }}
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <label class="cursor-pointer flex items-center gap-2">
                                    <input type="checkbox"
                                           wire:model="webhookEdits.{{ $existing->id }}.is_active"
                                           class="toggle toggle-success toggle-sm" />
                                    <span class="label-text text-sm">{{ __('Active') }}</span>
                                </label>

                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            wire:click="deleteWebhookChannel({{ $existing->id }})"
                                            wire:confirm="{{ __('Delete this webhook?') }}"
                                            class="btn btn-ghost btn-sm text-error">
                                        {{ __('Delete') }}
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-sm rounded-lg">{{ __('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    @endforeach

                    <form wire:submit="addWebhookChannel" class="border border-dashed border-base-300 rounded-lg p-4 space-y-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-base-content/50">
                            {{ $webhookChannels->isEmpty() ? __('Add your first webhook') : __('Add another webhook') }}
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="form-control">
                                <label class="label pb-1">
                                    <span class="label-text font-medium text-sm">{{ __('Label') }}</span>
                                </label>
                                <input type="text"
                                       wire:model="newWebhookLabel"
                                       maxlength="50"
                                       class="input input-bordered input-sm rounded-lg @error('newWebhookLabel') input-error @enderror"
                                       placeholder="PagerDuty / Zapier / …" />
                                @error('newWebhookLabel')
                                    <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-control sm:col-span-2">
                                <label class="label pb-1">
                                    <span class="label-text font-medium text-sm">{{ __('Endpoint URL') }}</span>
                                </label>
                                <input type="url"
                                       wire:model="newWebhookUrl"
                                       maxlength="500"
                                       autocomplete="off"
                                       class="input input-bordered input-sm rounded-lg font-mono text-xs @error('newWebhookUrl') input-error @enderror"
                                       placeholder="https://example.com/hooks/easymonitor" />
                                @error('newWebhookUrl')
                                    <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <label class="cursor-pointer flex items-center gap-2">
                                <input type="checkbox" wire:model="newWebhookActive" class="toggle toggle-success toggle-sm" />
                                <span class="label-text text-sm">{{ __('Active') }}</span>
                            </label>

                            <div class="flex items-center gap-3">
                                <x-action-message on="notifications-saved">
                                    {{ __('Saved.') }}
                                </x-action-message>
                                <button type="submit" class="btn btn-primary btn-sm rounded-lg">{{ __('Add webhook') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Pushover configuration -->
            <form wire:submit="savePushover" class="card bg-base-100 border border-base-300">
                <div class="card-body gap-5">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Pushover') }}</h3>
                        <p class="text-xs text-base-content/60 mt-1">
                            {{ __('Find your user key on') }}
                            <a href="https://pushover.net/" target="_blank" rel="noopener" class="link link-primary">pushover.net</a>.
                            {{ __('Leave the user key blank to disconnect Pushover.') }}
                        </p>
                    </div>

                    <div class="form-control">
                        <label class="label pb-1">
                            <span class="label-text font-medium">{{ __('User Key') }}</span>
                        </label>
                        <input type="text"
                               wire:model="pushoverUserKey"
                               maxlength="30"
                               autocomplete="off"
                               class="input input-bordered w-full rounded-lg @error('pushoverUserKey') input-error @enderror"
                               placeholder="uQiRzpo4DXghDmr9QzzfQu27cmVRsG" />
                        @error('pushoverUserKey')
                            <div class="label pb-0">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </div>
                        @enderror
                    </div>

                    <div class="form-control">
                        <label class="label pb-1">
                            <span class="label-text font-medium">{{ __('Device') }}</span>
                            <span class="label-text-alt text-base-content/50">{{ __('Optional') }}</span>
                        </label>
                        <input type="text"
                               wire:model="pushoverDevice"
                               maxlength="50"
                               autocomplete="off"
                               class="input input-bordered w-full rounded-lg @error('pushoverDevice') input-error @enderror"
                               placeholder="iphone" />
                        <div class="label pb-0">
                            <span class="label-text-alt text-base-content/50">{{ __('Restrict notifications to a specific device. Leave blank to send to all.') }}</span>
                        </div>
                        @error('pushoverDevice')
                            <div class="label pb-0">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </div>
                        @enderror
                    </div>

                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-4 py-2">
                            <input type="checkbox" wire:model="pushoverActive" class="toggle toggle-success" />
                            <span class="label-text">{{ __('Active') }}</span>
                        </label>
                    </div>

                    <div class="flex items-center gap-4 justify-end">
                        <x-action-message class="me-3" on="notifications-saved">
                            {{ __('Saved.') }}
                        </x-action-message>
                        <button type="submit" class="btn btn-primary rounded-lg">{{ __('Save') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </x-settings.layout>
</section>
