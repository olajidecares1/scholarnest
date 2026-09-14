<?php

use App\Enums\IdCardHolderType;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\IdCardTemplate;
use App\Models\IssuedIdCard;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolWebsite;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CodeImageGenerator;
use App\Support\IdCardDesign;
use App\Support\IdCardFieldIcon;
use App\Support\IdCardFields;

/**
 * The card is drawn to match a supplied reference design. These tests pin the
 * parts of it that were previously approximated, the front's proportions and
 * furniture, and the back's shape, so that "it stopped looking like the
 * template" is a failing test rather than something noticed months later.
 *
 * They assert structure, not pixels. Nothing here can tell you the card is
 * beautiful; they tell you the photograph is still the largest thing on it,
 * the footer is still a flat bar, and the red rule is still there.
 */
beforeEach(function () {
    $this->school = School::factory()->create([
        'name' => 'Marvel Int\' School',
        'current_session' => '2024/2025',

        // The school's own address, which every plan can now set. The back of
        // the card prints it under "If found, please return to".
        'contact_address' => '12 Excellence Avenue, GRA, Enugu, Enugu State, Nigeria.',
        'contact_phone' => '+234 812 345 6789',
        'contact_email' => 'info@marvelintschool.edu.ng',
    ]);

    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create([
        'school_id' => $this->school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Chinedu',
        'last_name' => 'Okafor',
        'admission_number' => 'MIS/2020/01235',
        'class_name' => 'Primary 6',
        'house' => 'Blue House',
    ]);

    $this->card = IssuedIdCard::factory()->create([
        'school_id' => $this->school->id,
        'holder_type' => IdCardHolderType::Student,
        'holder_uuid' => $this->student->uuid,
        'card_number' => 'MIS-STU-000001',
        'issued_by' => $this->admin->id,
    ]);
});

function renderCardFace(string $partial, IssuedIdCard $card): string
{
    return view("school-admin.id-cards.{$partial}", ['card' => $card])->render();
}

/**
 * The rendered card with its inline SVG icons decoded.
 *
 * The PDF back builds its contact icons as base64 data URIs, because dompdf
 * will not colour an inline <svg>. A colour used only inside one of those is
 * genuinely on the card but is not a literal string in the markup, so a test
 * looking for the hex would say it was missing when it is right there.
 */
function withDecodedSvgs(string $html): string
{
    return preg_replace_callback(
        '/data:image\/svg\+xml;base64,([A-Za-z0-9+\/=]+)/',
        fn (array $m) => (string) base64_decode($m[1], true),
        $html,
    ) ?? $html;
}

// ---------------------------------------------------------------------------
// Front
// ---------------------------------------------------------------------------

test('the front footer is a flat bar, not a wave', function () {
    // The reference footer is a plain navy rectangle. This used to draw an
    // elliptical curve, border-radius: 50% 13px, which is the single most
    // visible difference between the two designs.
    foreach (['_card', 'pdf._front'] as $partial) {
        $html = renderCardFace($partial, $this->card);

        expect($html)->not->toContain('border-top-left-radius: 50%')
            ->and($html)->not->toContain('border-top-right-radius: 50%');
    }
});

test('the front carries the red stripe under the masthead', function () {
    foreach (['_card', 'pdf._front'] as $partial) {
        expect(renderCardFace($partial, $this->card))->toContain(IdCardDesign::ACCENT);
    }
});

test('the photograph is framed, and a third of the card wide', function () {
    // A card is 204px wide. Measured off the artwork the photograph came out
    // at 84 of them, but on a rendered card that read as too heavy against
    // the details below it, so it was brought down by eye to 68.
    foreach (['_card', 'pdf._front'] as $partial) {
        $html = renderCardFace($partial, $this->card);

        expect($html)->toContain('68px')
            ->and($html)->toContain('82px')

            // A frame around the picture, not a border drawn on it: rounded
            // corners with a white gap between the navy edge and the photo.
            ->and($html)->toContain('border-radius: 8px');
    }
});

test('the front is built from card furniture, not a pre-rendered header image', function () {
    // The lanyard slot and header shapes were a raster PNG generated at a
    // fixed size and colour. Drawn now, so they stay sharp in print and follow
    // the school's own colour.
    expect(class_exists(CodeImageGenerator::class))->toBeTrue()
        ->and(method_exists(CodeImageGenerator::class, 'idCardHeaderDataUri'))->toBeFalse()
        ->and(method_exists(CodeImageGenerator::class, 'curvedBandDataUri'))->toBeFalse();
});

