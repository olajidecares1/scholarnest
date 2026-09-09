<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One of AkademicNest's own legal documents - the Terms, the Privacy Policy, and the
 * four that go with them.
 *
 * Edited by the AkademicNest Team, read by anyone. The markdown files in
 * resources/legal seeded this table and remain in the repository as the
 * reviewed original; the row is what schools actually see.
 *
 * THE SLUG IS THE PUBLIC URL and is not editable. /legal/privacy is printed on
 * the registration form and will be in emails, bookmarks and, before long, a
 * lawyer's letter. A renamed slug breaks all of them silently.
 *
 * INTERNAL NOTES ARE STRIPPED ON THE WAY OUT. These documents carry passages
 * addressed to counsel rather than to schools, fenced in the source. A school
 * agreeing to the Terms must not be shown them, so renderedHtml() removes them
 * - and a test asserts no published page contains the fences or the phrases
 * inside them, so a note added without a fence fails the build instead of
 * quietly appearing on a public page.
 */
class LegalDocument extends Model
{
    /**
     * Every slug the public routes will serve.
     *
     * A fixed list, not a query: the route constraint is built from it at boot,
     * and a slug is therefore incapable of naming anything else. It is also
     * what keeps the audit and gap report - which live in the same source
     * directory - unreachable.
     *
     * @var list<string>
     */
    public const SLUGS = [
        'terms',
        'privacy',
        'cookies',
        'data-retention',
        'security',
        'school-responsibilities',
    ];

    /**
     * A passage addressed to counsel, fenced in the markdown.
     */
    private const INTERNAL_OPEN = '<!-- internal:start -->';

    private const INTERNAL_CLOSE = '<!-- internal:end -->';

    /**
     * Deliberately without `slug`. It is set once, by the seeding migration,
     * and nothing in the admin screens may change it.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'summary',
        'body',
        'version',
        'effective_date',
        'is_published',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    /**
     * Bound by slug, so an admin URL reads .../legal/privacy and matches the
     * public one rather than carrying a row id nobody can recognise.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    /**
     * Every document, in the order the public index and the admin screen list
     * them - the order of SLUGS, so the Terms come first and the Privacy Policy
     * second, rather than alphabetically or by whichever row was written first.
     *
     * Sorted in PHP: a CASE expression would do it in SQL, but there are six
     * rows and the sort below is easier to read than the SQL replacing it.
     *
     * @return Collection<int, LegalDocument>
     */
    public static function inOrder(): Collection
    {
        return static::query()
            ->with('updatedBy')
            ->get()
            ->sortBy(fn (self $document) => array_search($document->slug, self::SLUGS, true))
            ->values();
    }

    /**
     * The document behind a public URL.
     *
     * Unpublished counts as absent: a document the AkademicNest Team has taken down
     * must 404 rather than half-render, because a page that exists but is not
     * ready is worse than one that is honestly missing.
     *
     * @throws NotFoundHttpException
     */
    public static function published(string $slug): self
    {
        $document = static::where('slug', $slug)->where('is_published', true)->first();

        if (! $document) {
            throw new NotFoundHttpException;
        }

        return $document;
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    /**
     * The document as a school sees it: markdown to HTML, internal passages
     * removed, tables given their own scroll.
     */
    public function renderedHtml(): string
    {
        return self::toHtml($this->body);
    }

    /**
     * Rendered with the internal passages LEFT IN.
     *
     * Only for the AkademicNest Team's own preview, where seeing exactly what is in
     * the document - including the notes to counsel - is the point.
     */
    public function renderedHtmlForReview(): string
    {
        return self::wrapTables(Str::markdown(
            self::withoutFenceMarkers($this->body),
            ['html_input' => 'strip'],
        ));
    }

    /**
     * How many passages are still marked for counsel.
     *
     * Surfaced on the admin screen so nobody has to remember that a published
     * document still has unresolved legal questions inside it.
     */
    public function internalNoteCount(): int
    {
        return substr_count($this->body, self::INTERNAL_OPEN);
    }

    /**
     * How many placeholders are still waiting on a real value.
     */
    public function placeholderCount(): int
    {
        return substr_count($this->body, 'TO BE PROVIDED');
    }

    private static function toHtml(string $markdown): string
    {
        $markdown = self::withoutInternalNotes($markdown);

        // The heading and the version line are drawn by the page from the
        // columns above, so the document's own copies would appear twice.
        $markdown = preg_replace('/\A\s*#\s+.*?\R/', '', $markdown, 1) ?? $markdown;
        $markdown = preg_replace('/\A\s*\*\*Version.*?\R/', '', $markdown, 1) ?? $markdown;

        return self::wrapTables(Str::markdown($markdown, ['html_input' => 'strip']));
    }

    /**
     * Remove every fenced internal passage.
     *
     * An unclosed fence removes everything after it rather than nothing. That
     * is the right way round to fail: a page missing its tail is obvious and
     * gets fixed, whereas a note to counsel published to schools might never be
     * noticed.
     */
    private static function withoutInternalNotes(string $markdown): string
    {
        $open = preg_quote(self::INTERNAL_OPEN, '/');
        $close = preg_quote(self::INTERNAL_CLOSE, '/');

        return preg_replace('/'.$open.'.*?(?:'.$close.'|\z)/s', '', $markdown) ?? $markdown;
    }

    private static function withoutFenceMarkers(string $markdown): string
    {
        return str_replace([self::INTERNAL_OPEN, self::INTERNAL_CLOSE], '', $markdown);
    }

    /**
     * Give every table its own horizontal scroll.
     *
     * The data inventory and the responsibility matrix are the most useful
     * parts of these documents and the most likely to be read on a phone. A
     * table wide enough to hold them cannot be narrowed to 320px, so it scrolls
     * inside its own box rather than dragging the page sideways with it.
     */
    private static function wrapTables(string $html): string
    {
        return str_replace(
            ['<table>', '</table>'],
            ['<div class="legal-table-scroll"><table>', '</table></div>'],
            $html,
        );
    }
}
