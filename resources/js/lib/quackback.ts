/**
 * Keeps the feedback widget's idea of "who is this" in step with the Laravel
 * session. The launcher itself is added by app.blade.php (only when
 * QUACKBACK_URL is set); this only decides when to identify or log out.
 *
 * Identity is never sent from here: the browser asks the server, which signs
 * a short-lived ssoToken from the session (QuackbackTokenController).
 */

const IDENTIFIED_KEY = 'quackback:identified';

let identifiedThisLoad: number | null = null;
let requesting = false;

const read = (): string | null => {
    try {
        return window.localStorage.getItem(IDENTIFIED_KEY);
    } catch {
        return null;
    }
};

const write = (value: number | null) => {
    try {
        if (value === null) {
            window.localStorage.removeItem(IDENTIFIED_KEY);
        } else {
            window.localStorage.setItem(IDENTIFIED_KEY, String(value));
        }
    } catch {
        // Storage can be blocked; the worst case is one extra logout call.
    }
};

/**
 * Call with the signed-in user's id (or null/undefined when signed out).
 * Identifies once per full page load - not on every Inertia navigation - and
 * logs the widget out when the session has ended, however that happened.
 */
export function syncQuackbackIdentity(userId: number | null | undefined) {
    const quackback = window.Quackback;

    // Not configured: the snippet was never rendered.
    if (typeof quackback !== 'function') return;

    if (userId == null) {
        identifiedThisLoad = null;

        // Only if this browser was identified before, so an anonymous
        // visitor's own session isn't reset on every page.
        if (read() !== null) {
            quackback('logout');
            write(null);
        }

        return;
    }

    if (identifiedThisLoad === userId || requesting) return;

    requesting = true;

    fetch(route('quackback.token'), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    })
        .then((response) => (response.ok ? response.json() : null))
        .then((body: { ssoToken?: string } | null) => {
            if (!body?.ssoToken) return;

            quackback('identify', { ssoToken: body.ssoToken });
            identifiedThisLoad = userId;
            write(userId);
        })
        .catch(() => {
            // Stay anonymous; the next full load tries again.
        })
        .finally(() => {
            requesting = false;
        });
}
