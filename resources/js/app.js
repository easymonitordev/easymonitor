// Theme management
const themeManager = {
    init() {
        this.applyTheme();

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (this.getTheme() === 'system') {
                this.applyTheme();
            }
        });

        // Listen for Livewire navigation events
        document.addEventListener('livewire:navigated', () => {
            this.applyTheme();
        });
    },

    getTheme() {
        return localStorage.getItem('theme') || 'system';
    },

    setTheme(theme) {
        localStorage.setItem('theme', theme);
        this.applyTheme();
    },

    applyTheme() {
        const theme = this.getTheme();
        const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-theme', isDark ? 'business' : 'corporate');
    }
};

// Initialize theme on page load
themeManager.init();

// Expose for Alpine.js
window.themeManager = themeManager;
