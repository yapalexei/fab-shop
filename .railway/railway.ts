import { bucket, defineRailway, github, postgres, preserve, project, redis, service } from 'railway/iac';

// Whole Railway project as code. `railway config plan` previews the diff
// against the live environment, `railway config apply` (or the GitHub Action
// in .github/workflows/railway.yml) applies it. Every service builds from the
// root Dockerfile; they differ only in start command. See docs/deploy-railway.md.
export default defineRailway((ctx) => {
    const db = postgres('postgres');
    const cache = redis('redis');
    const storage = bucket('uploads', { region: 'iad' });

    const source = github('yapalexei/fab-shop', { branch: 'main' });
    const build = { builder: 'DOCKERFILE', dockerfilePath: 'Dockerfile' } as const;

    // Values every Laravel process needs. Secrets are set by hand (dashboard or
    // `railway variables`) and declared with preserve() so apply keeps them -
    // an undeclared variable would be deleted. See docs/deploy-railway.md.
    const shared = {
        APP_NAME: 'Fab Shop',
        APP_KEY: preserve(),
        APP_URL: preserve(),
        APP_ENV: 'production',
        APP_DEBUG: 'false',
        LOG_CHANNEL: 'stderr',
        LOG_LEVEL: 'info',

        DB_CONNECTION: 'pgsql',
        DB_URL: db.env.DATABASE_URL,
        REDIS_URL: cache.env.REDIS_URL,
        QUEUE_CONNECTION: 'redis',
        CACHE_STORE: 'database',
        SESSION_DRIVER: 'database',
        SESSION_SECURE_COOKIE: 'true', // https only; Railway terminates TLS at its edge

        // AWS_* below are Railway references to the bucket (${{uploads.AWS_...}}), set
        // by hand: IaC's own bucket refs applied as empty strings. See docs/deploy-railway.md.
        FILESYSTEM_DISK: 's3',
        MEDIA_DISK: 's3',
        AWS_ACCESS_KEY_ID: preserve(),
        AWS_SECRET_ACCESS_KEY: preserve(),
        AWS_DEFAULT_REGION: preserve(),
        AWS_BUCKET: preserve(),
        AWS_ENDPOINT: preserve(),
        AWS_USE_PATH_STYLE_ENDPOINT: 'false', // Railway buckets use virtual-host URLs,

        MAIL_MAILER: 'resend',
        MAIL_FROM_ADDRESS: preserve(),
        RESEND_API_KEY: preserve(),

        BROADCAST_CONNECTION: 'reverb',
        REVERB_APP_ID: preserve(),
        REVERB_APP_KEY: preserve(),
        REVERB_APP_SECRET: preserve(),
        REVERB_PORT: '443',
        REVERB_SCHEME: 'https',
        REVERB_INTERNAL_PORT: '8080',
        REVERB_INTERNAL_SCHEME: 'http',
    };

    const reverb = service('reverb', {
        source,
        build,
        start: 'php artisan reverb:start',
        env: {
            ...shared,
            PORT: '8080',
            REVERB_SERVER_PORT: '8080',
            REVERB_HOST: preserve(),
            VITE_REVERB_APP_KEY: preserve(),
        },
    });

    // Browser-facing Reverb host + build-time VITE_* args come from the reverb
    // service's public domain (generate it once: `railway domain --service reverb`).
    const reverbEnv = {
        REVERB_HOST: reverb.env.RAILWAY_PUBLIC_DOMAIN,
        REVERB_INTERNAL_HOST: reverb.env.RAILWAY_PRIVATE_DOMAIN,
        VITE_REVERB_APP_KEY: preserve(), // same value as REVERB_APP_KEY
        VITE_REVERB_HOST: reverb.env.RAILWAY_PUBLIC_DOMAIN,
        VITE_REVERB_PORT: '443',
        VITE_REVERB_SCHEME: 'https',
    };

    const web = service('web', {
        source,
        build,
        // Railway allows exactly one pre-deploy command, so chain the two.
        preDeploy: ["sh -c 'php artisan migrate --force && php artisan db:seed --class=PlanSeeder --force'"],
        healthcheck: '/up',
        healthcheckTimeout: 120,
        env: {
            ...shared,
            ...reverbEnv,
            PORT: '8080',
            PHP_OPCACHE_ENABLE: '1',
            VITE_APP_NAME: 'Fab Shop',
            // Feedback widget: web only (the launcher and the token endpoint
            // are web requests). Set by hand; the secret is server-only.
            QUACKBACK_URL: preserve(),
            QUACKBACK_SIGNING_SECRET: preserve(),
        },
    });

    const horizon = service('horizon', {
        source,
        build,
        start: 'php artisan horizon',
        env: { ...shared, ...reverbEnv },
    });

    const scheduler = service('scheduler', {
        source,
        build,
        start: 'php artisan schedule:work',
        env: { ...shared, ...reverbEnv },
    });

    return project('fab-shop', { resources: [db, cache, storage, web, horizon, scheduler, reverb] });
});
