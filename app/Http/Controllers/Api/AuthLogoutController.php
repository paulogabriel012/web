<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Api\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;

final class AuthLogoutController extends Controller
{
    /**
     * Revoke the current browser session token chain.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $token = $user?->token();

        if ($token instanceof AccessToken) {
            $token->revoke();

            $tokenId = $token->toArray()['oauth_access_token_id'] ?? null;

            if (is_string($tokenId) && $tokenId !== '') {
                Passport::refreshToken()
                    ->newQuery()
                    ->where('access_token_id', $tokenId)
                    ->update(['revoked' => true]);
            }
        }

        return ApiResponse::noContent($request);
    }
}
