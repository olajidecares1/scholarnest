<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AuditLog;
use App\Rules\UploadedImage;
use App\Services\SignatureImage;
use App\Support\Uploads\ImageProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Registering a signature, for whoever is signing.
 *
 * THE ONE RULE THIS FILE EXISTS TO ENFORCE: the signer is a model the caller
 * has already resolved from the session, $request->user('staff'),
 * $request->user(), and nothing in the request is ever consulted to decide
 * whose signature this is.
 *
 * There is deliberately no signer id, uuid, email or staff number in the
 * accepted input, so there is nothing to tamper with: not a form field, not a
 * query string, not a route parameter, not a JSON key. A request that carries
 * one is not rejected, because it is not read, which is stronger than
 * rejecting it, since a check can be forgotten and an absent parameter cannot.
 *
 * The database backs this up. `signatures` is unique on (owner_type,
 * owner_id), so one signer has one signature and re-registering replaces it
 * rather than accumulating marks nobody can choose between.
 */
trait RegistersSignatures
{
    /**
     * Save the drawn signature against this signer.
     *
     * Answers JSON because the pad posts with fetch() and stays on the page.
     */
    protected function storeSignatureFor(Model $signer, Request $request, string $directory, string $signerLabel): JsonResponse
    {
        $request->validate([
            'signature' => ['required', 'string'],
        ]);

        try {
            $path = app(SignatureImage::class)->store($request->string('signature')->toString(), $directory);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $signer->registerSignature($path);

        AuditLog::record(
            'signature.registered',
            $signerLabel.' registered their signature.',
            $signer,
            actorName: $signerLabel,
        );

        return response()->json([
            'message' => 'Your signature was registered.',
            'signature_url' => $signer->signatureDataUri(),
        ]);
    }

    /**
     * Lift a signature off an uploaded photograph or scan, for the signer to
     * look at before keeping it.
     *
     * Nothing is stored and nobody's signature changes here. The preview goes
     * back to the page, and keeping it is the same POST to the same store
     * action as a drawn signature, so a signature has exactly one way in and
     * is re-checked there whichever way it was made. That also means the
     * signer always sees what was found before it can appear on a document.
     */
    protected function extractSignatureFrom(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => UploadedImage::rules(ImageProfile::Signature, required: true),
        ]);

        try {
            $png = app(SignatureImage::class)->extractFromPhoto((string) $request->file('photo')->getRealPath());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Here is the signature we found. Keep it if it looks right.',
            'preview' => 'data:image/png;base64,'.base64_encode($png),
        ]);
    }

    /**
     * Withdraw this signer's signature.
     */
    protected function destroySignatureFor(Model $signer, string $signerLabel): JsonResponse
    {
        $signer->withdrawSignature();

        AuditLog::record(
            'signature.withdrawn',
            $signerLabel.' withdrew their signature.',
            $signer,
            actorName: $signerLabel,
        );

        return response()->json(['message' => 'Your signature was removed.']);
    }
}
