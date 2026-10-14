import { bucket, defineRailway, github, group, image, postgres, preserve, project, redis, service, volume } from 'railway/iac';

// Whole Railway project as code. `railway config plan` previews the diff
// against the live environment, `railway config apply` (or the GitHub Action
// in .github/workflows/railway.yml) applies it. Every service builds from the
// root Dockerfile; they differ only in start command - except `ssr`, a plain
// Node service built from Dockerfile.ssr. See docs/deploy-railway.md.
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

    // Server-side renderer for the public pages (marketing, legal), so search
    // engines get real HTML. Private only: no public domain, ever - its
    // /shutdown endpoint is unauthenticated. `web` reaches it over Railway's
    // private network (INERTIA_SSR_HOST below); if it is down, `web` just serves
    // those pages client-rendered, so a failed deploy here never takes the site down.
    const ssr = service('ssr', {
        source,
        build: { builder: 'DOCKERFILE', dockerfilePath: 'Dockerfile.ssr' },
        healthcheck: '/health',
        healthcheckTimeout: 60,
        env: {
            PORT: '13714',
            // Build-time: the <title> suffix, kept the same as `web`'s.
            VITE_APP_NAME: 'Fab Shop',
        },
    });

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
            // Host/port split like REVERB_INTERNAL_*: config/inertia.php builds the URL.
            INERTIA_SSR_HOST: ssr.env.RAILWAY_PRIVATE_DOMAIN,
            INERTIA_SSR_PORT: '13714',
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

    // Quackback (user feedback board, https://quackback-production-59a0.up.railway.app) shares this
    // project but not the app: its own Postgres (pgvector - not the app's `postgres` above), Dragonfly
    // (Redis-compatible) and bucket. Declared here only so `railway config apply` doesn't delete them as
    // undeclared; their secrets and wiring (DATABASE_URL, S3_*, SECRET_KEY...) are set in the dashboard
    // and kept with preserve(). Captured from the live project with `railway config pull`.
    const quackbackDb = service('Postgres', {
        source: image('pgvector/pgvector:pg18'),
        replicas: { sfo: 1 },
        networking: { privateNetworkEndpoint: 'postgres-14666529' },
        volumeMounts: {
            '/var/lib/postgresql/data': volume('postgres-volume-JIjG', {
                alerts: { usage: { '80': {}, '95': {}, '100': {} } },
                allowOnlineResize: true,
                region: 'sfo',
                sizeMB: 5000,
            }),
        },
        env: { PGDATA: preserve(), POSTGRES_DB: preserve(), POSTGRES_PASSWORD: preserve(), POSTGRES_USER: preserve() },
    });

    const quackbackCache = service('Dragonfly', {
        source: image('ghcr.io/dragonflydb/dragonfly:v1.37.0'),
        start: 'dragonfly --cluster_mode=emulated --lock_on_hashtags',
        replicas: { sfo: 1 },
        networking: { privateNetworkEndpoint: 'dragonfly' },
    });

    const quackbackStorage = bucket('Bucket', { region: 'sjc' });

    const quackbackApp = service('Quackback', {
        source: image('ghcr.io/quackbackio/quackback:latest', {
            // Patch releases only, weekends only.
            autoUpdates: {
                schedule: [
                    { day: 0, endHour: 24, startHour: 0 },
                    { day: 6, endHour: 24, startHour: 0 },
                ],
                type: 'patch',
            },
        }),
        replicas: { sfo: 1 },
        networking: { privateNetworkEndpoint: 'quackback' },
        env: {
            BASE_URL: preserve(),
            DATABASE_URL: preserve(),
            NODE_ENV: preserve(),
            PORT: preserve(),
            REDIS_URL: preserve(),
            S3_ACCESS_KEY_ID: preserve(),
            S3_BUCKET: preserve(),
            S3_ENDPOINT: preserve(),
            S3_FORCE_PATH_STYLE: preserve(),
            S3_REGION: preserve(),
            S3_SECRET_ACCESS_KEY: preserve(),
            SECRET_KEY: preserve(),
        },
    });

    const quackback = group('Quackback', [quackbackApp, quackbackDb, quackbackCache, quackbackStorage]);

    return project('fab-shop', { resources: [db, cache, storage, web, horizon, scheduler, reverb, ssr, quackback] });
});
