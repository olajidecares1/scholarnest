<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LegalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The ScholarNest Team's editor for the platform's own legal documents.
 *
 * These are not blog posts. Each one is a document a school has agreed to, and
 * changing it changes the terms of a live contract - so this screen is
 * deliberately more careful than the rest of the CMS:
 *
 *   - Editing requires its own permission, not the general CMS one.
 *   - Every save is written to the audit log with who did it.
 *   - The version and effective date are edited alongside the text, because a
 *     change to the terms without a change to the version is a change nobody
 *     can later prove happened.
 *   - The slug is never accepted from the form. It is the public URL that
 *     schools have been given, and a renamed one breaks every link silently.
 */
class LegalDocumentController extends Controller
{
    public function index(): View
    {
        return view('super-admin.legal.index', [
            'documents' => LegalDocument::inOrder(),
        ]);
    }

    /**
     * Bound by slug rather than id, so the admin URL reads
     * .../legal/privacy and matches the public one.
     */
    public function edit(LegalDocument $document): View
    {
        return view('super-admin.legal.edit', [
            'document' => $document,
        ]);
    }

    public function update(Request $request, LegalDocument $document): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:500'],

            // No maximum. A Privacy Policy is as long as it needs to be, and a
            // limit here would silently truncate one mid-sentence.
            'body' => ['required', 'string'],

            'version' => ['required', 'string', 'max:40'],
            'effective_date' => ['nullable', 'string', 'max:60'],
            'is_published' => ['nullable', 'boolean'],
        ], [
            'body.required' => 'A legal document cannot be saved empty.',
            'version.required' => 'Give this version a number, so a school can tell which one it agreed to.',
        ]);

        $wasVersion = $document->version;

        $document->update([
            ...$validated,

            // Absent from the request when the box is unticked, which is what a
            // browser sends - so it has to be read from the boolean helper
            // rather than from $validated, where it would simply be missing and
            // leave the document published.
            'is_published' => $request->boolean('is_published'),

            'updated_by' => $request->user()->id,
        ]);

        AuditLog::record(
            'legal-document.updated',
            sprintf(
                'Updated the %s (version %s%s).',
                $document->title,
                $document->version,
                $wasVersion !== $document->version ? ", was {$wasVersion}" : '',
            ),
            $document,
        );

        return redirect()
            ->route('super-admin.legal.edit', $document)
            ->with('status', "\"{$document->title}\" was saved. Schools see the new text immediately.");
    }
}
