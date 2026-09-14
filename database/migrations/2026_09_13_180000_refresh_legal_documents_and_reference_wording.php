<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the stored legal documents, plan taglines and exam body descriptions
 * up to date with the revised wording in the repository.
 *
 * Only copies nobody has changed are replaced. A legal document whose wording
 * was edited from the AkademicNest Team screen is left alone; a tagline or
 * description is only changed while it still reads exactly as it was seeded.
 */
return new class extends Migration
{
    private const DOCUMENTS = [
        'terms' => 'terms-and-conditions.md',
        'privacy' => 'privacy-policy.md',
        'cookies' => 'cookie-policy.md',
        'data-retention' => 'data-retention-and-deletion-policy.md',
        'security' => 'security-and-data-handling-statement.md',
        'school-responsibilities' => 'school-data-processing-framework.md',
    ];

    /**
     * SHA-256 of each document's body as it was seeded, before this revision.
     */
    private const SEEDED_HASHES = [
        'terms' => '3850a211ba87db634f8d42a3ab3b4f24c92a767afb7cb4401fe0131b0521fdde',
        'privacy' => '201fcb01e4e68ae9fd2cdc0b22db7053533802c525e15bf3c68cb1b0f4cca48c',
        'cookies' => 'e6581dd4e6eac5f605a9c513651e0675852302e918dab764cef1a73135a2b346',
        'data-retention' => 'd0dc9fe0e9e1b366790eeb429ca26c521cc7817b84c6029a95608b377e6c06e4',
        'security' => '6903fbecf04bb0fa77ddbb4351494fd0213fca003e03ae129c61f596cc0ce785',
        'school-responsibilities' => '66482ab53cb2e98b04082e755af7fb9749ecdb2404e1d300163ad45e10cd8dd9',
    ];

    private const PLAN_TAGLINES = [
        'Perfect for schools that need to run their academics (results, attendance and report cards) without a public website.',
    ];

    private const EXAM_BODY_DESCRIPTIONS = [
        'West African Examinations Council, which conducts the WASSCE for senior secondary school students.',
        'National Examinations Council, which conducts the SSCE, an alternative senior secondary certificate exam.',
        'Joint Admissions and Matriculation Board, which conducts the UTME for tertiary institution admission.',
        'Basic Education Certificate Examination, taken by JSS3 students to complete basic education.',
        'National Business and Technical Examinations Board, which conducts NBC/NTC exams for senior secondary technical/business students.',
        "Junior WAEC, the West African Examinations Council's exam for junior secondary students.",
    ];

    public function up(): void
    {
        $this->refreshLegalDocuments();

        if (Schema::hasTable('plans')) {
            foreach (self::PLAN_TAGLINES as $new) {
                DB::table('plans')->where('tagline', $this->asFirstSeeded($new))->update(['tagline' => $new]);
            }
        }

        if (Schema::hasTable('cbt_exam_bodies')) {
            foreach (self::EXAM_BODY_DESCRIPTIONS as $new) {
                DB::table('cbt_exam_bodies')->where('description', $this->asFirstSeeded($new))->update(['description' => $new]);
            }
        }
    }

    /**
     * The wording as it was first seeded, where each aside was set off with a
     * spaced hyphen rather than a comma or brackets.
     */
    private function asFirstSeeded(string $wording): string
    {
        $separator = ' '.chr(45).' ';

        return strtr($wording, [
            ', which conducts' => $separator.'conducts',
            ', taken by' => $separator.'taken by',
            'WAEC, the' => 'WAEC'.$separator.'the',
            ' (results' => $separator.'results',
            'cards) ' => 'cards'.$separator,
        ]);
    }

    public function down(): void
    {
        // The earlier wording is in version control; nothing to restore here.
    }

    private function refreshLegalDocuments(): void
    {
        if (! Schema::hasTable('legal_documents')) {
            return;
        }

        foreach (self::DOCUMENTS as $slug => $file) {
            $path = resource_path('legal/'.$file);

            if (! File::exists($path)) {
                continue;
            }

            $row = DB::table('legal_documents')->where('slug', $slug)->first();

            if (! $row || ! $this->unchangedByAnyone($slug, $row)) {
                continue;
            }

            [$frontMatter, $body] = $this->split(File::get($path));

            DB::table('legal_documents')->where('id', $row->id)->update([
                'body' => trim($body),
                'version' => $frontMatter['version'] ?? '1.0',
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Never edited, or saved from the admin screen without any change to the
     * wording it was seeded with.
     */
    private function unchangedByAnyone(string $slug, object $row): bool
    {
        if ($row->updated_by === null) {
            return true;
        }

        $body = trim(str_replace("\r\n", "\n", (string) $row->body));

        return hash('sha256', $body) === (self::SEEDED_HASHES[$slug] ?? null);
    }

    /**
     * Separate the YAML front matter from the markdown.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function split(string $raw): array
    {
        $raw = ltrim($raw);

        if (! str_starts_with($raw, '---')) {
            return [[], $raw];
        }

        $end = strpos($raw, "\n---", 3);

        if ($end === false) {
            return [[], $raw];
        }

        $values = [];

        foreach (preg_split('/\R/', substr($raw, 3, $end - 3)) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $values[trim($key)] = trim(trim(trim($value), '"'));
        }

        return [$values, substr($raw, $end + 4)];
    }
};
