<?php

namespace App\Services\DocumentExtraction;

use Smalot\PdfParser\Document;
use Smalot\PdfParser\Page;
use Throwable;

/**
 * A PDF read the way it is printed, not the way it is stored.
 *
 * WHY THIS EXISTS. Asking a PDF for "its text" gets the text in the order the
 * file happens to store it, which on a two-column past-paper is both columns
 * braided together:
 *
 *     1. If M represents the median and D the mode
 *     10. If x + 2 and x - 1 are factors of
 *     measurements 5, 9, 3, 5, 8 then (M,D) is
 *     2kx + 24, find the values of l and k
 *
 * No parser downstream can recover question 1 from that, and every JAMB
 * compilation anyone uploads is printed in two columns.
 *
 * So each run of text is taken with the position it is drawn at, the page is
 * split down its gutter, and each column is read top to bottom on its own. A
 * single-column document has no gutter to find and is read straight through,
 * which is the same thing this does with one column instead of two.
 *
 * Superscripts are drawn as their own little line a few points above the
 * baseline. Those are folded back into the line they belong to (x², 45°)
 * rather than left stranded as a line containing "2".
 */
class PdfLayoutReader
{
    /**
     * Two runs this far apart vertically are on the same printed line.
     *
     * Generous enough for the baseline wobble in a scanned-then-typeset paper,
     * tight enough not to weld two lines of 10pt text together.
     */
    private const LINE_TOLERANCE = 2.5;

    /**
     * How far above a line a superscript sits, at the sizes papers are set in.
     */
    private const SUPERSCRIPT_RISE = [1.5, 10.0];

    /**
     * A gutter narrower than this is a wide word space, not a column break.
     */
    private const MINIMUM_GUTTER = 12.0;

    /**
     * Read every page in printed order, or null if this PDF does not carry the
     * positions needed to do it (in which case the caller falls back to the
     * library's own plain text).
     */
    public function read(Document $pdf): ?string
    {
        try {
            $pages = array_map(fn (Page $page) => $this->itemsOf($page), $pdf->getPages());
        } catch (Throwable) {
            return null;
        }

        $items = array_merge(...$pages);

        if ($items === []) {
            return null;
        }

        $gutter = $this->gutter($items);
        $out = [];

        foreach ($pages as $pageItems) {
            if ($pageItems === []) {
                continue;
            }

            foreach ($this->blocksOf($pageItems, $gutter) as $block) {
                foreach ($this->linesOf($block) as $line) {
                    $out[] = $line;
                }
            }

            // A blank line between pages, so nothing reads as one paragraph
            // across a page break.
            $out[] = '';
        }

        $text = trim(implode("\n", $out));

        return $text === '' ? null : $text;
    }

    /**
     * A page in reading order: the running head, then each column, then the
     * footer.
     *
     * The head and the foot are kept whole. "Mathematics 1983" is printed
     * across the top of both columns, and splitting it would file every
     * question on the page under no year at all.
     *
     * @param  list<array{x: float, y: float, text: string}>  $items
     * @return list<list<array{x: float, y: float, text: string}>>
     */
    private function blocksOf(array $items, ?float $gutter): array
    {
        if ($gutter === null) {
            return [$items];
        }

        // A running head is recognised by the white space under it: the first
        // line of the body sits one line below its neighbour, a running head
        // sits well clear of everything.
        $lines = array_values(array_unique(array_map(fn (array $item) => $this->bandOf($item['y']), $items)));
        rsort($lines);

        $head = count($lines) > 2 && $this->standsApart($lines) ? $lines[0] : null;
        $foot = count($lines) > 2 && $this->standsApart(array_reverse($lines)) ? $lines[count($lines) - 1] : null;

        $heading = [];
        $footer = [];
        $body = [];

        foreach ($items as $item) {
            match ($this->bandOf($item['y'])) {
                $head => $heading[] = $item,
                $foot => $footer[] = $item,
                default => $body[] = $item,
            };
        }

        return array_values(array_filter([
            $heading,
            array_values(array_filter($body, fn (array $item) => $item['x'] < $gutter)),
            array_values(array_filter($body, fn (array $item) => $item['x'] >= $gutter)),
            $footer,
        ], fn (array $block) => $block !== []));
    }

    /**
     * Is the first of these lines set well clear of the rest of the page?
     *
     * @param  list<float>  $lines  every line's height, in order away from the edge being tested
     */
    private function standsApart(array $lines): bool
    {
        $gaps = [];

        for ($i = 1; $i < count($lines); $i++) {
            $gaps[] = abs($lines[$i] - $lines[$i - 1]);
        }

        sort($gaps);
        $usual = $gaps[intdiv(count($gaps), 2)];

        return $usual > 0 && abs($lines[1] - $lines[0]) > $usual * 1.6;
    }

