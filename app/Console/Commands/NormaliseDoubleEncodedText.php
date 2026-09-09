<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair text that was stored HTML-encoded.
 *
 * THE CAUSE, which is fixed separately and first: a caller wrote
 * `value="{{ $school->name }}"` on <x-text-field>, so Blade escaped the value
 * into a string and the component's own `value="{{ $resolvedValue }}"` escaped
 * it again. The browser decoded one layer when the form was submitted, so
 * "Arise & Shine" came back as "Arise &amp; Shine" and was saved that way.
 * Every subsequent save added another layer: &amp;amp;, &amp;amp;amp;.
 *
 * This cleans up what that left behind. It is not a find-and-replace across
 * the database - it decodes REPEATEDLY UNTIL STABLE, so a value mangled three
 * times comes back as far as the text the person actually typed, and a value
 * that was never encoded is left exactly as it is.
 *
 * WHY THAT IS SAFE HERE. Decoding is only ever applied to short, plain-text
 * columns - names, titles, addresses, captions - none of which are rendered as
 * HTML anywhere in the application. Blade escapes them on output, which is
 * what keeps them safe; storing them decoded is the correct thing and the
 * escaping stays exactly where it belongs. Long-form and rich-text columns are
 * deliberately NOT touched, because there the ampersands might be part of
 * content somebody meant to write.
 *
 * Run it with --dry-run first. It prints every change it would make.
 */
class NormaliseDoubleEncodedText extends Command
{
    protected $signature = 'akademicnest:normalise-encoded-text {--dry-run : Show what would change without writing anything}';

    protected $description = 'Repair user text that was stored HTML-encoded by the old double-escaping form fields';

    /**
     * Table => the plain-text columns on it that are safe to decode.
     *
     * Deliberately a list rather than "every string column": a column is on
     * here because it holds a short piece of text a person typed and the
     * application renders it through Blade's escaping. Anything that might
     * legitimately contain markup is absent on purpose.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'schools' => ['name', 'address', 'motto'],
        'school_websites' => [
            'hero_title', 'hero_subtitle', 'about_headline', 'slogan', 'slogan_tagline',
            'topbar_announcement', 'topbar_badge_text', 'topbar_link_text', 'cta_text',
            'cta_title', 'cta_subtitle', 'contact_address', 'principal_name',
            'principal_title', 'quote_author', 'quote_author_role', 'whats_happening_title',
        ],
        'users' => ['name'],
        'staff' => ['first_name', 'last_name', 'emergency_contact_name'],
        'students' => ['first_name', 'last_name', 'middle_name'],
        'guardians' => ['name'],
        'news_posts' => ['title', 'category', 'excerpt'],
        'school_events' => ['title', 'location'],
        'school_gallery_images' => ['caption'],
        'school_facilities' => ['name', 'category'],
        'academic_levels' => ['name'],
        'school_classes' => ['name'],
        'subjects' => ['name'],
        'contact_messages' => ['name', 'subject'],
        'misconduct_reports' => ['reporter_name', 'subject', 'location'],
        'cbt_tests' => ['title', 'subject', 'class_name'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;

        foreach (self::COLUMNS as $table => $columns) {
            $columns = $this->existingColumns($table, $columns);

            if ($columns === []) {
                continue;
            }

            $changed += $this->repairTable($table, $columns, $dryRun);
        }

        if ($changed === 0) {
            $this->info('Nothing to repair - no double-encoded text found.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info($dryRun
            ? "{$changed} value(s) would be repaired. Run without --dry-run to apply."
            : "{$changed} value(s) repaired.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $columns
     */
    private function repairTable(string $table, array $columns, bool $dryRun): int
    {
        $changed = 0;

        DB::table($table)->select(['id', ...$columns])->orderBy('id')->chunk(200, function ($rows) use ($table, $columns, $dryRun, &$changed) {
            foreach ($rows as $row) {
                $updates = [];

                foreach ($columns as $column) {
                    $current = $row->{$column};

                    if (! is_string($current) || $current === '') {
                        continue;
                    }

                    $decoded = $this->fullyDecode($current);

                    if ($decoded !== $current) {
                        $updates[$column] = $decoded;
                        $changed++;

                        $this->line("  {$table}#{$row->id} {$column}: ".$current.'  ->  '.$decoded);
                    }
                }

                if ($updates !== [] && ! $dryRun) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            }
        });

        return $changed;
    }

    /**
     * Decode until it stops changing.
     *
     * One pass would turn "&amp;amp;" into "&amp;" and leave it still wrong.
     * The loop is bounded because a value that is not encoded decodes to
     * itself and stops on the first pass; the cap is only there so a
     * pathological string cannot spin.
     */
    private function fullyDecode(string $value): string
    {
        for ($i = 0; $i < 5; $i++) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $value) {
                return $value;
            }

            $value = $decoded;
        }

        return $value;
    }

    /**
     * The columns from the list above that this database actually has.
     *
     * Column by column, NOT all-or-nothing. Skipping the whole table when one
     * name was wrong is how the first run of this quietly passed over
     * schools.name - the single most visibly broken value in the database -
     * because "address" happens not to exist on that table.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function existingColumns(string $table, array $columns): array
    {
        $schema = DB::getSchemaBuilder();

        if (! $schema->hasTable($table)) {
            return [];
        }

        return array_values(array_filter(
            $columns,
            fn (string $column) => $schema->hasColumn($table, $column),
        ));
    }
}
