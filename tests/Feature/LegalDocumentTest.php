<?php

use App\Models\LegalDocument;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * AkademicNest's own legal documents, and the agreement that links to them.
 *
 * Two things are being protected here.
 *
 * The first is that a school can READ what it is agreeing to. The registration
 * form used to carry three links that all pointed at "#", so a school ticked a
 * box agreeing to documents it had no way of opening - and one of the three had
 * never existed at all.
 *
 * The second is that the box is a REAL gate. The checkbox carries `required`,
 * but that is a browser courtesy: it is removed with developer tools in a
 * second and absent entirely from a request made outside a browser. The rule
 * that matters runs on the server.
 */
describe('the documents are readable without an account', function () {
    test('the index lists every published document', function () {
        $response = $this->get(route('legal.index'))->assertOk();

        foreach (LegalDocument::inOrder() as $document) {
            $response->assertSee($document->title);
        }
    });

    test('each one renders', function (string $slug) {
        $this->get(route('legal.show', $slug))
            ->assertOk()
            // Rendered markdown, not the raw source.
            ->assertSee('<h2', false);
    })->with(LegalDocument::SLUGS);

    test('the Terms carry their actual terms', function () {
        $this->get(route('legal.show', 'terms'))
            ->assertOk()
            ->assertSee('Ownership of the Platform')
            ->assertSee('Limitation of liability')
            ->assertSee('Governing law');
    });

    test('the version and date are shown, so an agreement can name what was agreed', function () {
        // The date comes from the row's own updated_at now, not from a line in
        // the file - so it moves when the AkademicNest Team edits the document,
        // which is the whole point of it being on the page.
        $document = LegalDocument::published('terms');

        $this->get(route('legal.show', 'terms'))
            ->assertOk()
            ->assertSee($document->version)
            ->assertSee($document->updated_at->format('j F Y'));
    });

    test('none of them requires signing in', function (string $slug) {
        // The whole point: they are linked from the registration form, which
        // nobody has an account on yet.
        $this->assertGuest();

        $this->get(route('legal.show', $slug))->assertOk();
    })->with(LegalDocument::SLUGS);
});

describe('the internal notes never reach a school', function () {
    test('no published page carries a passage written for counsel', function (string $slug) {
        // These documents contain passages addressed to a lawyer - "this
        // transfer needs a lawful basis", "not yet reviewed" - and a school
        // being asked to agree to the Terms must not be shown them. They are
        // fenced in the source and stripped on the way out.
        //
        // This test is the real safeguard rather than the fence: a fence can be
        // forgotten, and then this fails.
        $html = $this->get(route('legal.show', $slug))->assertOk()->getContent();

        foreach (['For legal review', 'For counsel', 'internal:start', 'internal:end'] as $phrase) {
            expect($html)->not->toContain($phrase);
        }
    })->with(LegalDocument::SLUGS);

    test('the draft warning is stripped, because a published document is not a draft', function () {
        expect($this->get(route('legal.show', 'privacy'))->getContent())
            ->not->toContain('not yet reviewed by a lawyer');
    });

    test('but the disclosures a school SHOULD see survive', function () {
        // Stripping must not become a way of quietly removing anything
        // unflattering. These are the honest admissions, and they stay.
        $this->get(route('legal.show', 'privacy'))
            ->assertSee('Anthropic')
            ->assertSee('Blood group is health information');

        $this->get(route('legal.show', 'terms'))
            ->assertSee('Exclusive is not currently available for purchase');

        $this->get(route('legal.show', 'cookies'))
            ->assertSee('does not currently display a cookie banner');
    });
});

