<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\IdCardHolderType;
use App\Enums\IssuedIdCardStatus;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\IssuedIdCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IssuedIdCardController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $cards = $school->issuedIdCards()
            ->with('template')
            ->when($request->filled('type'), fn ($query) => $query->where('holder_type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('issued_at')
            ->paginate(20)
            ->withQueryString();

        return view('school-admin.id-cards.issued', [
            'cards' => $cards,
            'holderTypes' => IdCardHolderType::cases(),
            'statuses' => IssuedIdCardStatus::cases(),
        ]);
    }

    public function revoke(Request $request, IssuedIdCard $card): RedirectResponse
    {
        $this->authorizeSchoolOwnership($card);

        $card->update([
            'status' => IssuedIdCardStatus::Revoked,
            'revoked_at' => now(),
        ]);

        AuditLog::record('id-card.revoked', "Revoked ID card {$card->card_number}.", $card);

        return back()->with('status', "Card {$card->card_number} was revoked.");
    }
}