    /**
     * The printed line a run at this height belongs to.
     */
    private function bandOf(float $y): float
    {
        return round($y / self::LINE_TOLERANCE) * self::LINE_TOLERANCE;
    }

    /**
     * Every piece of text on a page, with where it is drawn.
     *
     * @return list<array{x: float, y: float, text: string}>
     */
    private function itemsOf(Page $page): array
    {
        $items = [];

        foreach ($page->getDataTm() as [$tm, $text]) {
            if (trim($text) === '') {
                continue;
            }

            $items[] = ['x' => (float) $tm[4], 'y' => (float) $tm[5], 'text' => $text];
        }

        return $items;
    }

    /**
     * Where the second column starts, judged from every run in the document.
     *
     * One answer for the whole document rather than one per page: the layout
     * does not change between pages, and a page carrying a wide diagram would
     * otherwise be read as a single column and come out braided.
     *
     * @param  list<array{x: float, y: float, text: string}>  $items
     */
    private function gutter(array $items): ?float
    {
        // Too little text to tell a column layout from a coincidence.
        if (count($items) < 40) {
            return null;
        }

        $starts = array_column($items, 'x');
        sort($starts);

        // The 1st and 99th percentile, so one stray run in the margin does not
        // decide where the middle of the page is.
        $left = $starts[(int) (count($starts) * 0.01)];
        $right = $starts[(int) (count($starts) * 0.99)];
        $width = $right - $left;

        if ($width < 100) {
            return null;
        }

        $bin = 4.0;
        $counts = [];

        foreach ($starts as $x) {
            $index = (int) floor(($x - $left) / $bin);
            $counts[$index] = ($counts[$index] ?? 0) + 1;
        }

        $busy = $counts;
        sort($busy);
        $typical = $busy[intdiv(count($busy), 2)];
        $quiet = max(1.0, $typical * 0.1);

        // The widest quiet strip in the middle of the page is the gutter.
        $bestEnd = null;
        $bestLength = 0;
        $runFrom = null;
        $bins = (int) ceil($width / $bin);

        for ($index = 0; $index <= $bins; $index++) {
            if (($counts[$index] ?? 0) <= $quiet) {
                $runFrom ??= $index;

                continue;
            }

            if ($runFrom !== null) {
                $length = $index - $runFrom;
                $centre = $left + ($runFrom + $length / 2) * $bin;

                if ($length > $bestLength && $centre > $left + $width * 0.3 && $centre < $left + $width * 0.72) {
                    $bestLength = $length;
                    $bestEnd = $index;
                }

                $runFrom = null;
            }
        }

        if ($bestEnd === null || $bestLength * $bin < self::MINIMUM_GUTTER) {
            return null;
        }

        // The far side of the strip, not its middle: that is where the second
        // column starts. A line of the first column may reach into the strip,
        // and taking the middle would cut its last words off and file them
        // under the other column.
        $gutter = $left + $bestEnd * $bin;

        // Both sides have to carry real text. A margin note down one edge is
        // not a column.
        $onLeft = count(array_filter($starts, fn (float $x) => $x < $gutter));
        $onRight = count($starts) - $onLeft;

        if (min($onLeft, $onRight) < count($starts) * 0.15) {
            return null;
        }

        return $this->isCrossed($items, $gutter) ? null : $gutter;
    }

    /**
     * Does text run straight through this line?
     *
     * The test that tells a real gutter from a page that merely happens to
     * start few words near its middle. A single-column page that is wrongly
     * split loses the end of every long line to the wrong column, which reads
     * as questions with their options cut in half, so this is deliberately
     * quick to say no.
     *
     * @param  list<array{x: float, y: float, text: string}>  $items
     */
    private function isCrossed(array $items, float $gutter): bool
    {
        $bands = [];

        foreach ($items as $item) {
            $bands[(string) $this->bandOf($item['y'])][] = $item;
        }

        $character = $this->characterWidth($bands);
        $crossings = 0;

        foreach ($bands as $band) {
            usort($band, fn (array $a, array $b) => $a['x'] <=> $b['x']);

            foreach ($band as $i => $item) {
                if ($item['x'] >= $gutter) {
                    break;
                }

                $next = $band[$i + 1] ?? null;
                $drawn = mb_strlen(rtrim($item['text'])) * $character;
                $ends = $item['x'] + ($next !== null ? min($drawn, $next['x'] - $item['x']) : $drawn);

                if ($ends > $gutter + $character) {
                    $crossings++;
                }
            }
        }

        return $crossings > count($items) * 0.02;
    }

