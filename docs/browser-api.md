# Invisiboll Browser Control API

**Status:** Proposed contract for v1
**Audience:** Web backend and Electron browser teams
**Owner:** `web` repository
**Last reviewed:** 2026-08-01

This document defines the API that the Laravel application must expose to the
Invisiboll Browser desktop application. It is the implementation plan and the
contract boundary between the web and Electron. It is not a description of
the marketing website and it does not move browser automation or anti-detect
logic into Laravel.

The short version is:

- Laravel is the source of truth for identity, billing, entitlements, quotas,
  devices, and release access.
- Electron is the client that executes browser operations on the user's
  machine.
- The browser must not calculate plans, decide subscription access, or trust
  a locally modified quota counter.
- The API contract is versioned and published as OpenAPI. Electron types are
  generated from that contract; they are never hand-written in the desktop
  repository.

## 1. Context and current repository state

The repository already contains the web/session side of the product:

- Fortify registration, login, email verification, password reset, 2FA, and
  logout.
- Cashier Stripe integration with plan selection, Checkout, the billing
  portal, and subscription webhooks.
- `Plan` enum and `config/plans.php` as the initial plan and quota catalog.
- A web `EnsureSubscribed` middleware that protects the current dashboard.

The desktop API is not implemented yet:

- There is currently no `routes/api.php` contract in this repository.
- `composer.json` contains Fortify and Cashier, but no Sanctum or Passport
  dependency.
- There is no device/installation model.
- There is no release metadata or download API.
- Plan quotas are configured, but are not yet exposed through a desktop API or
  enforced at a server-side browser-operation boundary.

Relevant existing files:

- [`routes/web.php`](../routes/web.php)
- [`BillingController.php`](../app/Http/Controllers/Billing/BillingController.php)
- [`EnsureSubscribed.php`](../app/Http/Middleware/EnsureSubscribed.php)
- [`Plan.php`](../app/Enums/Billing/Plan.php)
- [`config/plans.php`](../config/plans.php)
- [`ARCHITECTURE.md`](../../ARCHITECTURE.md)

The API work must extend these business rules rather than creating a second
plan or billing system.

## 2. Product boundary

```text
Marketing site
    └── sends users to the web application

Laravel web application
    ├── web sessions and account pages
    ├── OAuth/API authentication
    ├── Stripe/Cashier billing
    ├── plans, entitlements, quotas, devices, releases
    └── versioned JSON API

Electron browser
    ├── authenticates through the API
    ├── stores tokens in OS-protected storage
    ├── asks the API whether operations are allowed
    └── executes browser operations locally
```

### Laravel owns

- User identity and email verification state.
- API sessions, tokens, and revocation.
- Subscription state received from Stripe.
- Plan catalog and entitlement calculation.
- Profile and session quotas.
- Device registration and revocation.
- Browser release metadata and supported versions.
- Authorization at the start of quota-controlled operations.
- The OpenAPI contract.

### Electron owns

- Window and tab management.
- Local browser profiles and local encrypted data.
- Navigation, extensions, cookies, and browser automation.
- Installer and updater UX.
- Local caching of the last known entitlement for display only.
- Calling the API before operations that consume paid access.

### Laravel must not own

- Fingerprint generation.
- Anti-detect navigation logic.
- Browser rendering or automation.
- Local profile files or user cookies.
- A second API/backend between Electron and Laravel.

## 3. API goals and non-goals

### Goals for v1

1. Let a verified user authenticate the desktop application securely.
2. Tell Electron exactly who the user is and what the account can do.
3. Make billing status changes reach Electron without shipping a new desktop
   build.
4. Prevent a revoked subscription or device from starting new operations.
5. Enforce quotas on the server at the operation boundary.
6. Allow the user to download an official browser build.
7. Allow the user to revoke a lost or old installation.
8. Generate stable TypeScript contracts for Electron.

### Explicitly deferred

These are not required to launch the first usable browser:

- Cloud-synchronized browser profiles.
- Campaign management.
- Domains and traffic analytics.
- Teams, seats, roles, and invitations.
- Public customer API keys.
- Referral and affiliate systems.
- A complete support-ticket system.
- A competitor-style operations dashboard.

If profiles remain local to Electron, `/profiles` should not be implemented
until cross-machine synchronization becomes a product requirement.

## 4. Authentication decision

### Recommendation: Laravel Passport with Authorization Code + PKCE

The desktop is a public client: a client secret cannot be kept confidential in
an installed Electron application. The recommended flow is therefore OAuth2
Authorization Code with PKCE:

1. Electron creates a random `state`, `code_verifier`, and S256
   `code_challenge`.
2. Electron opens the system browser at Laravel's authorization endpoint.
3. The user completes the existing web login, email verification, and 2FA
   flow.
4. Laravel redirects to a registered Electron callback with a short-lived
   authorization code.
5. Electron verifies `state` and exchanges the code plus `code_verifier` for
   an access token and refresh token.
6. Electron stores the refresh token using OS-protected storage and keeps the
   access token in memory when possible.
7. Electron refreshes the access token before expiry and never asks the user
   for the password inside the desktop UI.