test('the front still shows the holder details from the database', function () {
    // The design changed; the data must not. Every one of these is read from
    // the pupil's own record or the school's.
    foreach (['_card', 'pdf._front'] as $partial) {
        $html = renderCardFace($partial, $this->card);

        expect($html)->toContain('Chinedu Okafor')
            ->and($html)->toContain('MIS/2020/01235')
            ->and($html)->toContain('Primary 6')
            ->and($html)->toContain('Blue House')
            ->and($html)->toContain('2024/2025')
            ->and($html)->toContain('Marvel Int&#039; School')

            // The badge reads STUDENT on the card, but the capitals come from
            // text-transform, the markup holds the label as written. The
            // screen card gets that from a utility class and the PDF from an
            // inline style, so either spelling satisfies this.
            ->and($html)->toContain('Student')
            ->and($html)->toMatch('/text-transform: uppercase|class="[^"]*\buppercase\b/');
    }
});

// ---------------------------------------------------------------------------
// Back
// ---------------------------------------------------------------------------

test('the back has straight edges, and closes on the signature bar', function () {
    // This used to assert that nothing followed the signature, read off a
    // small image of the whole card. A closer view showed the signature sits
    // ON a navy bar that closes the card, so the old premise was wrong, not
    // just the styling. What holds in both readings is that no edge curves.
    foreach (['_card_back', 'pdf._back'] as $partial) {
        $html = renderCardFace($partial, $this->card);

        expect($html)->not->toContain('border-bottom-left-radius: 50%')
            ->and($html)->not->toContain('border-bottom-right-radius: 50%')
            ->and($html)->not->toContain('border-top-left-radius: 50%')

            // And the bar is there, with the word under the signature.
            ->and($html)->toContain('Principal');
    }
});

test('the back carries the instructions and the return-to block', function () {
    foreach (['_card_back', 'pdf._back'] as $partial) {
        $html = renderCardFace($partial, $this->card);

        expect($html)->toContain('Instructions')
            ->and($html)->toContain('If found, please return to:')
            ->and($html)->toContain('Principal')

            // The school's own name, not a placeholder.
            ->and($html)->toContain('Marvel Int&#039; School');
    }
});

test('a school can write its own instructions and they are what appears', function () {
    $this->card->template()->associate(
        IdCardTemplate::factory()->create([
            'school_id' => $this->school->id,
            'instructions' => "Carry this card daily.\nDo not lend it to anyone.",
        ])
    )->save();

    foreach (['_card_back', 'pdf._back'] as $partial) {
        $html = renderCardFace($partial, $this->card->fresh());

        expect($html)->toContain('Carry this card daily.')
            ->and($html)->toContain('Do not lend it to anyone.')
            ->and($html)->not->toContain('must be worn at all times');
    }
});

// ---------------------------------------------------------------------------
// The reference card is the default every school edits
// ---------------------------------------------------------------------------

test('a school that has never opened the editor gets the reference card', function () {
    // No template at all. The colours it falls back to are the design's own,
    // from one place rather than five copies.
    expect($this->card->template)->toBeNull();

    foreach (['_card', 'pdf._front'] as $partial) {
        $html = renderCardFace($partial, $this->card);

        expect($html)->toContain(IdCardDesign::SECONDARY)
            ->and($html)->toContain(IdCardDesign::ACCENT);
    }
});

test('a school can change all three colours, the accent included', function () {
    // The accent was hardcoded, on the reasoning that it belonged to the
    // design rather than to a school. It does not: a school whose colours are
    // green and gold should not be stuck with a red stripe.
    // The back uses the accent only on its contact icons, which need contact
    // details to render at all, so the school gets some, as a real one would.
    SchoolWebsite::factory()->create([
        'school_id' => $this->school->id,
        'contact_phone' => '+234 812 345 6789',
        'contact_email' => 'info@marvel.example',
    ]);

    $this->card->template()->associate(
        IdCardTemplate::factory()->create([
            'school_id' => $this->school->id,
            'primary_color' => '#00695c',
            'secondary_color' => '#004d40',
            'accent_color' => '#f9a825',
        ])
    )->save();

    foreach (['_card', 'pdf._front', '_card_back', 'pdf._back'] as $partial) {
        $html = withDecodedSvgs(renderCardFace($partial, $this->card->fresh()));

        expect($html)->toContain('#004d40')
            ->and($html)->toContain('#f9a825')
            ->and($html)->not->toContain(IdCardDesign::ACCENT);
    }
});

