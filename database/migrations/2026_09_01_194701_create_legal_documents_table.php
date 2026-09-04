<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * ScholarNest's own legal documents, editable by the ScholarNest Team.
 *
 * They began as markdown files in resources/legal, which was right while they
 * were being drafted and wrong the moment they were published: a lawyer's
 * amendment to the Privacy Policy should not require a developer and a deploy,
 * and on most hosting the application cannot write to resources/ anyway.
 *
 * So the files become the SEED and the table becomes the live copy. The
 * markdown is still in the repository - it is what a reviewer reads, and what a
 * fresh install starts from - but once a row exists, the row is what schools
 * see. A later deploy carrying an improved file will not silently overwrite an
 * edit somebody made on purpose.
 */
return new class extends Migration
{
    /**
     * The six publishable documents, seeded from their files.
     *
     * The audit and gap report in the same directory are deliberately absent:
     * they are internal, and a row here is what makes a document reachable.
     *
     * @var array<string, array{file: string, title: string, summary: string}>
     */
    private const SEED = [
        'terms' => [
            'file' => 'terms-and-conditions.md',
            'title' => 'Terms & Conditions',
            'summary' => 'The agreement between ScholarNest and your school: what a subscription grants, what each side is responsible for, and how it ends.',
        ],
        'privacy' => [
            'file' => 'privacy-policy.md',
            'title' => 'Privacy Policy',
            'summary' => 'What personal information the platform holds, why, who can reach it, and what rights people have over it.',
        ],
        'cookies' => [
            'file' => 'cookie-policy.md',
            'title' => 'Cookie & Browser Storage Policy',
            'summary' => 'Every cookie and browser storage key ScholarNest uses, what each is for, and which can be turned off.',
        ],
        'data-retention' => [
            'file' => 'data-retention-and-deletion-policy.md',
            'title' => 'Data Retention & Deletion Policy',
            'summary' => 'How long records are kept, what happens when a subscription ends, and how deletion works.',
        ],
        'security' => [
            'file' => 'security-and-data-handling-statement.md',
            'title' => 'Security & Data Handling Statement',
            'summary' => 'The protections that are actually in place, and how we respond to a security incident.',
        ],
        'school-responsibilities' => [
            'file' => 'school-data-processing-framework.md',
            'title' => 'School Responsibilities Framework',
            'summary' => 'Who is responsible for what: ScholarNest, the school, and individual users.',
        ],
    ];

    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();

            // The slug is the public URL - /legal/privacy - and is fixed. It is
            // not editable from the admin screen: changing it would break every
            // link already printed on a registration form, in an email, or in
            // somebody's bookmarks.
            $table->string('slug')->unique();

            $table->string('title');
            $table->text('summary')->nullable();

            // Markdown, not HTML. It is what a lawyer can read and amend
            // without meeting a tag, and it is what the files were written in.
            $table->longText('body');

            $table->string('version')->default('1.0');
            $table->string('effective_date')->nullable();

            $table->boolean('is_published')->default(true);

            // Who last changed it, for the audit trail. nullOnDelete rather
            // than cascade: losing the editor's account must not take the
            // document with it.
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        $this->seedFromFiles();
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }

    /**
     * Load the drafted markdown into the table.
     *
     * A missing file is skipped rather than fatal. The documents are in the
     * repository, but a migration that refuses to run because a resource file
     * moved would take the whole deploy down with it - and an absent row simply
     * means that document is not published yet, which the admin screen shows.
     */
    private function seedFromFiles(): void
    {
        $now = now();

        foreach (self::SEED as $slug => $document) {
            $path = resource_path('legal/'.$document['file']);

            if (! File::exists($path)) {
                continue;
            }

            [$frontMatter, $body] = $this->split(File::get($path));

            DB::table('legal_documents')->insert([
                'slug' => $slug,
                'title' => $document['title'],
                'summary' => $document['summary'],
                'body' => trim($body),
                'version' => $frontMatter['version'] ?? '1.0',
                'effective_date' => $frontMatter['effective_date'] ?? null,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
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