This matches the existing architecture requirement of a short-lived access
token plus refresh token. Passport provides the OAuth2 token endpoint,
refresh-token response, token lifetimes, scopes, and revocation model. The
API contract must not depend on whether the access token is internally opaque
or JWT-shaped; Electron only sends it as a Bearer token.

Suggested initial lifetimes:

| Credential          |           Suggested lifetime | Storage                                         |
| ------------------- | ---------------------------: | ----------------------------------------------- |
| Access token        |                   15 minutes | Memory; encrypted storage only if necessary     |
| Refresh token       |                      30 days | OS-protected storage via Electron `safeStorage` |
| Authorization code  | Provider default; keep short | Server only                                     |
| Device registration |                Until revoked | Server record plus local installation ID        |

Refresh behavior must be explicitly tested. On refresh, the server should
return a new access token and apply a refresh-token replay policy. If rotation
is enabled, the old refresh token must become invalid after successful use.
Logout and device revocation must revoke the associated access/refresh token
chain.

Passport is not currently installed in this repository. Before implementing
the API, choose and approve the dependency, then run the Laravel 13 API
installation flow for Passport and commit the generated configuration and
migrations in the normal project workflow.

### Why not use the web session for Electron?

The web application uses cookie sessions for Inertia pages. Electron is a
separate native client and must not share the browser's session cookie as its
long-term credential. A Bearer-token API makes revocation, device tracking,
refresh, and API contract testing explicit.

### Sanctum alternative

Sanctum is a valid simpler choice if the product accepts a single opaque API
token with an explicit expiry and revocation instead of a full OAuth2
access/refresh flow. Sanctum supports Bearer tokens, abilities, revocation,
and token expiration, but it is not an OAuth2 server and does not provide the
Passport-style refresh-token protocol.

Do not implement a custom JWT-plus-refresh protocol on top of Sanctum without
a separate security review. If short access tokens and refresh tokens remain
mandatory, Passport + PKCE is the preferred implementation.

### OAuth endpoints

When Passport is selected, these endpoints are provider-managed and must not
be duplicated as unrelated custom login endpoints:

| Method | Endpoint                                       | Purpose                                      | Client                 |
| ------ | ---------------------------------------------- | -------------------------------------------- | ---------------------- |
| `GET`  | `/oauth/authorize`                             | Start web authorization                      | Electron + web session |
| `POST` | `/oauth/token`                                 | Exchange authorization code or refresh token | Electron               |
| `POST` | `/oauth/revoke` or project revocation endpoint | Revoke a token when required                 | Web/API                |

The exact generated Passport routes must be inspected after installation and
included in the OpenAPI description or linked as an OAuth security section.

### Authorization Code + PKCE example

Electron opens a URL equivalent to:

```text
GET https://app.example.com/oauth/authorize
    ?client_id=browser-desktop
    &redirect_uri=http%3A%2F%2F127.0.0.1%3A49152%2Foauth%2Fcallback
    &response_type=code
    &scope=browser%3Aread%20browser%3Arun%20device%3Amanage
    &state=<random-state>
    &code_challenge=<base64url-sha256-verifier>
    &code_challenge_method=S256
```

Electron then exchanges the callback code:

```http
POST /oauth/token HTTP/1.1
Host: app.example.com
Content-Type: application/x-www-form-urlencoded
Accept: application/json

grant_type=authorization_code&
client_id=browser-desktop&
redirect_uri=http%3A%2F%2F127.0.0.1%3A49152%2Foauth%2Fcallback&
code=<authorization-code>&
code_verifier=<original-verifier>
```

The token response must contain at least:

```json
{
  "token_type": "Bearer",
  "access_token": "<access-token>",
  "refresh_token": "<refresh-token>",
  "expires_in": 900
}
```

Electron refreshes with the standard token endpoint:

```http
POST /oauth/token HTTP/1.1
Content-Type: application/x-www-form-urlencoded

grant_type=refresh_token&
refresh_token=<refresh-token>&
client_id=browser-desktop
```

The desktop must verify the callback `state`, use exact registered redirect
URIs, and reject callbacks with an unexpected origin or missing code.

## 5. Base API contract

### Base URL and versioning

Production base URL:

```text
https://app.example.com/api/v1
```

Local development base URL:

```text
http://127.0.0.1:8000/api/v1
```

Rules:

- Put breaking changes behind a new major path, such as `/api/v2`.
- Additive response fields are allowed in v1.
- Do not rename or change the meaning of an existing field in v1.
- Do not expose Stripe price IDs, customer IDs, tokens, or internal database
  implementation details.
- Publish the contract at `GET /api/v1/openapi.json`.

### Headers

