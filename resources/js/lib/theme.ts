import { useEffect, useState } from 'react';

export type Theme = 'light' | 'dark';

const STORAGE_KEY = 'theme';

/**
 * The explicit choice the user made, if any - absent means the default,
 * light, same as the inline script in app.blade.php that sets the initial
 * class before React ever mounts (see that file for why it has to be inline).
 */
export function getStoredTheme(): Theme | null {
    try {
        const value = localStorage.getItem(STORAGE_KEY);
        return value === 'light' || value === 'dark' ? value : null;
    } catch {
        // Private browsing / storage disabled - fall back to the default.
        return null;
    }
}

export function currentTheme(): Theme {
    return getStoredTheme() ?? 'light';
}

export function applyTheme(theme: Theme): void {
    document.documentElement.classList.toggle('dark', theme === 'dark');
}

/**
 * Toggles between light and dark, persisting the choice - this is what
 * makes it "adjustable" rather than always light.
 */
export function useTheme() {
    const [theme, setTheme] = useState<Theme>(() => currentTheme());

    useEffect(() => {
        applyTheme(theme);
    }, [theme]);

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
