<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Altcha\AltchaService;
use Illuminate\Http\JsonResponse;

final class AltchaChallengeController extends Controller
{
    public function __invoke(AltchaService $altcha): JsonResponse
    {
        abort_unless($altcha->enabled(), 404);

        return response()->json($altcha->createChallenge()->toArray());
    }
}
