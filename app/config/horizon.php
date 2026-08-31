<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    |
    | This name appears in notifications and in the Horizon UI. Unique names
    | can be useful while running multiple instances of Horizon within an
    | application, allowing you to identify the Horizon you're viewing.
    |
    */

    'name' => env('HORIZON_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    /*
     * ⛔ **A REDIS CONNECTION, ON A PLATFORM THAT HAS NEVER HAD ONE (8816,
     * 8880-8899).** Everything Horizon knows lives here — supervisors, failed
     * jobs, metrics — so with no Redis every one of the twenty-one
     * `horizon/api/*` routes answers 500 while `GET /horizon` answers **200**,
     * because the dashboard route serves a static bundle and reads nothing.
     * **The door and the room are two different claims** and only the door has
     * ever been asserted; `tests/Feature/Architecture/ObservabilityTest.php`
     * §9 is where the second one is now driven. Read that rather than this
     * paragraph — a comment is the only artefact in this repository with no
     * failing state (8531).
     */
    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        // Cast as config/database.php already does: env() returns bool|string,
        // and Str::slug() takes a string.
        Str::slug((string) env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    /*
     * ⛔ **A THRESHOLD WITH NO RECIPIENT (8880-8899).** `LongWaitDetected` is
     * delivered by Horizon's own notification routing —
     * `Horizon::routeMailNotificationsTo()` and its two siblings — and **none
     * of the three is called anywhere in `app/`**, so this sixty seconds could
     * be tuned for ever with nothing on the other end of it. It is also scoped
     * to `redis:default`, the connection the supervisors use and the
     * application does not.
     *
     * ⛔ **WIRING IT IS NOT THE FIX AND IS REFUSED UNTIL SOMEBODY ARGUES IT.**
     * This platform has a pager — `OperatorAlerts` — with a floor, a ceiling, a
     * quiet window and a daily budget, every one of them argued (7595-7599,
     * 7760-7779, 8120-8139). A vendor notification path beside it inherits none
     * of them. `ObservabilityTest` §9 reddens on the day one is added.
     */
    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    /*
     * ⛔ **A TRIM POLICY OVER SNAPSHOTS NOTHING TAKES (8880-8899).** The graphs
     * these figures bound are built by `horizon:snapshot`, and **nothing in
     * `routes/console.php` schedules it** — so Waits and Throughput would be
     * blank even on the day a Horizon master ran. **Scheduling it today is
     * refused rather than forgotten**: with no Redis the command throws inside
     * `schedule:run` every five minutes, which is decision 415's *"a step that
     * always fails trains whoever runs it to stop reading the output"*. The
     * refusal has a failing state in `ObservabilityTest` §9, and that test's
     * message lists what has to be true on the box first.
     */
    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    /*
     * ⛔ **THE SIX QUEUE NAMES BELOW ARE A DESIGN FOR A HORIZON, AND THEY ARE
     * PLANNED WORK RATHER THAN DRIFT — ESTABLISHED 2026-08-23 (8750-8779).**
     * They were argued one supervisor at a time in the commit that introduced
     * them (`585ea427`, 2026-07-31): *"six queues across three supervisors
     * rather than one watching 'default'"*, with a reason per split that the
     * three comments below still carry. **So do not delete them to make a lint
     * green** — that would delete the design and leave the tree looking
     * finished.
     *
     * ⛔ **WHAT THEY ARE NOT IS REACHABLE, AND THE SECOND HALF IS THE ONE THAT
     * BITES.** Every block here is `'connection' => 'redis'` and this
     * application's queue connection is `database`; and separately, **a worker
     * pops the queue names it was started with and no others** —
     * `Illuminate\Queue\Console\WorkCommand::getQueue()` falls back to
     * `queue.connections.{connection}.queue`, which is `default`. So a job
     * dispatched to `outreach`, `sync`, `ai`, `seo` or `monitoring` is written
     * into `jobs` with that name and **left there** by a worker started
     * without a matching `--queue=`, holding its serialised payload — a
     * recipient's address, a caller's number, a sign-in URL — in a table with
     * no row-level security, no horizon and no erasure path (8615, 8620).
     * **`default` is the one name of the six that is not in that position**,
     * and only because it is the fallback rather than because anything here
     * arranged it.
     *
     * ⛔ **WHICH OF THE FIVE ARE CONSUMED ON ANY GIVEN BOX IS NOT KNOWABLE FROM
     * THIS REPOSITORY AND THIS COMMENT DOES NOT CLAIM TO KNOW IT.** What
     * consumes a queue is a worker's command line, and `CLAUDE.md` is explicit
     * that no document here may state what a running install has on. **What is
     * knowable is the relationship, and it has a failing state**:
     * `tests/Feature/Architecture/QueueRoutingTest.php` reddens the day
     * anything in `app/` or `routes/` routes a job to a named queue, and its
     * failure message says what has to be proven on the box before the routing
     * may land. Read that test rather than this paragraph — a comment is the
     * only artefact in this repository with no failing state (8531).
     *
     * ⚠️ **`tries => 1` BELOW IS A DEFAULT AND NOT A CEILING, AND 6055 READ IT
     * AS A CEILING** (6268). That decision pairs *"no actuation job declares
     * `$tries` or `$backoff`"* with *"Horizon runs `tries => 1`"* to conclude
     * that nothing retries. **The first half stopped being true before the row
     * was written** — all three of `app/Jobs/Actuation/*` declare `$tries` and a
     * `backoff()` — and **the second half never meant what it was read to
     * mean**. `Illuminate\Queue\Worker::markJobAsFailedIfAlreadyExceedsMaxAttempts()`,
     * verbatim from `vendor/` on 2026-08-21:
     *
     *     $maxTries = ! is_null($job->maxTries()) ? $job->maxTries() : $maxTries;
     *
     * — so a job's own `$tries`, serialised into the payload as `maxTries`,
     * **wins** over the worker option, and this figure applies only to jobs that
     * declare none. It is left at 1 deliberately: a job that has not thought
     * about idempotency should not be retried by a config file on its behalf.
     */
    'defaults' => [

        /*
         * Interactive work the owner is waiting on. Kept off the same
         * supervisor as everything else so a backlog of overnight SEO jobs
         * cannot delay a review reply or a missed-call text-back.
         */
        'supervisor-realtime' => [
            'connection' => 'redis',
            'queue' => ['default', 'outreach'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],

        /*
         * Provider syncs and AI calls. Longer timeout because an LLM round trip
         * or a paginated GBP sync legitimately outlasts 60 seconds — and a
         * timeout that fires mid-call produces a retry that does the work twice.
         * AutopilotJob's idempotency key is what makes that survivable, but the
         * cheaper fix is not to time out in the first place.
         */
        'supervisor-integrations' => [
            'connection' => 'redis',
            'queue' => ['sync', 'ai'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 300,
            'nice' => 0,
        ],

        /*
         * Background work nobody is watching. Lower priority via nice, so it
         * yields to the other two under load.
         */
        'supervisor-background' => [
            'connection' => 'redis',
            'queue' => ['seo', 'monitoring'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 600,
            'nice' => 10,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-realtime' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-integrations' => [
                'maxProcesses' => 6,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-background' => [
                'maxProcesses' => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-realtime' => ['maxProcesses' => 3],
            'supervisor-integrations' => ['maxProcesses' => 2],
            'supervisor-background' => ['maxProcesses' => 1],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watcher Configuration
    |--------------------------------------------------------------------------
    |
    | The following list of directories and files will be watched when using
    | the `horizon:listen` command. Whenever any directories or files are
    | changed, Horizon will automatically restart to apply all changes.
    |
    */

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