    /**
     * One column of a page, as printed lines from the top down.
     *
     * @param  list<array{x: float, y: float, text: string}>  $items
     * @return list<string>
     */
    private function linesOf(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $bands = [];

        foreach ($items as $item) {
            $bands[(string) $this->bandOf($item['y'])][] = $item;
        }

        // Top of the page first: PDF measures y upwards from the bottom.
        uksort($bands, fn ($a, $b) => (float) $b <=> (float) $a);

        $bands = $this->foldSuperscripts($bands);
        $character = $this->characterWidth($bands);
        $lines = [];

        foreach ($bands as $band) {
            usort($band, fn (array $a, array $b) => $a['x'] <=> $b['x']);
            $lines[] = $this->joinRuns($band, $character);
        }

        return array_values(array_filter($lines, fn (string $line) => trim($line) !== ''));
    }

    /**
     * Put raised text back on the line it belongs to.
     *
     * @param  array<string, list<array{x: float, y: float, text: string}>>  $bands
     * @return array<string, list<array{x: float, y: float, text: string}>>
     */
    private function foldSuperscripts(array $bands): array
    {
        $keys = array_keys($bands);

        foreach ($keys as $position => $key) {
            $band = $bands[$key] ?? null;

            if ($band === null || ! $this->looksRaised($band)) {
                continue;
            }

            // The line below it, which is the line it is raised above.
            $below = $keys[$position + 1] ?? null;

            if ($below === null || ! isset($bands[$below])) {
                continue;
            }

            $rise = (float) $key - (float) $below;

            if ($rise < self::SUPERSCRIPT_RISE[0] || $rise > self::SUPERSCRIPT_RISE[1]) {
                continue;
            }

            foreach ($band as $item) {
                $item['text'] = Scripts::up(trim($item['text']));
                $item['y'] = (float) $below;

                // x², not x ². Raised text sits against what it belongs to.
                $item['raised'] = true;
                $bands[$below][] = $item;
            }

            unset($bands[$key]);
        }

        return $bands;
    }

    /**
     * A line that is nothing but a couple of small characters: an exponent, a
     * degree sign, the 2 in CO2. Never a line of the paper in its own right.
     *
     * @param  list<array{x: float, y: float, text: string}>  $band
     */
    private function looksRaised(array $band): bool
    {
        if (count($band) > 6) {
            return false;
        }

        foreach ($band as $item) {
            if (! preg_match('/^[0-9+\-−=()nox]{1,3}$/iu', trim($item['text']))) {
                return false;
            }
        }

        return true;
    }

    /**
     * How wide one character is in this column, measured from the column.
     *
     * Taken low (the tightest tenth of what is seen) because the runs that sit
     * hard against each other are the ones with no space between them, and
     * that is the width wanted: anything wider than it is a real space.
     *
     * @param  array<string, list<array{x: float, y: float, text: string}>>  $bands
     */
    private function characterWidth(array $bands): float
    {
        $ratios = [];

        foreach ($bands as $band) {
            usort($band, fn (array $a, array $b) => $a['x'] <=> $b['x']);

            for ($i = 1; $i < count($band); $i++) {
                $length = mb_strlen(rtrim($band[$i - 1]['text']));
                $gap = $band[$i]['x'] - $band[$i - 1]['x'];

                if ($length >= 4 && $gap > 0 && $gap < 200) {
                    $ratios[] = $gap / $length;
                }
            }
        }

        if ($ratios === []) {
            return 4.5;
        }

        sort($ratios);

        return max(2.0, $ratios[(int) (count($ratios) * 0.05)]);
    }

    /**
     * The runs of one printed line, back into one string.
     *
     * A PDF stores a line as a series of runs placed by hand, and the space
     * between two words is often no character at all, just a gap. So a space
     * goes in wherever the next run starts further along than the last one can
     * possibly have reached, and does not where the two sit hard against each
     * other, which is a word the producer happened to split mid-way.
     *
     * @param  list<array{x: float, y: float, text: string}>  $band
     */
    private function joinRuns(array $band, float $character): string
    {
        $line = '';
        $previous = null;

        foreach ($band as $item) {
            if ($previous !== null && ($item['raised'] ?? false) === false) {
                $gap = $item['x'] - $previous['x'];
                $drawn = mb_strlen(rtrim($previous['text'])) * $character;
                $wantsSpace = ! preg_match('/\s$/u', $line) && ! preg_match('/^\s/u', $item['text']);

                // A word followed by a number is two things, however tightly a
                // heading sets them: "Mathematics1983" is a year lost.
                $runTogether = preg_match('/\p{L}{3,}$/u', rtrim($previous['text']))
                    && preg_match('/^\d/u', $item['text']);

                if ($wantsSpace && ($gap > $drawn || $runTogether)) {
                    $line .= ' ';
                }
            }

            $line .= $item['text'];
            $previous = $item;
        }

        return rtrim($line);
    }
}
