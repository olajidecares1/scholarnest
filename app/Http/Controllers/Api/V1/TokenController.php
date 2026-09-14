<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IssueTokenRequest;
use App\Models\AuditLog;
use App\Models\School;
use App\Support\ApiAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issuing and revoking API tokens.
 *
 * The credential check is the portal's, unchanged, see IssueTokenRequest.
 * What differs is what happens once it passes: instead of a session cookie the
 * caller is handed a bearer token, and the guard's in-memory login is thrown
 * away with the request.
 *
 * That last part is worth stating plainly, because authenticateAs() does log
 * the user into a session guard. Nothing persists: the API middleware group
 * has no StartSession, so the session store is written in memory and never
 * saved, and no cookie is issued. The login exists only long enough to hand us
 * the authenticated model.
 */
class TokenController extends Controller
{
    /**
     * How long a token lives without being renewed.
     *
     * Long enough that a parent checking results once a term is not signed out
     * between visits, short enough that a phone lost and forgotten stops being
     * a way in. A client refreshes by signing in again.
     */
    private const TOKEN_LIFETIME_DAYS = 60;

    /**
     * @throws ValidationException
     */
    public function store(IssueTokenRequest $request): JsonResponse
    {
        $school = $request->resolveSchool();
        $guard = $request->string('role')->toString();

        $request->authenticateAs($guard, $school);

        $account = Auth::guard($guard)->user();

        $this->assertPlanIncludesThisPortal($school, $guard);

        // A password the school has told this account to change is not a
        // password it may keep using. The web portal forces the change before
        // anything else; refusing the token here is the same rule, and is the
        // honest answer until there is an endpoint to change it through.
        if ($account->must_change_password) {
            throw ValidationException::withMessages([
                'login' => 'Sign in through your school portal first and set a new password, then sign in here.',
            ]);
        }

        $token = $account->createToken(
            $request->string('device_name')->toString(),
            ['*'],
            now()->addDays(self::TOKEN_LIFETIME_DAYS),
        );

        AuditLog::record(
            'api.token_issued',
            "Issued an API token to \"{$request->string('device_name')}\".",
            $account,
            ApiAccount::displayName($account),
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'account' => ApiAccount::describe($account, $guard),
        ], 201);
    }

    /**
     * The same plan gate the browser portals apply.
     *
     * Student and guardian portals are the premium tier; the staff portal is
     * on every plan, because it is where teachers take attendance and enter
     * marks rather than a paid extra. Without this the API would be a way
     * round a restriction the browser enforces, which is the one thing a
     * second interface onto the same data must never become.
     *
     * A Basic school's parents are not shut out of results by this, they
     * reach them through the result-token flow, which needs no account.
     *
     * @throws ValidationException
     */
    private function assertPlanIncludesThisPortal(School $school, string $guard): void
    {
        $included = $guard === 'staff'
            ? $school->hasActiveSubscription()
            : $school->hasPortalAccounts();

        if ($included) {
            return;
        }

        throw ValidationException::withMessages([
            'login' => 'This school\'s plan does not include the app. Ask your school about upgrading.',
        ]);
    }

    /**
     * Sign this device out. Other devices keep working.
     */
    public function destroyCurrent(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['revoked' => 'current']);
    }

    /**
     * Sign every device out, what somebody wants after losing a phone.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $count = $request->user()->tokens()->count();

        $request->user()->tokens()->delete();

        AuditLog::record(
            'api.tokens_revoked',
            "Revoked every API token ({$count}).",
            $request->user(),
            ApiAccount::displayName($request->user()),
        );

        return response()->json(['revoked' => $count]);
    }
}
