<?php

namespace App\Http\Controllers\Concerns;

/**
 * The rules that govern a CBT document upload, in one place.
 *
 * Two controllers accept these documents, the Super Admin's question bank and
 * a teacher's own test, and a limit that differs between them is a limit that
 * is wrong in one of them. The size in particular is quoted to the user on the
 * upload form, so the number they are told and the number enforced have to come
 * from the same constant or they will drift apart.
 */
trait AcceptsCbtDocumentUploads
{
    /** @var list<string> */
    private const ALLOWED_MIMES = ['pdf', 'doc', 'docx'];

    /**
     * The largest document we accept, in kilobytes.
     *
     * PHP's own upload_max_filesize and post_max_size must both exceed this or
     * an oversized file is discarded by PHP before Laravel ever sees it, which
     * surfaces as the confusing "the file field is required" rather than a size
     * error.
     */
    private const MAX_KILOBYTES = 21504;

    /**
     * @return array<int, string>
     */
    protected function documentRules(): array
    {
        return [
            'required',
            'file',
            'mimes:'.implode(',', self::ALLOWED_MIMES),
            'max:'.self::MAX_KILOBYTES,
        ];
    }

    /**
     * The size limit as it should be written on the form.
     */
    public static function maxUploadLabel(): string
    {
        return (int) (self::MAX_KILOBYTES / 1024).'MB';
    }
}
