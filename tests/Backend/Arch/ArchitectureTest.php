<?php

declare(strict_types=1);

use App\Http\Controllers\Controller;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/*
 * =============================================================================
 * Browser web — Architecture tests
 * =============================================================================
 * These tests encode the rules described in AGENTS.md as executable assertions.
 * If you can't describe a rule as a Pest arch expectation, document it in
 * AGENTS.md instead — but every rule that CAN be expressed here SHOULD be,
 * because CI enforces it on every push.
 *
 * How to add a new rule:
 *   1. Describe the architectural constraint in a short sentence.
 *   2. Write the arch() expectation expressing it.
 *   3. If a legitimate violation exists, refactor — DO NOT add exceptions.
 * =============================================================================
 */

// -----------------------------------------------------------------------------
// Global hygiene — no debug / dump / die anywhere in app/
// -----------------------------------------------------------------------------
arch('no debug helpers in app code')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit', 'var_export'])
    ->not->toBeUsed();

arch('no env() calls outside config')
    ->expect('env')
    ->not->toBeUsed()
    ->ignoring('config');

arch('strict types on every file')
    ->expect('App')
    ->toUseStrictTypes();

// -----------------------------------------------------------------------------
// Controllers
// -----------------------------------------------------------------------------
arch('controllers are final')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toBeFinal()
    ->ignoring(Controller::class);

arch('controllers end with Controller')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('controllers never touch DB facade directly')
    ->expect('App\Http\Controllers')
    ->not->toUse(DB::class);

arch('controllers delegate domain work')
    ->expect('App\Http\Controllers')
    ->toOnlyBeUsedIn(['App\Http\Controllers', 'App\Providers', 'routes', 'Tests']);

// -----------------------------------------------------------------------------
// Models
// -----------------------------------------------------------------------------
arch('models extend Eloquent Model')
    ->expect('App\Models')
    ->classes()
    ->toExtend(Model::class);

arch('models never depend on HTTP layer')
    ->expect('App\Models')
    ->not->toUse([
        Request::class,
        'App\Http\Requests',
        'App\Http\Controllers',
    ]);

arch('models never use DB facade directly')
    ->expect('App\Models')
    ->not->toUse(DB::class);

// -----------------------------------------------------------------------------
// Form Requests
// -----------------------------------------------------------------------------
arch('form requests extend FormRequest')
    ->expect('App\Http\Requests')
    ->classes()
    ->toExtend(FormRequest::class);

arch('form requests end with Request')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request');

// -----------------------------------------------------------------------------
// Actions / Fortify
// -----------------------------------------------------------------------------
arch('actions are final')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal();

// -----------------------------------------------------------------------------
// Notifications
// -----------------------------------------------------------------------------
arch('notifications extend base Notification')
    ->expect('App\Notifications')
    ->classes()
    ->toExtend(Notification::class);

// All notifications must be queued except security-critical ones that need
// immediate delivery. Adding a class to the ignore list requires an inline
// comment explaining why synchronous dispatch is required.
arch('notifications are queued')
    ->expect('App\Notifications')
    ->classes()
    ->toImplement(ShouldQueue::class)
    ->ignoring([
        // Verify-email notifications follow Fortify's default sync dispatch
        // contract; do not queue auth-critical delivery.
        VerifyEmailNotification::class,
    ]);