Every Electron request should send:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <access-token>
X-Client-Name: invisiboll-browser
X-Client-Version: 1.0.0
X-Client-Platform: windows|macos|linux
X-Client-Architecture: x64|arm64
X-Request-Id: <client-generated-uuid>
```

For requests that create or reserve a business operation, also require:

```http
Idempotency-Key: <unique-operation-key>
```

Laravel should accept or generate a request ID, include it in the response as
`X-Request-Id`, and include it in structured logs. Tokens and passwords must
never be logged.

### Successful response envelope

Application endpoints should use one stable envelope so Electron can parse
responses consistently:

```json
{
  "data": {},
  "meta": {
    "request_id": "req_01J..."
  }
}
```

Collections use:

```json
{
  "data": [],
  "meta": {
    "request_id": "req_01J...",
    "next_cursor": null
  }
}
```

Passport's standard OAuth token response remains unchanged and is not wrapped
in this application envelope.

### Error response envelope

Every application error should use:

```json
{
  "error": {
    "code": "subscription_required",
    "message": "An active subscription is required to run the browser.",
    "details": {}
  },
  "meta": {
    "request_id": "req_01J..."
  }
}
```

`code` is stable and machine-readable. `message` is displayable but must not
be used for branching. `details` is optional and must not contain secrets.

Validation errors use the same envelope and should include field errors in
`details.errors`:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The request contains invalid fields.",
    "details": {
      "errors": {
        "device_name": ["The device name field is required."]
      }
    }
  },
  "meta": {
    "request_id": "req_01J..."
  }
}
```

Laravel returns HTTP `422` for JSON validation failures. Keep that HTTP
behavior, but normalize the application-level error code in the API exception
handler.

### HTTP status policy

|        Status | Meaning                                   | Electron behavior                        |
| ------------: | ----------------------------------------- | ---------------------------------------- |
|         `200` | Successful read/update/action             | Process response                         |
|         `201` | Resource created                          | Store returned resource                  |
|         `204` | Successful deletion with no body          | Clear local reference                    |
|         `400` | Malformed request                         | Fix client/request; do not retry blindly |
|         `401` | Missing, expired, or invalid access token | Refresh once, then require login         |
|         `403` | Authenticated but not allowed             | Show account/device/subscription state   |
|         `404` | Resource or release does not exist        | Stop operation or refresh catalog        |
|         `409` | State or idempotency conflict             | Reconcile using returned details         |
|         `422` | Validation failed                         | Show field/domain error                  |
|         `429` | Rate limited                              | Honor `Retry-After` and back off         |
| `500/502/503` | Temporary server/upstream failure         | Retry safe requests with backoff         |

Do not use `402 Payment Required` as the only subscription signal. Use a
stable `403` error code such as `subscription_required` or
`subscription_past_due`; this keeps client behavior explicit and testable.

## 6. Endpoint inventory

### Public endpoints

| Method | Path                      | Purpose                                                                   |
| ------ | ------------------------- | ------------------------------------------------------------------------- |
| `GET`  | `/api/v1/health`          | Operational health check; do not expose sensitive diagnostics             |
| `GET`  | `/api/v1/openapi.json`    | Versioned API contract                                                    |
| `GET`  | `/api/v1/releases/latest` | Latest compatible browser release metadata and short-lived download links |

The release endpoint may be public because the installer is not the license
boundary. The browser itself must authenticate and obtain an entitlement before
it runs paid operations. Rate-limit release metadata and make artifact URLs
short-lived.

### Authenticated account endpoints

| Method | Path                   | Scope          | Purpose                                                       |
| ------ | ---------------------- | -------------- | ------------------------------------------------------------- |
| `GET`  | `/api/v1/me`           | `browser:read` | Identity, subscription, entitlements, usage, and server clock |
| `GET`  | `/api/v1/subscription` | `browser:read` | Detailed subscription state and limits                        |
| `GET`  | `/api/v1/usage`        | `browser:read` | Current usage counters and reset times                        |
| `POST` | `/api/v1/auth/logout`  | authenticated  | Revoke the current browser session/token chain                |

`GET /me` is intentionally available to a verified user without an active
subscription. This allows Electron to show an upgrade screen instead of
failing during login. Operation endpoints remain subscription-protected.

### Device endpoints

| Method   | Path                                 | Scope           | Purpose                            |
| -------- | ------------------------------------ | --------------- | ---------------------------------- |
| `POST`   | `/api/v1/devices`                    | `device:manage` | Register or resume an installation |
| `GET`    | `/api/v1/devices`                    | `device:manage` | List the user's installations      |
| `GET`    | `/api/v1/devices/{device}`           | `device:manage` | Show one installation              |
| `POST`   | `/api/v1/devices/{device}/heartbeat` | `device:manage` | Update liveness and client version |
| `DELETE` | `/api/v1/devices/{device}`           | `device:manage` | Revoke an installation             |

### Operation and quota endpoints

| Method | Path                                | Scope          | Purpose                                                    |
| ------ | ----------------------------------- | -------------- | ---------------------------------------------------------- |
| `POST` | `/api/v1/sessions/authorize`        | `browser:run`  | Atomically check entitlement and reserve a browser session |
| `POST` | `/api/v1/sessions/{session}/finish` | `browser:run`  | Mark a session as finished and release its active lease    |
| `GET`  | `/api/v1/sessions/{session}`        | `browser:read` | Recover the state of a previously authorized session       |

### Deferred profile endpoints

Only implement these if profiles must be synchronized in the cloud:

| Method   | Path                         | Purpose                |
| -------- | ---------------------------- | ---------------------- |
| `GET`    | `/api/v1/profiles`           | List cloud profiles    |
| `POST`   | `/api/v1/profiles`           | Create a cloud profile |
| `GET`    | `/api/v1/profiles/{profile}` | Read a cloud profile   |
| `PATCH`  | `/api/v1/profiles/{profile}` | Update a cloud profile |
| `DELETE` | `/api/v1/profiles/{profile}` | Delete a cloud profile |

