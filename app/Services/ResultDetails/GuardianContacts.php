<?php

namespace App\Services\ResultDetails;

use App\Enums\ResultDeliveryChannel;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\CredentialShareMessage;

/**
 * Where a student's parents/guardians can be reached, read from that
 * student's own records and nowhere else.
 *
 * Two places hold it, and both are used:
 *   - the parent/guardian fields on the student's record (guardian_name,
 *     guardian_email, guardian_phone), which every school fills in;
 *   - the parent accounts linked to that student (a parent with a portal
 *     login), which carry their own email and phone.
 *
 * STRICT MATCHING. Every address comes from THIS student: their own row, or a
 * guardian linked to them in guardian_student. Nothing is looked up by name,
 * by surname, or by "a parent with the same phone", so a message about one
 * child can never be routed to another child's parent. A parent with two
 * children is reached once per child, each message carrying only that
 * child's details.
 *
 * VALIDATED BEFORE SENDING. An email must be a well-formed address. A phone
 * number is turned into international digits the way the WhatsApp share
 * links already do (08031234567 becomes 2348031234567) and must then be a
 * plausible number: 10 to 15 digits, and 13 for a Nigerian one. Anything
 * else is reported as invalid, by name, rather than sent to and failed.
 */
class GuardianContacts
{
    public function __construct(private readonly CredentialShareMessage $phones) {}

    /**
     * @return array{
     *     email: list<array{address: string, name: string}>,
     *     whatsapp: list<array{address: string, name: string}>,
     *     invalid: list<string>
     * }
     */
    public function for(Student $student): array
    {
        $emails = [];
        $phones = [];
        $invalid = [];

        $people = [[
            'name' => filled($student->guardian_name) ? (string) $student->guardian_name : 'Parent/Guardian',
            'email' => $student->guardian_email,
            'phone' => $student->guardian_phone,
            'source' => 'the student record',
        ]];

        $guardians = $student->relationLoaded('guardians') ? $student->guardians : $student->guardians()->get();

        foreach ($guardians as $guardian) {
            /** @var Guardian $guardian */
            if ($guardian->school_id !== $student->school_id || ! $guardian->is_active) {
                continue;
            }

            $people[] = [
                'name' => (string) $guardian->name,
                'email' => $guardian->email,
                'phone' => $guardian->phone,
                'source' => "{$guardian->name}'s parent account",
            ];
        }

        foreach ($people as $person) {
            if (filled($person['email'])) {
                $email = mb_strtolower(trim((string) $person['email']));

                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $invalid[] = "Email \"{$person['email']}\" on {$person['source']} is not a valid address.";
                } else {
                    $emails[$email] ??= ['address' => $email, 'name' => $person['name']];
                }
            }

            if (filled($person['phone'])) {
                $number = $this->whatsAppNumber((string) $person['phone']);

                if ($number === null) {
                    $invalid[] = "Phone \"{$person['phone']}\" on {$person['source']} is not a valid WhatsApp number.";
                } else {
                    $phones[$number] ??= ['address' => $number, 'name' => $person['name']];
                }
            }
        }

        return [
            'email' => array_values($emails),
            'whatsapp' => array_values($phones),
            'invalid' => $invalid,
        ];
    }

    /**
     * The addresses for one channel.
     *
     * @return list<array{address: string, name: string}>
     */
    public function forChannel(Student $student, ResultDeliveryChannel $channel): array
    {
        return $this->for($student)[$channel->value];
    }

    /**
     * International digits for WhatsApp, or null if this cannot be a phone
     * number.
     */
    public function whatsAppNumber(string $phone): ?string
    {
        $digits = $this->phones->normalisePhone($phone);

        if ($digits === null || strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        // A Nigerian number is always 234 followed by ten digits.
        if (str_starts_with($digits, '234') && strlen($digits) !== 13) {
            return null;
        }

        return $digits;
    }
}
