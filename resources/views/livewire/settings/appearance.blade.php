<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Appearance')" :subheading=" __('Update the appearance settings for your account')">
        <div
            class="join join-vertical lg:join-horizontal w-full"
            x-data="{ theme: themeManager.getTheme() }"
        >
            <input
                class="join-item btn"
                type="radio"
                name="appearance"
                aria-label="{{ __('Light') }}"
                value="light"
                x-model="theme"
                @change="themeManager.setTheme('light')"
            />
            <input
                class="join-item btn"
                type="radio"
                name="appearance"
                aria-label="{{ __('Dark') }}"
                value="dark"
                x-model="theme"
                @change="themeManager.setTheme('dark')"
            />
            <input
                class="join-item btn"
                type="radio"
                name="appearance"
                aria-label="{{ __('System') }}"
                value="system"
                x-model="theme"
                @change="themeManager.setTheme('system')"
            />
        </div>
    </x-settings.layout>
</section>
