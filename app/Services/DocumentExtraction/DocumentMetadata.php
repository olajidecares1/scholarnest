<?php

namespace App\Services\DocumentExtraction;

/**
 * What a question paper says about itself.
 *
 * Papers carry a heading before the first question - subject, class, session,
 * term, a title - and reading it saves the person uploading from retyping what
 * is already written on the page they are holding.
 *
 * Every field is nullable and every field is a SUGGESTION. Nothing here is
 * trusted enough to publish on: the upload screen shows what was found, and a
 * person confirms or corrects it before anything is created. A heading read
 * wrongly and applied silently would file a whole paper under the wrong class,
 * which is worse than not reading it at all.
 */
final class DocumentMetadata
{
    public function __construct(
        public readonly ?string $subject = null,
        public readonly ?string $className = null,
        public readonly ?string $session = null,
        public readonly ?string $term = null,
        public readonly ?string $title = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->subject === null
            && $this->className === null
            && $this->session === null
            && $this->term === null
            && $this->title === null;
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'class_name' => $this->className,
            'session' => $this->session,
            'term' => $this->term,
            'title' => $this->title,
        ];
    }

    /**
     * The fields that were actually found, for telling somebody what was read.
     *
     * @return array<string, string>
     */
    public function found(): array
    {
        return array_filter($this->toArray(), fn (?string $value) => $value !== null);
    }
}
