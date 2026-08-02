<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Browser API configuration
|--------------------------------------------------------------------------
|
| Tunable knobs for the desktop API surface. Product rules (quotas, grace
| periods) live here and in config/plans.php — never in the Electron client.
|
*/

return [

    /*
    | Access policy for payment states that are not immediately granted.
    | past_due_grace_days: how many days a past-due subscription keeps browser
    | access after the payment failure. 0 disables the grace period.
    */
    'access' => [
        'past_due_grace_days' => (int) env('BROWSER_PAST_DUE_GRACE_DAYS', 0),
    ],

    /*
    | Session lease semantics. A "session" is a started authorized operation;
    | the daily usage counter is never decremented when a session finishes.
    */
    'session' => [
        'lease_minutes' => (int) env('BROWSER_SESSION_LEASE_MINUTES', 30),
        'idempotency_retention_days' => (int) env('BROWSER_IDEMPOTENCY_RETENTION_DAYS', 7),
    ],

    /*
    | Minimum accepted desktop client version for paid operations. Empty means
    | every version is accepted for now; set it once releases are published.
    */
    'minimum_client_version' => env('BROWSER_MINIMUM_CLIENT_VERSION'),

    /*
    | Release metadata and artifact serving. Artifacts live in object storage;
    | the API only returns short-lived signed URLs.
    */
    'releases' => [
        'disk' => env('RELEASES_DISK', 'releases'),
        'signed_url_ttl_minutes' => (int) env('BROWSER_RELEASE_URL_TTL_MINUTES', 15),
        'notes_url' => env('RELEASES_NOTES_URL', 'https://app.example.com/releases'),
    ],

    /*
    | The OAuth client registered for the desktop application. The redirect URI
    | is the local loopback endpoint the Electron app listens on.
    */
    'oauth' => [
        'client_id' => env('BROWSER_OAUTH_CLIENT_ID'),
        'redirect_uri' => env('BROWSER_OAUTH_REDIRECT_URI', 'http://127.0.0.1:49152/oauth/callback'),
        'scopes' => ['browser:read', 'browser:run', 'device:manage'],
    ],

];
