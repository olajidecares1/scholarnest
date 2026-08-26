<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who this token belongs to.
 *
 * The first call a client makes after signing in, and the one it makes on
 * launch to find out whether the token it stored is still any good. Because
 * the active-account check runs on every API request, a 401 here is a complete
 * answer: sign in again.
 */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ApiAccount::describe($request->user()),
        ]);
    }
}
