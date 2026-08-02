<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

class OAuthClientSeeder extends Seeder
{
    /**
     * The desktop client name used by the Electron app.
     */
    public const CLIENT_NAME = 'Invisiboll Browser (desktop)';

    /**
     * Seed the desktop OAuth client (public, Authorization Code + PKCE).
     */
    public function run(): void
    {
        if (Client::query()->where('name', self::CLIENT_NAME)->exists()) {
            return;
        }

        $redirectUri = (string) config('browser.oauth.redirect_uri');

        app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            name: self::CLIENT_NAME,
            redirectUris: [$redirectUri],
            confidential: false,
        );
    }
}