Do not put cookies, passwords, fingerprints, or raw browser profile data in
these resources. Those belong in the local encrypted Electron profile store.

## 7. Core resource contracts

### `GET /api/v1/me`

Example:

```json
{
  "data": {
    "user": {
      "id": 42,
      "name": "Paulo Gabriel",
      "email": "paulo@example.com",
      "email_verified": true
    },
    "subscription": {
      "status": "active",
      "plan": {
        "id": "pro",
        "name": "Pro",
        "features": ["browser_profiles", "unlimited_sessions", "priority_support"]
      },
      "trial_ends_at": null,
      "current_period_ends_at": "2026-09-01T00:00:00Z",
      "cancel_at_period_end": false,
      "access": {
        "can_run_browser": true,
        "reason": null
      }
    },
    "entitlements": {
      "limits": {
        "profiles": 10,
        "sessions_per_day": null,
        "devices": null
      },
      "features": {
        "cloud_profiles": false,
        "team_sharing": true,
        "api_access": false
      }
    },
    "usage": {
      "profiles": {
        "used": 0,
        "limit": 10,
        "remaining": 10
      },
      "sessions_today": {
        "used": 12,
        "limit": null,
        "remaining": null,
        "reset_at": "2026-08-02T00:00:00Z"
      }
    },
    "server_time": "2026-08-01T15:30:00Z"
  },
  "meta": {
    "request_id": "req_01J..."
  }
}
```

Rules:

- Do not expose Stripe customer IDs, subscription IDs, price IDs, or payment
  method details here.
- `null` means unlimited, not zero.
- `remaining` is `null` when the limit is unlimited.
- `server_time` lets Electron correct local clock drift for expiry display.
- `access.reason` is a stable code when access is denied, for example
  `subscription_required`, `payment_past_due`, or `device_revoked`.

### Subscription status and access policy

The API should expose the provider state without making Electron understand
Stripe internals:

| API status           | Meaning                             | Initial browser access                       |
| -------------------- | ----------------------------------- | -------------------------------------------- |
| `none`               | No subscription                     | No                                           |
| `incomplete`         | Initial payment is incomplete       | No                                           |
| `incomplete_expired` | Initial payment expired             | No                                           |
| `trialing`           | Valid trial                         | Yes                                          |
| `active`             | Paid and active                     | Yes                                          |
| `past_due`           | Latest payment failed or is overdue | No, unless a configured grace period applies |
| `unpaid`             | Payment retries exhausted           | No                                           |
| `canceled`           | Subscription ended                  | No                                           |
| `paused`             | Subscription paused                 | No                                           |

The exact grace-period rule is a product decision and must be encoded in one
Laravel service, not in Electron. Every API response should use the same
entitlement service.

### `GET /api/v1/subscription`

```json
{
  "data": {
    "status": "active",
    "plan": {
      "id": "starter",
      "name": "Starter"
    },
    "limits": {
      "profiles": 1,
      "sessions_per_day": 50,
      "devices": null
    },
    "trial_ends_at": null,
    "current_period_ends_at": "2026-09-01T00:00:00Z",
    "cancel_at_period_end": false,
    "access": {
      "can_run_browser": true,
      "reason": null
    },
    "billing_portal_url": null
  },
  "meta": {
    "request_id": "req_01J..."
  }
}
```

The billing portal URL should generally be generated by an authenticated web
route when the user clicks “Manage billing”. It should not be permanently
cached or exposed to unauthenticated clients.

### `POST /api/v1/devices`

Request:

```json
{
  "installation_id": "3b3d2fbb-45bf-4d5a-bb76-6c4f0fbf8ec8",
  "name": "Paulo's MacBook Pro",
  "platform": "macos",
  "architecture": "arm64",
  "app_version": "1.0.0",
  "os_version": "15.5"
}
```

Response:

```json
{
  "data": {
    "id": "dev_01J...",
    "name": "Paulo's MacBook Pro",
    "platform": "macos",
    "architecture": "arm64",
    "app_version": "1.0.0",
    "last_seen_at": "2026-08-01T15:30:00Z",
    "revoked_at": null,
    "created_at": "2026-08-01T15:29:00Z"
  },
  "meta": {
    "request_id": "req_01J..."
  }
}
```

`installation_id` is generated once by Electron and stored in OS-protected
storage. It is an installation identifier, not a fingerprint. Registration
must be idempotent for the same user and installation ID. A revoked installation
must not be silently reactivated by a heartbeat; it requires an explicit
re-authentication or a new registration policy.

### `POST /api/v1/sessions/authorize`

This endpoint is the quota control point for starting a paid browser
operation.

Request:

```json
{
  "device_id": "dev_01J...",
  "operation": "browser_run",
  "client_version": "1.0.0",
  "profile_id": null
}
```

Response:

```json
{
  "data": {
    "session_id": "ses_01J...",
    "operation": "browser_run",
    "authorized": true,
    "expires_at": "2026-08-01T16:00:00Z",
    "plan": "starter",
    "usage": {
      "used": 50,
      "limit": 50,
      "remaining": 0,
      "reset_at": "2026-08-02T00:00:00Z"
    }
  },
  "meta": {
    "request_id": "req_01J..."
  }
}
```

