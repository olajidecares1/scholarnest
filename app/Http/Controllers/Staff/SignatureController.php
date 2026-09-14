<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\RegistersSignatures;
use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A class teacher's own signature, drawn in their portal.
 *
 * The signer comes from the staff session and from nowhere else, see
 * RegistersSignatures for why there is no id to swap.
 */
class SignatureController extends Controller
{
    use RegistersSignatures;

    public function store(Request $request, School $school): JsonResponse
    {
        $staff = $request->user('staff');

        return $this->storeSignatureFor($staff, $request, 'staff-signatures', $staff->fullName());
    }

    public function destroy(Request $request, School $school): JsonResponse
    {
        $staff = $request->user('staff');

        return $this->destroySignatureFor($staff, $staff->fullName());
    }
}
