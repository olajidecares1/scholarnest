<?php

namespace App\Http\Controllers;

use App\Models\IssuedIdCard;
use Illuminate\View\View;

/**
 * Deliberately ungated and unauthenticated: anyone scanning a printed
 * card's QR code must be able to reach this page. There is no Basic-plan
 * exposure risk - cards can only ever be issued by Standard/Exclusive
 * schools (id_card_access middleware guards generation, not this).
 */
class IdCardVerificationController extends Controller
{
    public function show(IssuedIdCard $card): View
    {
        return view('id-verify.show', [
            'card' => $card,
            'school' => $card->school,
            'holder' => $card->holder(),
        ]);
    }
}
