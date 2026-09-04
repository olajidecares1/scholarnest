<?php

namespace App\Support;

use App\Models\School;

/**
 * A school's address, telephone and email - wherever they happen to be kept.
 *
 * These now belong to the school itself, so every plan can set them. They used
 * to live only on the website record, which is a Standard and Exclusive
 * feature, and the two places an address matters most - the back of an ID card
 * and the letterhead on a result sheet - were the two a Basic school could not
 * fill in.
 *
 * The school's own value wins. The website is consulted only where the school
 * has left a field empty, so a Standard school that has filled in its website
 * and never opened Settings still gets a letterhead, and one that fills in
 * Settings can override what the public site says.
 */
final class SchoolContact
{
    public function __construct(
        public readonly ?string $address,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $website,
    ) {}

    public static function for(School $school): self
    {
        $site = $school->website;

        return new self(
            address: self::firstFilled($school->contact_address, $site?->contact_address),
            phone: self::firstFilled($school->contact_phone, $site?->contact_phone),
            email: self::firstFilled($school->contact_email, $site?->contact_email),

            // Not stored: a school's public address is derived from its domain
            // or slug, so there is nothing for anyone to type or mistype.
            website: $school->resolvedPublicHost(),
        );
    }

    /**
     * Whether there is anything at all to print.
     *
     * Lets a card or a letterhead leave the whole block out rather than draw a
     * heading with nothing beneath it.
     */
    public function isEmpty(): bool
    {
        return blank($this->address) && blank($this->phone) && blank($this->email) && blank($this->website);
    }

    private static function firstFilled(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }
}
