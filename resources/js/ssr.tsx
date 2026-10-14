import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';
import { route as ziggyRoute } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

type ZiggyConfig = Parameters<typeof ziggyRoute>[3];

// Only the public pages are server-rendered (App\Support\PublicSsr decides
// which requests get here), so only they are bundled - the authenticated app
// stays out of the SSR bundle.
const pages = import.meta.glob(['./Pages/Welcome.tsx', './Pages/Legal/*.tsx'], {
    eager: true,
});

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: ReactDOMServer.renderToString,
            title: (title) => `${title} - ${appName}`,
            resolve: (name) => {
                const resolved = pages[`./Pages/${name}.tsx`];

                if (!resolved) {
                    throw new Error(`Page "${name}" is not server-rendered.`);
                }

                return resolved;
            },
            setup: ({ App, props }) => {
                // The browser gets `route` from the @routes script in the
                // Blade layout; there is no window here, so build it from the
                // `ziggy` prop PublicSsr shares with server-rendered pages.
                const ziggy = page.props.ziggy as ZiggyConfig;

                globalThis.route = ((
                    name?: string,
                    params?: unknown,
                    absolute?: boolean,
                ) =>
                    ziggyRoute(
                        name as never,
                        params as never,
                        absolute,
                        ziggy,
                    )) as typeof ziggyRoute;

                return <App {...props} />;
            },
        }),
    { port: Number(process.env.PORT) || 13714 },
);
