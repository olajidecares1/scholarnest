<?php

use App\Enums\UserRole;
use App\Models\AdminRole;
use App\Models\AuditLog;
use App\Models\LegalDocument;
use App\Models\School;
use App\Models\User;

/**
 * The AkademicNest Team's editor for the platform's own legal documents.
 *
 * These began as markdown files, which was right while they were being drafted
 * and wrong once they were published: a lawyer's amendment to the Privacy
 * Policy should not need a developer and a deploy, and on most hosting the
 * application cannot write to resources/ anyway.
 *
 * What is being protected here is that the editor is careful in the ways a
 * contract deserves - its own permission, an audit entry for every save, and a
 * slug that cannot be renamed out from under the links schools already hold.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
});

describe('the team can find and read them', function () {
    test('the sidebar carries a link', function () {
        expect($this->actingAs($this->admin)->get(route('super-admin.legal.index'))->getContent())
            ->toContain('Legal Documents');
    });

    test('the index lists all six with their live addresses', function () {
        $response = $this->actingAs($this->admin)->get(route('super-admin.legal.index'))->assertOk();

        foreach (LegalDocument::inOrder() as $document) {
            $response->assertSee($document->title)
                ->assertSee('/legal/'.$document->slug);
        }
    });

    test('the editor shows the actual page, not a placeholder', function () {
        // The point of the request: clicking through to the Privacy Policy has
        // to show what a school really reads, rendered - not an empty stub.
        $this->actingAs($this->admin)
            ->get(route('super-admin.legal.edit', 'privacy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            // The markdown source, to edit.
            ->assertSee('name="body"', false)
            // And the rendered page beside it.
            ->assertSee('legal-prose', false)
            ->assertSee('Who we are');
    });

    test('it warns about placeholders and lawyer notes still in the text', function () {
        // Both are easy to forget once the page renders and looks finished.
        expect($this->actingAs($this->admin)->get(route('super-admin.legal.edit', 'privacy'))->getContent())
            ->toContain('placeholders still to fill in')
            ->toContain('notes for your lawyer');
    });
});

describe('an edit reaches schools', function () {
    test('saving changes the public page immediately', function () {
        $this->actingAs($this->admin)
            ->put(route('super-admin.legal.update', 'privacy'), [
                'title' => 'Privacy Policy',
                'summary' => 'How we handle your information.',
                'body' => "## 1. Who we are\n\nAkademicNest Ltd, of 12 Example Road, Lagos.",
                'version' => '1.1',
                'effective_date' => '1 October 2026',
                'is_published' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('super-admin.legal.edit', 'privacy'));

        $this->get(route('legal.show', 'privacy'))
            ->assertOk()
            ->assertSee('12 Example Road, Lagos')
            ->assertSee('1.1');
    });

    test('the slug cannot be changed, however it is submitted', function () {
        // It is the public URL, printed on the registration form and soon in
        // emails and bookmarks. It is not in $fillable, so a slug in the
        // request is not rejected - it is simply not read, which is stronger
        // than rejecting it, since a check can be forgotten.
        $this->actingAs($this->admin)
            ->put(route('super-admin.legal.update', 'privacy'), [
                'title' => 'Privacy Policy',
                'body' => 'Still here.',
                'version' => '1.0',
                'slug' => 'somewhere-else',
            ])
            ->assertSessionHasNoErrors();

        expect(LegalDocument::where('slug', 'privacy')->exists())->toBeTrue()
            ->and(LegalDocument::where('slug', 'somewhere-else')->exists())->toBeFalse();
    });

    test('an empty document is refused', function () {
        $this->actingAs($this->admin)
            ->put(route('super-admin.legal.update', 'terms'), [
                'title' => 'Terms & Conditions',
                'body' => '',
                'version' => '1.0',
            ])
            ->assertSessionHasErrors('body');

        expect(LegalDocument::published('terms')->body)->not->toBeEmpty();
    });

    test('a version is required, so a change can be identified afterwards', function () {
        // Without it there is no way to say which version a school agreed to.
        $this->actingAs($this->admin)
            ->put(route('super-admin.legal.update', 'terms'), [
                'title' => 'Terms & Conditions',
                'body' => 'Something.',
                'version' => '',
            ])
            ->assertSessionHasErrors('version');
    });

    test('unticking published actually unpublishes, rather than being ignored', function () {
        // A browser omits an unchecked box entirely, so reading it from the
        // validated data would find nothing there and leave the document
        // published for ever.
        $this->actingAs($this->admin)
            ->put(route('super-admin.legal.update', 'security'), [
                'title' => 'Security & Data Handling Statement',
                'body' => 'Content.',
                'version' => '1.0',
            ]);

        expect(LegalDocument::where('slug', 'security')->first()->is_published)->toBeFalse();
    });

    test('every save is recorded with who made it', function () {
        $this->actingAs($this->admin)
            ->put(route('super-admin.legal.update', 'terms'), [
                'title' => 'Terms & Conditions',
                'body' => 'Revised.',
                'version' => '2.0',
                'is_published' => '1',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'legal-document.updated',
            'user_id' => $this->admin->id,
        ]);

        expect(LegalDocument::published('terms')->updated_by)->toBe($this->admin->id);
    });

    test('the audit entry records the version it moved to', function () {
        $this->actingAs($this->admin)
            ->put(route('super-admin.legal.update', 'terms'), [
                'title' => 'Terms & Conditions',
                'body' => 'Revised.',
                'version' => '2.0',
                'is_published' => '1',
            ]);

        expect(AuditLog::latest('id')->first()->description)
            ->toContain('version 2.0')
            ->toContain('was 1.0');
    });
});

describe('editing them is its own permission', function () {
    test('full CMS access is not enough', function () {
        // The distinction the permission exists for: writing a blog post and
        // rewriting the contract every school has agreed to are not the same
        // level of trust.
        $role = AdminRole::create([
            'name' => 'Content Editor',
            'slug' => 'content-editor',
            'permissions' => ['manage_cms'],
        ]);

        $editor = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'admin_role_id' => $role->id,
        ]);

        $this->actingAs($editor)->get(route('super-admin.legal.index'))->assertForbidden();

        $this->actingAs($editor)
            ->put(route('super-admin.legal.update', 'terms'), [
                'title' => 'Hijacked', 'body' => 'x', 'version' => '9.9',
            ])
            ->assertForbidden();

        expect(LegalDocument::published('terms')->title)->toBe('Terms & Conditions');
    });

    test('a role granted it gets in', function () {
        $role = AdminRole::create([
            'name' => 'Legal Officer',
            'slug' => 'legal-officer',
            'permissions' => ['manage_legal'],
        ]);

        $officer = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'admin_role_id' => $role->id,
        ]);

        $this->actingAs($officer)->get(route('super-admin.legal.index'))->assertOk();
    });

    test('it is offered on the roles screen, so it can be granted', function () {
        expect(AdminRole::PERMISSIONS)->toHaveKey('manage_legal');
    });

    test('a school admin cannot reach the editor at all', function () {
        $school = activateSchool(School::factory()->create());
        $schoolAdmin = User::factory()->create([
            'role' => UserRole::SchoolAdmin,
            'school_id' => $school->id,
        ]);

        $this->actingAs($schoolAdmin)->get(route('super-admin.legal.index'))->assertForbidden();
    });

    test('a stranger is sent to sign in rather than shown the editor', function () {
        $this->get(route('super-admin.legal.index'))->assertRedirect();
    });
});
