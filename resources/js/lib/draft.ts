/**
 * Unsaved-form drafts in localStorage, so leaving a long form (to create
 * something it needs, say) doesn't lose the work. Storage can be missing,
 * full or blocked (private windows), so every call is best-effort: a
 * failure just means no draft.
 */
export function readDraft(
    key: string,
): { data: unknown; savedAt: number } | null {
    try {
        const raw = localStorage.getItem(key);
        if (!raw) return null;
        const parsed = JSON.parse(raw);

        return parsed && typeof parsed.savedAt === 'number' && 'data' in parsed
            ? parsed
            : null;
    } catch {
        return null;
    }
}

export function writeDraft(key: string, data: unknown): void {
    try {
        localStorage.setItem(
            key,
            JSON.stringify({ data, savedAt: Date.now() }),
        );
    } catch {
        // Not saved; nothing to do.
    }
}

export function clearDraft(key: string): void {
    try {
        localStorage.removeItem(key);
    } catch {
        // Nothing to clear.
    }
}