test('a template saved before the accent existed still shows the reference red', function () {
    // Nullable column: every template that already exists has no accent, and
    // must keep the card its school is looking at today.
    $this->card->template()->associate(
        IdCardTemplate::factory()->create([
            'school_id' => $this->school->id,
            'accent_color' => null,
        ])
    )->save();

    expect(renderCardFace('_card', $this->card->fresh()))->toContain(IdCardDesign::ACCENT);
});

test('the editor opens a new template on the default card, not on a blank form', function () {
    $defaults = IdCardDesign::newTemplateDefaults($this->school);

    expect($defaults['primary_color'])->toBe(IdCardDesign::PRIMARY)
        ->and($defaults['secondary_color'])->toBe(IdCardDesign::SECONDARY)
        ->and($defaults['accent_color'])->toBe(IdCardDesign::ACCENT)

        // Wording a school can accept as it stands, carrying its own name,
        // not a placeholder it has to replace before the card makes sense.
        ->and($defaults['instructions'])->toContain('Marvel Int\' School')
        ->and($defaults['instructions'])->toContain('must be worn at all times');

    $this->actingAs($this->admin)
        ->get(route('id-cards.templates.index'))
        ->assertOk()
        ->assertSee('Accent Colour')
        ->assertSee(IdCardDesign::ACCENT);
});

test('the defaults are defined once, not copied into each card face', function () {
    // Five copies is five chances for a school that never touched the editor
    // and a school that saved without changing anything to end up looking at
    // two different cards.
    foreach ([
        'views/school-admin/id-cards/_card.blade.php',
        'views/school-admin/id-cards/_card_back.blade.php',
        'views/school-admin/id-cards/pdf/_front.blade.php',
        'views/school-admin/id-cards/pdf/_back.blade.php',
        'views/school-admin/id-cards/templates.blade.php',
    ] as $view) {
        $markup = file_get_contents(resource_path($view));

        expect($markup)->toContain('IdCardDesign')
            ->and($markup)->not->toContain("'#1d4ed8'")
            ->and($markup)->not->toContain("'#111a35'")
            ->and($markup)->not->toContain("'#c8102e'");
    }
});

// ---------------------------------------------------------------------------
// Segment 3, the details block, barcode and footer
// ---------------------------------------------------------------------------

test('each detail row carries its own icon in a navy tile', function () {
    foreach (['_card', 'pdf._front'] as $partial) {
        $html = withDecodedSvgs(renderCardFace($partial, $this->card));

        // The tile, and a distinct mark inside it per row. Decoded, because
        // the icons are SVG data URIs, dompdf cannot use an icon font, and a
        // card that showed icons on screen and none in print would be worse
        // than one with no icons at all.
        expect($html)->toContain('width: 10px; height: 10px; border-radius: 2px')
            ->and($html)->toContain('<svg xmlns');
    }
});

test('the icons are the right ones for the rows they sit beside', function () {
    // Lifted from the bundled Font Awesome rather than traced by hand, and
    // keyed by the label so the two card faces cannot choose differently.
    expect(IdCardFieldIcon::dataUri('Admission No.'))
        ->not->toBe(IdCardFieldIcon::dataUri('Class'));

    foreach (['Admission No.', 'Staff ID', 'Class', 'Department', 'D.O.B', 'House', 'Session', 'Blood Group', 'Expires'] as $label) {
        expect(IdCardFieldIcon::dataUri($label))->not->toBeNull();
    }

    // A row nobody has given an icon gets an empty tile, not a broken image.
    expect(IdCardFieldIcon::dataUri('Something Else'))->toBeNull();
});

test('both card faces print exactly the same rows', function () {
    // The row list was written out three times over, each with its own copy of
    // the conditions. The preview a School Admin approves and the card that
    // comes out of the printer have to be the same card.
    $rows = IdCardFields::rows($this->card);

    expect(collect($rows)->pluck('label')->all())
        ->toBe(['Admission No.', 'Class', 'D.O.B', 'House', 'Session']);

    foreach (['_card', 'pdf._front'] as $partial) {
        $html = renderCardFace($partial, $this->card);

        foreach ($rows as $row) {
            expect($html)->toContain($row['label'])
                ->and($html)->toContain($row['value']);
        }
    }
});

