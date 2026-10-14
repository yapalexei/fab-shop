import '../css/app.css';
import './bootstrap';

import { syncQuackbackIdentity } from '@/lib/quackback';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        // Feedback widget: identify the signed-in user once per load, and log
        // it out when the session ends (a no-op unless the widget is on).
        const userId = (page: typeof props.initialPage) =>
            (page.props as { auth?: { user?: { id: number } | null } }).auth
                ?.user?.id;
        syncQuackbackIdentity(userId(props.initialPage));
        router.on('navigate', (event) =>
            syncQuackbackIdentity(userId(event.detail.page)),
        );

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
