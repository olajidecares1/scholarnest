<?php

namespace App\Services\DocumentExtraction;

use App\Services\Uploads\UploadStorage;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Reads a Word (.docx) file into text a question parser can follow, keeping
 * the layout that tells one question, option and heading from the next.
 *
 * WHY NOT PHPWORD'S TEXT. PHPWord returns the text of a paragraph but drops
 * what sits between its pieces. A JAMB compilation that puts each option on
 * its own line with a line break (Shift+Enter) came out as
 * "...the consciousness ofA. birdsB. armed robbersC. animalsD. spirit beings",
 * words welded together and every question's options fused into its text.
 * Heading paragraphs ("UTME 2010 LITERATURE-IN-ENGLISH") were dropped
 * altogether, so no year was ever detected. This reads the document XML
 * directly, so nothing between the words is lost.
 *
 * WHAT IT KEEPS:
 *
 *   - Every paragraph on its own line, and every line break within one.
 *   - Headings, marked "## " so the parser can tell a heading from prose.
 *   - Word's automatic numbering ("1.", "A.", "(a)"), which is not in the
 *     text at all: a paper typed with numbered lists would otherwise arrive
 *     with no question numbers and no option letters.
 *   - Superscript and subscript (x², H₂O), and equations built with Word's
 *     equation editor, written out in plain notation: (a)/(b), √(x), x^(n).
 *   - Tables, one row per line.
 *   - Pictures, as an [[image:N]] marker at the place they appear, so an
 *     image can be attached to the question it belongs to.
 */