test('a row with nothing to say is left off rather than printed empty', function () {
    // This pupil has no house and the school has recorded no session.
    $bare = Student::factory()->create([
        'school_id' => $this->school->id,
        'house' => null,
        'date_of_birth' => null,
    ]);

    $this->school->update(['current_session' => null]);

    $card = IssuedIdCard::factory()->create([
        'school_id' => $this->school->id,
        'holder_type' => IdCardHolderType::Student,
        'holder_uuid' => $bare->uuid,
        'issued_by' => $this->admin->id,
    ]);

    expect(collect(IdCardFields::rows($card->fresh()))->pluck('label')->all())
        ->toBe(['Admission No.', 'Class']);
});

test('the barcode is unframed and a red hairline sits above the footer', function () {
    foreach (['_card', 'pdf._front'] as $partial) {
        $html = renderCardFace($partial, $this->card);

        // The framed strip was mine, not the reference's.
        expect($html)->not->toContain('border-radius: 2px; padding: 1.5px 3px')

            // A 2px red rule immediately above the navy bar.
            ->and($html)->toContain('height: 2px; background-color: '.IdCardDesign::ACCENT);
    }
});

// ---------------------------------------------------------------------------
// The specimen a School Admin sees before creating a template
// ---------------------------------------------------------------------------

test('the specimen is the real card, not a second drawing of it', function () {
    $response = $this->actingAs($this->admin)
        ->getJson(route('id-cards.templates.sample'))
        ->assertOk();

    $front = $response->json('front');

    // The same furniture the printed card has. The preview used to be a
    // hand-drawn miniature, a gradient header, an "Authorized Signature"
    // line, that had drifted away from the card entirely.
    expect($front)->toContain('68px')
        ->and($front)->toContain(IdCardDesign::ACCENT)
        ->and($front)->toContain('Admission No.')
        ->and($front)->toContain('Primary 6')
        ->and($front)->toContain(IdCardDesign::SECONDARY);

    expect($response->json('back'))->toContain('If found, please return to:');

    // The editor no longer carries its own copy of the design.
    $editor = file_get_contents(resource_path('views/school-admin/id-cards/templates.blade.php'));

    expect($editor)->not->toContain('Authorized Signature')
        ->and($editor)->not->toContain('linear-gradient(135deg');
});

test('the specimen takes the colours being chosen, not the saved ones', function () {
    // Nothing has been saved when the editor is open, so the colours travel
    // with the request.
    $front = $this->actingAs($this->admin)
        ->getJson(route('id-cards.templates.sample', [
            'secondary_color' => '#004d40',
            'accent_color' => '#f9a825',
        ]))
        ->assertOk()
        ->json('front');

    expect($front)->toContain('#004d40')
        ->and($front)->toContain('#f9a825')
        ->and($front)->not->toContain(IdCardDesign::SECONDARY);
});

test('the specimen covers staff as well as pupils', function () {
    $front = $this->actingAs($this->admin)
        ->getJson(route('id-cards.templates.sample', ['type' => 'teaching_staff']))
        ->assertOk()
        ->json('front');

    // A staff card names their staff number and department, not an admission
    // number and a class.
    expect($front)->toContain('Staff ID')
        ->and($front)->toContain('Department')
        ->and($front)->not->toContain('Admission No.');
});

test('drawing a specimen saves nothing', function () {
    $before = [IssuedIdCard::count(), IdCardTemplate::count(), Student::count()];

    $this->actingAs($this->admin)->getJson(route('id-cards.templates.sample'))->assertOk();

    expect([IssuedIdCard::count(), IdCardTemplate::count(), Student::count()])->toBe($before);
});

test('a specimen belongs to the school asking for it', function () {
    // It carries the school's own name and crest, so it must not be reachable
    // by anyone else, and one school must never be handed another's.
    // Given a plan of its own, so this tests school isolation rather than the
    // plan gate that would otherwise turn it away first.
    $otherSchool = School::factory()->create(['name' => 'Another School']);

    Subscription::factory()->create([
        'school_id' => $otherSchool->id,
        'plan_id' => Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray())->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $stranger = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $otherSchool->id,
    ]);

    $front = $this->actingAs($stranger)
        ->getJson(route('id-cards.templates.sample'))
        ->assertOk()
        ->json('front');

    expect($front)->toContain('Another School')
        ->and($front)->not->toContain('Marvel Int');
});