The server must perform these checks in one business operation:

1. Access token is valid.
2. User email is verified.
3. Device belongs to the user and is not revoked.
4. Subscription entitlement allows `browser_run`.
5. Client version is supported or inside the allowed grace window.
6. The requested profile is allowed by the plan, if cloud profiles are used.
7. The daily/session quota can be reserved atomically.

If the check fails, return a stable error code instead of returning
`authorized: false` with HTTP 200:

```json
{
  "error": {
    "code": "quota_exceeded",
    "message": "The daily session limit has been reached.",
    "details": {
      "limit": 50,
      "used": 50,
      "reset_at": "2026-08-02T00:00:00Z"
    }
  },
  "meta": {
    "request_id": "req_01J..."
  }
}
```

Use the `Idempotency-Key` header so a retry after a network timeout does not
consume the same quota twice. Store the request key and result for a bounded
retention period.

### `POST /api/v1/sessions/{session}/finish`

Request:

```json
{
  "result": "completed",
  "ended_at": "2026-08-01T15:48:00Z"
}
```

Allowed results:

```text
completed | stopped | crashed | expired
```

Finishing a session must be idempotent. The daily usage count is not decremented
when a session finishes; the product definition of “session” is a started
authorized operation. If a future plan limits concurrent sessions instead,
that must be represented by a separate lease counter rather than changing the
meaning of daily usage.

### `GET /api/v1/usage`

```json
{
  "data": {
    "period": {
      "type": "utc_day",
      "starts_at": "2026-08-01T00:00:00Z",
      "ends_at": "2026-08-02T00:00:00Z"
    },
    "counters": {
      "sessions": {
        "used": 12,
        "limit": 50,
        "remaining": 38
      },
      "profiles": {
        "used": 1,
        "limit": 1,
        "remaining": 0
      }
    }
  },
  "meta": {
    "request_id": "req_01J..."
  }
}
```

Use UTC for the initial daily reset so all clients receive the same result.
The reset boundary must be explicit in the response. Do not calculate the
server's quota day from the user's local machine clock.

### `GET /api/v1/releases/latest`

Request query:

```text
?platform=macos&architecture=arm64&current_version=1.0.0
```

Response:

```json
{
  "data": {
    "version": "1.1.0",
    "platform": "macos",
    "architecture": "arm64",
    "release_notes_url": "https://app.example.com/releases/1.1.0",
    "mandatory": false,
    "minimum_supported_version": "1.0.0",
    "artifact": {
      "size_bytes": 182736451,
      "sha256": "<sha256>",
      "download_url": "https://storage.example.com/signed-url",
      "download_url_expires_at": "2026-08-01T15:45:00Z"
    }
  },
  "meta": {
    "request_id": "req_01J..."
  }
}
```

Release rules:

- Store installers in object storage/CDN, not in the Laravel application
  process or Git repository.
- Return a short-lived signed URL.
- Publish SHA-256 and, preferably, a platform-native artifact signature.
- Electron verifies the checksum/signature before installing an update.
- `mandatory: true` means the current version is blocked after the grace
  window; the update policy is separate from subscription access.
- The API should return `404` when no compatible artifact exists.

## 8. Billing and webhook boundary

Billing is a web concern. Electron should never collect card data, call Stripe
directly, or trust a plan value supplied by the desktop client.

### Existing web billing flow

The current web flow is the right direction:

```text
User selects plan on web
    ↓
Laravel validates Plan enum and server-side Stripe price ID
    ↓
Cashier creates hosted Stripe Checkout session
    ↓
Stripe processes payment
    ↓
Cashier webhook updates local subscription state
    ↓
GET /api/v1/me returns new entitlements
```

The selected plan must be validated against `Plan::catalogValues()`. Never
accept a Stripe price ID from the browser or Electron as an authority.

### Web routes, not desktop API routes

These remain authenticated web/Inertia or redirect routes:

```text
GET  /billing/plan
POST /billing/checkout
GET  /billing/success
GET  /billing/cancel
GET  /billing/status
GET  /billing/portal
```

The browser only needs the normalized result through `/api/v1/me` and
`/api/v1/subscription`.

### Stripe webhook

The existing Cashier webhook path is `/stripe/webhook`. It must:

- Remain outside the user Bearer-token API group.
- Verify the `Stripe-Signature` header using the configured webhook secret.
- Return a successful response only after the event is accepted safely.
- Be idempotent because Stripe retries deliveries.
- Log event ID and type, never card data or secrets.
- Update local subscription state before the next entitlement read.

At minimum, configure the Cashier-required subscription/customer/payment
events, including:

```text
customer.subscription.created
customer.subscription.updated
customer.subscription.deleted
customer.updated
customer.deleted
payment_method.automatically_updated
invoice.payment_action_required
invoice.payment_succeeded
```

Also decide how to handle payment failure and invoice states such as
`past_due`, `unpaid`, `incomplete`, and `paused`. Stripe subscription activity
is asynchronous; the API must not assume that a successful Checkout redirect
means the subscription is already active. The success page may poll the web
status endpoint, but the entitlement service should trust the persisted
webhook-synchronized subscription state.