describe('a slug cannot name a file', function () {
    test('the audit and gap report are not reachable', function () {
        // It sits in the same directory as the six published documents and is
        // full of unfixed vulnerabilities. It must never be served.
        $this->get('/legal/00-LEGAL-REVIEW-AND-GAPS')->assertNotFound();
        $this->get('/legal/README')->assertNotFound();
    });

    test('a traversal attempt is simply not a document', function () {
        // The slug is a KEY into a fixed list, never part of a path, so there
        // is nothing to escape from - "../.env" is not a key.
        foreach (['..%2F..%2F.env', 'terms-and-conditions', 'anything'] as $attempt) {
            $this->get('/legal/'.$attempt)->assertNotFound();
        }
    });

    test('the resolver refuses an unknown slug rather than returning nothing', function () {
        expect(LegalDocument::published('terms'))->toBeInstanceOf(LegalDocument::class);

        LegalDocument::published('not-a-document');
    })->throws(NotFoundHttpException::class);

    test('an unpublished document is a 404, not a half-rendered page', function () {
        // A page that exists but is not ready is worse than one that is
        // honestly missing - and a school must never be shown a document it is
        // being asked to agree to in a state the team has taken down.
        LegalDocument::where('slug', 'security')->update(['is_published' => false]);

        $this->get(route('legal.show', 'security'))->assertNotFound();

        // And it leaves the index rather than appearing as a dead entry.
        $this->get(route('legal.index'))->assertOk()
            ->assertDontSee('Security &amp; Data Handling Statement', false);
    });
});

describe('the registration form links to them', function () {
    test('every link opens a real document instead of going nowhere', function () {
        $html = $this->get(route('register'))->assertOk()->getContent();

        expect($html)->toContain(route('legal.show', 'terms'))
            ->toContain(route('legal.show', 'privacy'))
            ->toContain(route('legal.show', 'cookies'))
            // The three dead links this replaced.
            ->not->toContain('href="#" class="font-medium text-primary-500');
    });

    test('they open in a new tab, so a half-filled form is not lost', function () {
        expect($this->get(route('register'))->getContent())
            ->toContain('target="_blank" rel="noopener"');
    });

    test('the form no longer names a document that does not exist', function () {
        expect($this->get(route('register'))->getContent())
            ->not->toContain('Data Protection Policy');
    });
});

describe('the agreement is a gate, not a decoration', function () {
    /**
     * Everything a valid registration needs, minus the agreement.
     *
     * @return array<string, string>
     */
    function legalRegistrationPayload(): array
    {
        return [
            'school_name' => 'Consent Academy',
            'email' => 'admin@consent-academy.test',
            'phone' => '08012345678',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ];
    }

    test('an unticked box is refused, and creates nothing', function () {
        // A browser omits an unchecked box entirely rather than sending false,
        // so this is exactly what an unticked form posts.
        $this->post(route('register'), legalRegistrationPayload())
            ->assertSessionHasErrors('terms');

        $this->assertDatabaseMissing('schools', ['name' => 'Consent Academy']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@consent-academy.test']);
        $this->assertGuest();
    });

    test('stripping the required attribute achieves nothing', function () {
        // The point of the whole test file: the browser attribute is a
        // courtesy, and the server does not consult it. A request that never
        // came from the form is refused just the same.
        foreach (['0', '', 'false', 'no', 'maybe'] as $value) {
            $this->post(route('register'), [...legalRegistrationPayload(), 'terms' => $value])
                ->assertSessionHasErrors('terms');
        }

        $this->assertDatabaseMissing('users', ['email' => 'admin@consent-academy.test']);
    });

    test('the refusal names the documents that actually exist', function () {
        $this->post(route('register'), legalRegistrationPayload())
            ->assertSessionHasErrors([
                'terms' => 'Please read and accept the Terms & Conditions, Privacy Policy and Cookie Policy to continue.',
            ]);
    });

    test('a ticked box registers the school', function () {
        $this->post(route('register'), [...legalRegistrationPayload(), 'terms' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schools', ['name' => 'Consent Academy']);
        $this->assertDatabaseHas('users', ['email' => 'admin@consent-academy.test']);
    });
});