class DocxReader
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const M = 'http://schemas.openxmlformats.org/officeDocument/2006/math';

    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private DOMXPath $xpath;

    private ZipArchive $zip;

    /** @var array<string, string> relationship id => part path */
    private array $relationships = [];

    /** @var array<string, array<int, array{format: string, text: string, start: int}>> numId => levels */
    private array $numbering = [];

    /** @var array<string, array<int, int>> numId => level => counter */
    private array $counters = [];

    /** @var array<string, string> style id => heading level marker */
    private array $headingStyles = [];

    /** @var list<string> public-disk paths, in document order */
    private array $images = [];

    private string $imageDirectory = '';

    /**
     * @return array{text: string, images: list<string>}
     */
    public function read(string $absolutePath): array
    {
        $this->zip = new ZipArchive;

        if ($this->zip->open($absolutePath) !== true) {
            throw new RuntimeException('The Word document could not be opened.');
        }

        try {
            $xml = $this->zip->getFromName('word/document.xml');

            if ($xml === false) {
                throw new RuntimeException('The Word document has no body.');
            }

            $document = new DOMDocument;
            $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);

            $this->xpath = new DOMXPath($document);
            $this->xpath->registerNamespace('w', self::W);
            $this->xpath->registerNamespace('m', self::M);
            $this->xpath->registerNamespace('r', self::R);

            $this->images = [];
            $this->counters = [];
            $this->imageDirectory = 'cbt-uploads/images/'.Str::uuid();
            $this->loadRelationships();
            $this->loadNumbering();
            $this->loadHeadingStyles();

            $body = $this->xpath->query('/w:document/w:body')->item(0);
            $lines = $body instanceof DOMElement ? $this->blocks($body) : [];

            $text = implode("\n", $lines);
            $text = preg_replace("/[ \t]+\n/u", "\n", $text) ?? $text;

            return ['text' => trim($text), 'images' => $this->images];
        } finally {
            $this->zip->close();
        }
    }

    /**
     * The block-level content of a container, one entry per line.
     *
     * @return list<string>
     */
    private function blocks(DOMElement $container): array
    {
        $lines = [];

        foreach ($container->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            match ($child->localName) {
                'p' => array_push($lines, ...$this->paragraph($child)),
                'tbl' => array_push($lines, ...$this->table($child)),

                // Content controls and tracked insertions wrap ordinary blocks.
                'sdt' => array_push($lines, ...$this->blocks($this->firstChild($child, 'sdtContent') ?? $child)),
                'customXml', 'ins' => array_push($lines, ...$this->blocks($child)),
                default => null,
            };
        }

        return $lines;
    }

    /**
     * One paragraph: its automatic number, its text, and a heading marker.
     *
     * @return list<string>
     */
    private function paragraph(DOMElement $paragraph): array
    {
        $text = $this->listPrefix($paragraph).$this->inline($paragraph);

        if (trim($text) === '') {
            // A picture on a line of its own still has to be kept.
            return [];
        }

        $style = $this->xpath->query('./w:pPr/w:pStyle/@w:val', $paragraph)->item(0)?->nodeValue;

        if ($style !== null && isset($this->headingStyles[$style]) && ! str_contains($text, "\n")) {
            return ['## '.trim($text)];
        }

        return explode("\n", $text);
    }

    /**
     * A table, one row per line, cells separated by wide spacing so a row of
     * options ("A. one | B. two") still reads as separate options.
     *
     * @return list<string>
     */
    private function table(DOMElement $table): array
    {
        $lines = [];

        foreach ($this->xpath->query('./w:tr', $table) as $row) {
            $cells = [];

            foreach ($this->xpath->query('./w:tc', $row) as $cell) {
                $content = trim(implode(' ', $this->blocks($cell)));

                if ($content !== '') {
                    $cells[] = $content;
                }
            }

            if ($cells !== []) {
                $lines[] = implode('    ', $cells);
            }
        }

        return $lines;
    }

    /**
     * The inline text of a paragraph or run container.
     */
    private function inline(DOMElement $container): string
    {
        $text = '';

        foreach ($container->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if ($node->namespaceURI === self::M) {
                $text .= $node->localName === 'oMathPara' || $node->localName === 'oMath'
                    ? $this->math($node)
                    : '';

                continue;
            }

            $text .= match ($node->localName) {
                'r' => $this->run($node),
                'hyperlink', 'smartTag', 'fldSimple', 'ins', 'customXml', 'sdtContent', 'dir', 'bdo' => $this->inline($node),
                'sdt' => $this->inline($this->firstChild($node, 'sdtContent') ?? $node),

                // Tracked deletions are not part of the document as it reads.
                'del', 'moveFrom' => '',
                'moveTo' => $this->inline($node),
                'AlternateContent' => $this->alternateContent($node),
                default => '',
            };
        }

        return $text;
    }

    /**
     * One run of text with its formatting.
     */
    private function run(DOMElement $run): string
    {
        $vertical = $this->xpath->query('./w:rPr/w:vertAlign/@w:val', $run)->item(0)?->nodeValue;
        $text = '';

        foreach ($run->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $text .= match ($node->localName) {
                't' => $node->textContent,
                'tab', 'ptab' => ' ',
                'br', 'cr' => "\n",
                'noBreakHyphen' => '-',
                'softHyphen' => '',
                'sym' => $this->symbol($node),
                'drawing', 'pict', 'object' => $this->picture($node),
                'AlternateContent' => $this->alternateContent($node),
                default => '',
            };
        }

        return match ($vertical) {
            'superscript' => Scripts::up($text),
            'subscript' => Scripts::down($text),
            default => $text,
        };
    }

    /**
     * Word writes a picture twice, a modern version and a fallback. Only one
     * is read, or the image would be attached twice.
     */
    private function alternateContent(DOMElement $node): string
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'Choice') {
                return $this->inline($child).$this->picture($child);
            }
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'Fallback') {
                return $this->inline($child).$this->picture($child);
            }
        }

        return '';
    }

    /**
     * A character inserted from a symbol font.
     */
    private function symbol(DOMElement $symbol): string
    {
        $code = $symbol->getAttributeNS(self::W, 'char');

        if ($code === '') {
            return '';
        }

        $value = hexdec($code);

        // Symbol-font characters live in the private area F000-F0FF; the
        // printable ones map onto the Greek and maths symbols they show.
        if ($value >= 0xF000) {
            $value -= 0xF000;
        }

        return self::SYMBOL_FONT[$value] ?? mb_chr($value);
    }

    /**
     * Where a picture sits, as a marker the importer turns into an image.
     */
    private function picture(DOMElement $node): string
    {
        $markers = '';

        foreach ($this->xpath->query('.//*[local-name()="blip"]/@r:embed | .//*[local-name()="imagedata"]/@r:id', $node) as $attribute) {
            $path = $this->storeImage((string) $attribute->nodeValue);

            if ($path !== null) {
                $markers .= "\n[[image:".(count($this->images) - 1)."]]\n";
            }
        }

        return $markers;
    }

    private function storeImage(string $relationshipId): ?string
    {
        $target = $this->relationships[$relationshipId] ?? null;

        if ($target === null) {
            return null;
        }

        $extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return null;
        }

        $contents = $this->zip->getFromName($target);

        if ($contents === false) {
            return null;
        }

        $path = $this->imageDirectory.'/'.Str::uuid().'.'.$extension;
        app(UploadStorage::class)->putContents('public', $path, $contents);
        $this->images[] = $path;

        return $path;
    }

    /**
     * An equation from Word's equation editor, as plain notation.
     */
    private function math(DOMElement $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->namespaceURI !== self::M) {
                $text .= $child->localName === 'r' ? $this->run($child) : '';

                continue;
            }

            $text .= match ($child->localName) {
                'r' => $this->mathText($child),
                'f' => '('.$this->mathPart($child, 'num').')/('.$this->mathPart($child, 'den').')',
                'sSup' => $this->mathPart($child, 'e').Scripts::up($this->mathPart($child, 'sup')),
                'sSub' => $this->mathPart($child, 'e').Scripts::down($this->mathPart($child, 'sub')),
                'sSubSup' => $this->mathPart($child, 'e')
                    .Scripts::down($this->mathPart($child, 'sub'))
                    .Scripts::up($this->mathPart($child, 'sup')),
                'rad' => $this->radical($child),
                'd' => $this->delimited($child),
                'nary' => $this->nary($child),
                'func' => $this->mathPart($child, 'fName').' '.$this->mathPart($child, 'e'),
                'bar', 'acc', 'box', 'borderBox', 'groupChr', 'limLow', 'limUpp', 'eqArr', 'm', 'mr', 'e', 'oMath', 'sPre' => $this->math($child),
                default => $this->math($child),
            };
        }

        return $text;
    }

    private function mathText(DOMElement $run): string
    {
        $text = '';

        foreach ($this->xpath->query('./m:t', $run) as $t) {
            $text .= $t->textContent;
        }

        return $text;
    }

    private function mathPart(DOMElement $node, string $name): string
    {
        $part = $this->firstChild($node, $name, self::M);

        return $part ? trim($this->math($part)) : '';
    }

    private function radical(DOMElement $node): string
    {
        $degree = $this->mathPart($node, 'deg');

        return ($degree !== '' ? Scripts::up($degree) : '').'√('.$this->mathPart($node, 'e').')';
    }

    private function delimited(DOMElement $node): string
    {
        $begin = $this->xpath->query('./m:dPr/m:begChr/@m:val', $node)->item(0)?->nodeValue ?? '(';
        $end = $this->xpath->query('./m:dPr/m:endChr/@m:val', $node)->item(0)?->nodeValue ?? ')';
        $parts = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'e') {
                $parts[] = trim($this->math($child));
            }
        }

        return $begin.implode(', ', $parts).$end;
    }

    private function nary(DOMElement $node): string
    {
        $symbol = $this->xpath->query('./m:naryPr/m:chr/@m:val', $node)->item(0)?->nodeValue ?? '∫';
        $lower = $this->mathPart($node, 'sub');
        $upper = $this->mathPart($node, 'sup');

        return $symbol
            .($lower !== '' ? '_('.$lower.')' : '')
            .($upper !== '' ? '^('.$upper.')' : '')
            .' '.$this->mathPart($node, 'e');
    }

    /**
     * The number or letter Word draws in front of a list paragraph.
     */
    private function listPrefix(DOMElement $paragraph): string
    {
        $numId = $this->xpath->query('./w:pPr/w:numPr/w:numId/@w:val', $paragraph)->item(0)?->nodeValue;
        $level = (int) ($this->xpath->query('./w:pPr/w:numPr/w:ilvl/@w:val', $paragraph)->item(0)?->nodeValue ?? 0);

        if ($numId === null || $numId === '0' || ! isset($this->numbering[$numId][$level])) {
            return '';
        }

        $definition = $this->numbering[$numId][$level];

        if ($definition['format'] === 'bullet' || $definition['format'] === 'none') {
            return '';
        }

        // Deeper levels restart whenever a shallower one moves on.
        $this->counters[$numId][$level] = ($this->counters[$numId][$level] ?? ($definition['start'] - 1)) + 1;

        foreach (array_keys($this->counters[$numId]) as $deeper) {
            if ($deeper > $level) {
                unset($this->counters[$numId][$deeper]);
            }
        }

        $prefix = preg_replace_callback('/%(\d)/', function (array $match) use ($numId) {
            $lvl = (int) $match[1] - 1;
            $definition = $this->numbering[$numId][$lvl] ?? ['format' => 'decimal', 'start' => 1];
            $value = $this->counters[$numId][$lvl] ?? $definition['start'];

            return $this->formatNumber($value, $definition['format']);
        }, $definition['text']) ?? '';

        return trim($prefix) === '' ? '' : trim($prefix).' ';
    }

    private function formatNumber(int $value, string $format): string
    {
        return match ($format) {
            'upperLetter' => $this->letters($value),
            'lowerLetter' => strtolower($this->letters($value)),
            'upperRoman' => $this->roman($value),
            'lowerRoman' => strtolower($this->roman($value)),
            default => (string) $value,
        };
    }

    private function letters(int $value): string
    {
        $value = max(1, $value);
        $letter = chr(ord('A') + (($value - 1) % 26));

        return str_repeat($letter, intdiv($value - 1, 26) + 1);
    }

    private function roman(int $value): string
    {
        $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $result = '';

        foreach ($map as $numeral => $amount) {
            while ($value >= $amount) {
                $result .= $numeral;
                $value -= $amount;
            }
        }

        return $result;
    }

    private function loadRelationships(): void
    {
        $this->relationships = [];
        $xml = $this->zip->getFromName('word/_rels/document.xml.rels');

        if ($xml === false) {
            return;
        }

        $rels = new DOMDocument;
        $rels->loadXML($xml, LIBXML_NONET);

        foreach ($rels->getElementsByTagName('Relationship') as $relationship) {
            $target = $relationship->getAttribute('Target');

            if ($relationship->getAttribute('TargetMode') === 'External') {
                continue;
            }

            $this->relationships[$relationship->getAttribute('Id')] = str_starts_with($target, '/')
                ? ltrim($target, '/')
                : 'word/'.$target;
        }
    }

    private function loadNumbering(): void
    {
        $this->numbering = [];
        $xml = $this->zip->getFromName('word/numbering.xml');

        if ($xml === false) {
            return;
        }

        $numbering = new DOMDocument;
        $numbering->loadXML($xml, LIBXML_NONET);
        $xpath = new DOMXPath($numbering);
        $xpath->registerNamespace('w', self::W);

        $abstract = [];

        foreach ($xpath->query('/w:numbering/w:abstractNum') as $node) {
            $levels = [];

            foreach ($xpath->query('./w:lvl', $node) as $lvl) {
                $levels[(int) $lvl->getAttributeNS(self::W, 'ilvl')] = [
                    'format' => $xpath->query('./w:numFmt/@w:val', $lvl)->item(0)?->nodeValue ?? 'decimal',
                    'text' => $xpath->query('./w:lvlText/@w:val', $lvl)->item(0)?->nodeValue ?? '',
                    'start' => (int) ($xpath->query('./w:start/@w:val', $lvl)->item(0)?->nodeValue ?? 1),
                ];
            }

            $abstract[$node->getAttributeNS(self::W, 'abstractNumId')] = $levels;
        }

        foreach ($xpath->query('/w:numbering/w:num') as $num) {
            $abstractId = $xpath->query('./w:abstractNumId/@w:val', $num)->item(0)?->nodeValue;
            $levels = $abstract[$abstractId] ?? [];

            foreach ($xpath->query('./w:lvlOverride', $num) as $override) {
                $level = (int) $override->getAttributeNS(self::W, 'ilvl');
                $start = $xpath->query('./w:startOverride/@w:val', $override)->item(0)?->nodeValue;

                if ($start !== null && isset($levels[$level])) {
                    $levels[$level]['start'] = (int) $start;
                }
            }

            $this->numbering[$num->getAttributeNS(self::W, 'numId')] = $levels;
        }
    }

    private function loadHeadingStyles(): void
    {
        $this->headingStyles = [];
        $xml = $this->zip->getFromName('word/styles.xml');

        if ($xml === false) {
            return;
        }

        $styles = new DOMDocument;
        $styles->loadXML($xml, LIBXML_NONET);
        $xpath = new DOMXPath($styles);
        $xpath->registerNamespace('w', self::W);

        foreach ($xpath->query('/w:styles/w:style[@w:type="paragraph"]') as $style) {
            $id = $style->getAttributeNS(self::W, 'styleId');
            $name = strtolower((string) $xpath->query('./w:name/@w:val', $style)->item(0)?->nodeValue);
            $outline = $xpath->query('./w:pPr/w:outlineLvl/@w:val', $style)->item(0)?->nodeValue;

            if (str_starts_with($name, 'heading') || $name === 'title' || $outline !== null) {
                $this->headingStyles[$id] = $name;
            }
        }
    }

    private function firstChild(DOMNode $node, string $localName, string $namespace = self::W): ?DOMElement
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName && $child->namespaceURI === $namespace) {
                return $child;
            }
        }

        return null;
    }

    /**
     * The Symbol font's printable characters, by position.
     *
     * @var array<int, string>
     */
    private const SYMBOL_FONT = [
        0x22 => '∀', 0x24 => '∃', 0x27 => '∋', 0x2A => '∗', 0x2D => '−', 0x40 => '≅',
        0x41 => 'Α', 0x42 => 'Β', 0x43 => 'Χ', 0x44 => 'Δ', 0x45 => 'Ε', 0x46 => 'Φ', 0x47 => 'Γ', 0x48 => 'Η',
        0x49 => 'Ι', 0x4B => 'Κ', 0x4C => 'Λ', 0x4D => 'Μ', 0x4E => 'Ν', 0x4F => 'Ο', 0x50 => 'Π', 0x51 => 'Θ',
        0x52 => 'Ρ', 0x53 => 'Σ', 0x54 => 'Τ', 0x55 => 'Υ', 0x57 => 'Ω', 0x58 => 'Ξ', 0x59 => 'Ψ', 0x5A => 'Ζ',
        0x61 => 'α', 0x62 => 'β', 0x63 => 'χ', 0x64 => 'δ', 0x65 => 'ε', 0x66 => 'φ', 0x67 => 'γ', 0x68 => 'η',
        0x69 => 'ι', 0x6B => 'κ', 0x6C => 'λ', 0x6D => 'μ', 0x6E => 'ν', 0x6F => 'ο', 0x70 => 'π', 0x71 => 'θ',
        0x72 => 'ρ', 0x73 => 'σ', 0x74 => 'τ', 0x75 => 'υ', 0x77 => 'ω', 0x78 => 'ξ', 0x79 => 'ψ', 0x7A => 'ζ',
        0xA3 => '≤', 0xA5 => '∞', 0xB0 => '°', 0xB1 => '±', 0xB3 => '≥', 0xB4 => '×', 0xB8 => '÷', 0xB9 => '≠',
        0xBA => '≡', 0xBB => '≈', 0xAE => '→', 0xAC => '←', 0xD6 => '√', 0xE5 => '∑', 0xF2 => '∫',
    ];
}
