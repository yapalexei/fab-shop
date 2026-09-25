import { useEffect, useState } from 'react';

export type Theme = 'light' | 'dark';

const STORAGE_KEY = 'theme';

function systemPrefersDark(): boolean {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

/**
 * The explicit choice the user made, if any - absent means "follow the OS",
 * same as the inline script in app.blade.php that sets the initial class
 * before React ever mounts (see that file for why it has to be inline).
 */
export function getStoredTheme(): Theme | null {
    try {
        const value = localStorage.getItem(STORAGE_KEY);
        return value === 'light' || value === 'dark' ? value : null;
    } catch {
        // Private browsing / storage disabled - fall back to system.
        return null;
    }
}

export function currentTheme(): Theme {
    return getStoredTheme() ?? (systemPrefersDark() ? 'dark' : 'light');
}

export function applyTheme(theme: Theme): void {
    document.documentElement.classList.toggle('dark', theme === 'dark');
}

/**
 * Toggles between light and dark, persisting the choice - this is what
 * makes it "adjustable" rather than purely system-following from here on.
 */
export function useTheme() {
    const [theme, setTheme] = useState<Theme>(() => currentTheme());

    useEffect(() => {
        applyTheme(theme);
    }, [theme]);

    // Only listens while no explicit choice has been made - once the user
    // toggles, their choice sticks regardless of the OS setting.
    useEffect(() => {
        if (getStoredTheme() !== null) return;

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => setTheme(media.matches ? 'dark' : 'light');
        media.addEventListener('change', onChange);

        return () => media.removeEventListener('change', onChange);
    }, []);

    const toggleTheme = () => {
        const next: Theme = theme === 'dark' ? 'light' : 'dark';

        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // ignore - nothing to persist to
        }

        setTheme(next);
    };

    return { theme, toggleTheme };
}
