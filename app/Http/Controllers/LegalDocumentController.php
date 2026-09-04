<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\View\View;

/**
 * ScholarNest's own legal documents, open to anyone.
 *
 * Deliberately unauthenticated. These are linked from the registration form,
 * which nobody has an account on yet - a school being asked to agree to terms
 * it cannot read until after it has registered would be the same broken flow
 * these pages exist to fix.
 */
class LegalDocumentController extends Controller
{
    public function index(): View
    {
        return view('legal.index', [
            'documents' => LegalDocument::inOrder()->where('is_published', true),
        ]);
    }

    /**
     * The slug is constrained on the route to the fixed list in the model, and
     * published() refuses anything unpublished - so it never becomes a lookup
     * for arbitrary content.
     */
    public function show(string $document): View
    {
        return view('legal.show', [
            'document' => LegalDocument::published($document),
            'others' => LegalDocument::inOrder()->where('is_published', true),
        ]);
    }
}
