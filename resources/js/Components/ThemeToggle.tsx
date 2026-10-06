import { Button } from '@/Components/ui/button';
import { useTheme } from '@/lib/theme';
import { Moon, Sun } from 'lucide-react';

export default function ThemeToggle({
    className = '',
}: {
    className?: string;
}) {
    const { theme, toggleTheme } = useTheme();
    const isDark = theme === 'dark';
    const label = isDark ? 'Switch to light theme' : 'Switch to dark theme';

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={toggleTheme}
            aria-label={label}
            title={label}
            className={className}
        >
            {isDark ? <Sun /> : <Moon />}
        </Button>
    );
}
