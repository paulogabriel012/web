<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Api\ApiException;
use App\Domain\Api\ApiResponse;
use App\Domain\Releases\ReleaseService;
use App\Enums\Api\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LatestReleaseRequest;
use Illuminate\Http\JsonResponse;

/**
 * @unauthenticated
 */
final class ReleaseController extends Controller
{
    public function __construct(
        private readonly ReleaseService $releases,
    ) {}

    /**
     * Latest compatible browser release metadata and a short-lived download link.
     *
     * @unauthenticated
     */
    public function latest(LatestReleaseRequest $request): JsonResponse
    {
        $input = $request->releaseInput();
        $release = $this->releases->latest(
            platform: $input['platform'],
            architecture: $input['architecture'],
            currentVersion: $input['current_version'],
        );

        if ($release === null) {
            throw ApiException::make(
                ErrorCode::ReleaseNotFound,
                'No compatible release is available.',
                404,
            );
        }

        return ApiResponse::data($this->releases->payload($release), $request);
    }
}