### Entitlement service

Create one domain service, for example:

```text
App\Domain\Subscription\EntitlementService
```

It should expose operations equivalent to:

```text
resolve(user) -> Entitlements
canRun(user, operation, device) -> AccessDecision
reserveUsage(user, operation, idempotencyKey) -> SessionGrant
```

`EnsureSubscribed` may continue protecting web pages, but the API must not
duplicate its logic in controllers. The web middleware and API controllers
should call the same entitlement policy.

## 9. Data model proposal

### Existing tables to reuse

- `users`
- `subscriptions`
- `subscription_items`

The plan catalog may remain configuration-backed for the first release. Do not
create a second database plan table unless non-developer plan editing becomes
a requirement.

### Passport tables

Install and use the tables generated by Passport for OAuth clients, access
tokens, refresh tokens, authorization codes, and optional device codes. Do not
create a parallel custom token table unless the authentication decision changes.

### `devices`

Suggested fields:

```text
id                  UUID/ULID primary key
user_id             foreign key
installation_id     UUID, unique per user
name                varchar
platform            enum/string
architecture        enum/string
app_version         varchar
os_version          varchar nullable
last_seen_at        timestamp nullable
revoked_at          timestamp nullable
created_at          timestamp
updated_at          timestamp
```

Indexes:

```text
unique(user_id, installation_id)
index(user_id, revoked_at)
index(last_seen_at)
```

### `session_grants`

Suggested fields:

```text
id                  UUID/ULID primary key
user_id             foreign key
device_id           foreign key
operation           varchar
profile_id          nullable foreign key if cloud profiles exist
idempotency_key     varchar
status              reserved|started|completed|stopped|crashed|expired
authorized_at       timestamp
expires_at          timestamp
finished_at         timestamp nullable
metadata            jsonb nullable
created_at          timestamp
updated_at          timestamp
```

Indexes and constraints:

```text
unique(user_id, idempotency_key)
index(user_id, created_at)
index(device_id, status)
```

### `usage_daily`

Use an atomic daily aggregate for quotas that are read frequently:

```text
id                  UUID/ULID primary key
user_id             foreign key
period_start        timestamp UTC
sessions_started    bigint default 0
profiles_count      bigint default 0 or derived from source
created_at          timestamp
updated_at          timestamp
```

Constraint:

```text
unique(user_id, period_start)
```

If auditability becomes important, append immutable `usage_events` and update
the aggregate transactionally. The launch version should not calculate a
quota by scanning every historical request.

### `browser_releases`

Suggested fields:

```text
id                  UUID/ULID primary key
version             varchar
platform            enum/string
architecture        enum/string
artifact_key        varchar
artifact_size       bigint
sha256              varchar
signature           text nullable
minimum_version     varchar nullable
mandatory           boolean default false
published_at        timestamp
deprecated_at       timestamp nullable
```

Unique release identity:

```text
unique(version, platform, architecture)
```

## 10. Authorization and middleware

Recommended API route layers:

```text
api
└── throttle:api
    └── auth:api              # Passport; auth:sanctum if Sanctum is selected
        ├── verified          # for account/device reads and operations
        └── entitlement       # only for paid operations
```

Suggested middleware/policies:

- `auth:api`: validates the Bearer access token.
- `verified`: rejects unverified accounts with `email_not_verified`.
- `EnsureDeviceBelongsToUser`: prevents cross-account device access.
- `EnsureDeviceNotRevoked`: rejects revoked installations.
- `EnsureBrowserEntitled`: checks the normalized entitlement decision.
- `throttle:api`: general request protection.
- Per-operation rate limiter: stricter protection for authorization and
  heartbeat endpoints.

Token scopes/abilities are coarse-grained permissions, not billing rules:

```text
browser:read
browser:run
device:manage
profile:read
profile:write
```

The server must still check the user's ownership, subscription, plan limits,
and device state. A token with `browser:run` does not bypass a canceled
subscription.

## 11. Quota semantics

### Initial quota definition

The current plan configuration defines:

```text
starter: profiles=1,  sessions_per_day=50
pro:     profiles=10, sessions_per_day=unlimited
scale:   profiles=unlimited, sessions_per_day=unlimited
```

These values must be returned by the entitlement service and not duplicated in
Electron constants.

### Server enforcement

For a daily limit:

1. Compute the UTC period key.
2. Lock or atomically update the user's usage row.
3. Compare the current count with the entitlement limit.
4. If allowed, increment and create a session grant in the same transaction.
5. If denied, return `quota_exceeded` with the current count and reset time.

The operation must be safe under concurrent requests from multiple devices.
An application-level `if` followed by a separate increment is not sufficient.

### Local Electron behavior

Electron may cache the last `/me` response and show a local warning, but:

- It must call the server before starting a quota-controlled operation.
- It must stop when the access token expires and refresh fails.
- It must stop when `/sessions/authorize` returns an entitlement error.
- It must not continue indefinitely because the server is unavailable.

## 12. Download and release security

The download system is not the subscription enforcement boundary. It should
provide a reliable official artifact:

- Keep artifacts in private object storage where possible.
- Generate signed URLs with short expiry.
- Publish SHA-256 checksums.
- Sign installers using Windows, macOS, and Linux platform mechanisms.
- Verify checksums/signatures inside Electron before replacing the current
  installation.
- Record the minimum supported client version in the release metadata.
- Never expose storage credentials to Electron or the web frontend.

The updater should use a timeout, retry only safe metadata requests, and fail
closed when the downloaded artifact cannot be verified.

## 13. Reliability and client retry rules

### Electron retry policy

- `401`: refresh once, replay the original request once, then require login.
- `403`: do not retry; show the returned account/device/subscription state.
- `404`: do not retry unless refreshing a release catalog.
- `409`: reconcile using the server response and idempotency key.
- `422`: fix request data; do not retry unchanged.
- `429`: honor `Retry-After`; use exponential backoff with jitter.
- `5xx`: retry only idempotent reads and explicitly idempotent actions.
- Network timeout: retry with the same `Idempotency-Key` for an operation.

### Server behavior

- Enforce request timeouts on upstream Stripe and storage calls.
- Return `Retry-After` for rate limits and planned temporary unavailability.
- Generate a request ID for every request.
- Log latency, status, endpoint, user ID, device ID, and request ID; never log
  access tokens, refresh tokens, passwords, or full authorization headers.
- Emit metrics for authentication failures, entitlement denials, quota
  denials, webhook lag, and release download failures.

## 14. Rate limits

Use named Laravel rate limiters rather than one hard-coded controller counter.
Initial suggested policies:

| Limiter             | Suggested policy         | Key                        |
| ------------------- | ------------------------ | -------------------------- |
| `api`               | 120 requests/minute      | user ID, then device ID/IP |
| `auth`              | 10 attempts/minute       | email + IP                 |
| `device-register`   | 10/hour                  | user ID + installation ID  |
| `heartbeat`         | 60/minute                | device ID                  |
| `session-authorize` | 60/minute plus plan rule | user ID + device ID        |
| `release`           | 60/minute                | IP, then user ID           |

These are starting values, not product quotas. Tune them using production
metrics. A `429` response must include `Retry-After`.

## 15. CORS, CSRF, and transport

- All production API and OAuth traffic must use HTTPS.
- API Bearer routes should be stateless and should not rely on the Inertia web
  session cookie.
- Web form/session routes keep Laravel's normal CSRF protection.
- Do not enable wildcard credentialed CORS.
- Native Electron requests do not need broad browser CORS allowances; allow
  only the actual web origins needed for the Inertia application.
- If the web SPA ever calls a stateful Sanctum API, configure Sanctum's
  stateful domains and CSRF flow separately from the native Electron token
  flow.

## 16. OpenAPI and Electron contract workflow

The API contract is generated from Laravel-side route and schema definitions.
Do not make Electron the place where endpoint types are designed.

### Required artifacts

```text
web
├── routes/api.php
├── app/Http/Controllers/Api/
├── app/Http/Requests/Api/
├── app/Http/Resources/Api/
├── app/Domain/Subscription/
└── public/openapi.json or a stable /api/v1/openapi.json endpoint
```

The project should choose one Laravel-compatible OpenAPI generator, such as
Scramble, and configure it as part of the API implementation. The generated
spec should describe:

- Every method/path/parameter.
- Request bodies and validation constraints.
- Success and error responses.
- Bearer security scheme.
- OAuth2 authorization-code/PKCE metadata or a linked Passport section.
- Enum values for platform, architecture, subscription status, and error
  codes.
- `Idempotency-Key` and `X-Request-Id` headers.

OpenAPI security scheme baseline:

```yaml
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: OAuth2 access token
```

Public operations explicitly set `security: []`; authenticated operations use
the bearer scheme. The contract should follow OpenAPI 3.1 and be validated in
CI.

### Electron sync

The Electron repository should have a command equivalent to:

```text
pnpm contracts:pull
```

That command should:

1. Download the versioned `openapi.json` over HTTPS.
2. Verify the expected host and, in CI, an optional checksum.
3. Generate TypeScript types.
4. Fail on a breaking schema change.
5. Run Electron typecheck and API contract tests.

The web repository must not manually edit Electron types, and Electron must
not create a second handwritten API model.

## 17. Testing plan

Every endpoint must have Laravel feature coverage against PostgreSQL.

### Authentication

- Unauthenticated API request returns `401` and `WWW-Authenticate`.
- Expired access token is rejected.
- Refresh returns a usable access token.
- Replayed/revoked refresh token is rejected according to the chosen policy.
- PKCE callback rejects invalid `state` and invalid verifier.
- Logout/revocation blocks the previous token chain.
- Unverified user cannot authorize browser operations.

### Account and entitlements

- `/me` returns the expected plan limits for every plan.
- No subscription returns `can_run_browser=false` but still allows account read.
- Active and trialing subscriptions allow operation authorization.
- Past-due/unpaid/canceled behavior follows the selected grace policy.
- Stripe webhook state changes are visible through `/me` without a desktop
  update.

### Devices

- A device can only be read, heartbeated, or revoked by its owner.
- Registration is idempotent for the same installation ID.
- A revoked device cannot authorize a session.
- Heartbeats cannot silently reactivate a revoked device.

