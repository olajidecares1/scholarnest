<?php

namespace App\Services\DocumentExtraction;

/**
 * What came out of an uploaded file, before anyone tries to read questions
 * out of it.
 *
 * Kept separate from the parsing because the two fail for entirely different
 * reasons and deserve different messages: a scanned PDF yields no text at all
 * and nothing can be done with it, whereas a perfectly readable document may
 * simply not be laid out as questions. Telling a teacher "no questions found"
 * when the real problem is that they photographed a page sends them looking in
 * the wrong place.
 */
final class ExtractedDocument
{
    /**
     * @param  string  $text  Plain text, one logical line per paragraph.
     * @param  list<string>  $images  Disk paths of any embedded images.
     * @param  list<string>  $warnings  Things worth telling a person, none of
     *                                  which stop the extraction.
     * @param  bool  $looksScanned  True when the file is a PDF that yielded no
     *                              usable text, almost always a scan.
     * @param  list<string>  $alternatives  Other readings of the same file,
     *                                      for a reader that cannot be sure
     *                                      which one a document suits. The
     *                                      one that yields the better
     *                                      questions is the one used.
     */
    public function __construct(
        public readonly string $text,
        public readonly array $images = [],
        public readonly array $warnings = [],
        public readonly bool $looksScanned = false,
        public readonly array $alternatives = [],
    ) {}

    public function hasText(): bool
    {
        return trim($this->text) !== '';
    }

    public function withWarning(string $warning): self
    {
        return new self($this->text, $this->images, [...$this->warnings, $warning], $this->looksScanned, $this->alternatives);
    }

    /**
     * Every reading of this file worth trying, best guess first.
     *
     * @return list<string>
     */
    public function readings(): array
    {
        return array_values(array_unique(array_filter(
            [$this->text, ...$this->alternatives],
            fn (string $text) => trim($text) !== '',
        )));
    }
}
