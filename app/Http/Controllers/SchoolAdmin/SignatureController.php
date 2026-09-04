<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\RegistersSignatures;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The School Admin's own signature - which is the school's Principal
 * signature.
 *
 * School Admin is the Principal on this platform. There is no separate
 * Principal account, no "use this as the school's signature" tick, and no
 * school-level copy: what an admin registers here IS what prints on the
 * Principal's line of their school's report cards and ID cards, in gold. One
 * authoritative signature per school, resolved by
 * App\Support\PrincipalSignature.
 *
 * The signer comes from the web session and from nowhere else - see
 * RegistersSignatures for why there is no id to swap.
 */
class SignatureController extends Controller
{
    use RegistersSignatures;

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->storeSignatureFor($user, $request, 'admin-signatures', $user->name);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        // Withdrawing takes it off the school's documents too, because it was
        // never copied anywhere: the Principal's line simply resolves to
        // nothing and prints blank, which is the honest placeholder.
        return $this->destroySignatureFor($user, $user->name);
    }
}