### Quotas

- Starter denies the 51st daily session.
- Unlimited quotas return `null` for limit and remaining.
- Concurrent authorization requests cannot exceed a finite quota.
- Repeating an operation with the same idempotency key does not double-count.
- The UTC reset boundary is correct.
- Finishing a session is idempotent.

### Releases

- Unsupported platform/architecture returns `404`.
- Signed URLs expire.
- Artifact checksum and minimum version are present.
- Mandatory update metadata is correctly returned.

### API contract

- OpenAPI is generated and valid.
- Every documented response can be produced by a test.
- CI detects breaking changes before Electron contracts are regenerated.
- Electron `contracts:pull` and typecheck pass against the deployed or test
  contract.

Laravel's JSON HTTP testing helpers should be used for these tests, including
`postJson`, status assertions, and JSON path assertions. The tests should
focus on this application's policies and contract rather than re-testing
Cashier or Passport internals.

## 18. Implementation roadmap

### Phase 0 — decisions before code

- Approve Passport + PKCE or explicitly choose the simpler Sanctum model.
- Decide the Electron callback strategy: loopback redirect or custom URI.
- Decide the payment grace policy for `past_due`.
- Define exactly what counts as a session.
- Decide whether the first release allows download before subscription.
- Choose artifact storage, signing, and update hosting.

### Phase 1 — API foundation

- Install/configure the chosen API authentication package.
- Add `routes/api.php` and `/api/v1` route organization.
- Add API exception/error envelope and request IDs.
- Add rate limiters.
- Add API Resources and Form Requests.
- Add the first OpenAPI generator and CI validation.
- Implement `/me` with a temporary entitlement service.

### Phase 2 — billing-backed entitlements

- Normalize Cashier subscription statuses.
- Map `Plan` and `config/plans.php` quotas into API resources.
- Implement `/subscription` and `/usage`.
- Expand webhook event tests and payment-failure behavior.
- Ensure web middleware and API policy use the same entitlement service.

### Phase 3 — desktop identity and devices

- Implement Passport client and PKCE callback flow.
- Implement device registration/list/revoke/heartbeat.
- Add token/device revocation behavior.
- Integrate Electron's safeStorage and refresh loop.

### Phase 4 — releases and operation authorization

- Add `browser_releases` and release publishing workflow.
- Add signed artifact URLs and checksum/signature verification.
- Implement `sessions/authorize`, `finish`, and idempotency.
- Add atomic usage counters and concurrency tests.
- Connect Electron GuardManager to API responses.

### Phase 5 — post-launch features

- Cloud profile synchronization, if required.
- Teams and roles.
- Public API access for eligible plans.
- More detailed dashboard analytics.
- Admin and support tooling.

## 19. Definition of done for the first API release

The API is ready for Electron integration when all of the following are true:

- A new user can register and verify an email in the web application.
- Electron can authenticate through the system browser using PKCE.
- Electron can refresh and revoke credentials without storing a password.
- `/api/v1/me` returns identity, subscription status, limits, features, usage,
  access decision, and server time.
- Stripe webhook changes are reflected in `/me` and `/subscription`.
- A verified user can discover and download a signed official browser release.
- A device can be registered, listed, heartbeated, and revoked.
- A revoked subscription or device cannot authorize a new browser session.
- Finite quotas are enforced atomically on the server.
- Network retries with an idempotency key cannot double-consume quota.
- Every API response follows the documented success/error contract.
- `openapi.json` is generated, validated, and consumed by Electron.
- Focused Pest feature tests, PHPStan, Pint, and Electron contract checks pass.

## 20. Official references

The design above follows the current official documentation for the versions in
this repository:

- [Laravel Sanctum 13.x](https://laravel.com/docs/13.x/sanctum) — API tokens,
  abilities, revocation, expiration, and `auth:sanctum`.
- [Laravel Passport 13.x](https://laravel.com/docs/13.x/passport) — OAuth2,
  token lifetimes, refresh tokens, PKCE, device flow, and revocation.
- [Laravel Fortify 13.x](https://laravel.com/docs/13.x/fortify) — web
  authentication, email verification, 2FA, and XHR authentication behavior.
- [Laravel Cashier Stripe 13.x](https://laravel.com/docs/13.x/billing) —
  Checkout, subscriptions, billing portal, webhook handling, and testing.
- [Laravel routing and rate limiting](https://laravel.com/docs/13.x/routing) —
  named limiters and throttle middleware.
- [Laravel validation](https://laravel.com/docs/13.x/validation) — JSON
  validation failures and HTTP 422 behavior.
- [Laravel API Resources](https://laravel.com/docs/13.x/eloquent-resources) —
  server-side JSON serialization.
- [Laravel HTTP tests](https://laravel.com/docs/13.x/http-tests) — JSON API
  feature-test helpers and assertions.
- [Stripe webhooks](https://docs.stripe.com/webhooks) — signature
  verification and webhook delivery handling.
- [Stripe subscription webhooks](https://docs.stripe.com/billing/subscriptions/webhooks)
  — asynchronous subscription states and payment lifecycle.
- [OpenAPI Specification 3.1.1](https://spec.openapis.org/oas/v3.1.1.html) —
  security schemes, operations, and responses.
