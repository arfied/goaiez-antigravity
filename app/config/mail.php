<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        /*
        | ⛔ NOT THE SES PATH THIS APPLICATION USES, AND THE NAME INVITES THE
        | MISTAKE. This is Laravel's skeleton block for the AWS SDK transport.
        | SES is reached as plain SMTP through the `smtp` mailer above with SES
        | SMTP credentials (1191, R16), which is what makes the flip a
        | credential change.
        |
        | ⛔ **THIS BLOCK USED TO ARGUE THE TRAP WAS HARMLESS BECAUSE
        | `aws/aws-sdk-php` IS "not a dependency here", SO "selecting this one
        | would fail at resolution, not at send" — AND THAT WENT STALE ON
        | 2026-08-25 (9516).** The package entered `composer.lock` for the object
        | store, and `app('mail.manager')->mailer('ses')` now returns an
        | `Illuminate\Mail\Transport\SesTransport`. **Nothing in this repository
        | reddened**, because the lint over mailer resolution takes its subject
        | set from `MailDrivers::MAILERS` and this block is not in it — a subject
        | set derived from the thing being guarded.
        |
        | ✅ **THE CONTAINMENT IS ONE LAYER DEEPER AND ALWAYS WAS, AND IT IS
        | STATED HERE AS A PROPERTY RATHER THAN AN INVENTORY OF `composer.lock`**
        | (8861). `ses` is absent from `MailDrivers::MAILERS`, so
        | `MailDrivers::isKnown()` is false, `MailQuota::ceiling()` is null, and
        | `PlatformMailer::deliverNow()` asks for the ceiling **before** it
        | touches a transport — so an operator who types `MAIL_MAILER=ses` gets a
        | refusal naming `mail.daily_send_ceiling.ses`, whether the driver is
        | installed, absent, or installed next week.
        | `PlatformMailerTest`'s *"a framework mailer this application does not
        | declare is refused before its transport is built"* drives it over this
        | block and the three beside it.
        |
        | ⛔ **THE WARNING IS NOT DELETED, BECAUSE THE TRAP IS STILL REAL — IT IS
        | REAL FOR A WORSE REASON NOW.** It used to fail loudly at boot; it now
        | resolves and then refuses every message, which looks like a working
        | configuration until nothing arrives.
        */
        'ses' => [
            'transport' => 'ses',
        ],

        /*
        | The Gmail API transport, registered by name in `AppServiceProvider::
        | registerGmailTransport()`.
        |
        | ⚠️ THIS BLOCK WAS MISSING UNTIL R16 AND ITS ABSENCE MADE THE THEN-
        | PRIMARY TRANSPORT UNSELECTABLE. Laravel's `MailManager::resolve()`
        | reads `mail.mailers.<name>` before it ever consults an extended
        | driver, so `MAIL_MAILER=gmail` threw "Mailer [gmail] is not defined."
        | Nothing caught it because the only test that exercised the transport
        | set `mail.mailers.gmail` by hand — a fixture standing in for the
        | configuration it was meant to prove. R16 demotes this transport rather
        | than removing it (staff, support and inbound mail stay here), so the
        | block is added rather than the finding merely reported.
        */
        'gmail' => [
            'transport' => 'gmail',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

];
