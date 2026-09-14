<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\NewsPost;
use App\Models\School;
use App\Models\SchoolEvent;
use App\Models\SchoolWebsite;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Background images for two cards on the public website.
 *
 * TWO settings, not three. Latest News and Upcoming Events share one card and
 * one background, the image stays put while the content rotates between
 * stories and events, and Academic Excellence is a separate card with its own.
 *
 * The cards themselves are untouched. What changed is what sits behind them.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    $this->website = SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'is_published' => true]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

function wideImage(string $name = 'backdrop.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 1600, 900);
}

describe('News and Events share ONE background', function () {
    test('one upload sets the background for both panels', function () {
        $this->actingAs($this->admin)
            ->post(route('website.news-events-card-background'), ['background_image' => wideImage()])
            ->assertRedirect();

        $path = $this->website->fresh()->news_events_card_image_path;

        expect($path)->not->toBeNull();
        Storage::disk('public')->assertExists($path);
    });

    test('there is no separate setting for events', function () {
        // The whole point of the rule. One card, one image, a second endpoint
        // would say they were two cards, and whichever was saved last would
        // appear to overwrite the other.
        expect(fn () => route('website.events-card-background'))->toThrow(Exception::class);
    });

    test('the Events page points at the News page rather than repeating the control', function () {
        $this->actingAs($this->admin)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('shares a card with Latest News')
            ->assertSee(route('news.index'))
            // No second upload form.
            ->assertDontSee('name="background_image"', false);
    });

    test('the News page carries the shared control', function () {
        $this->actingAs($this->admin)
            ->get(route('news.index'))
            ->assertOk()
            ->assertSee('News &amp; Events card background', false)
            ->assertSee('name="background_image"', false);
    });
});

describe('Academic Excellence has its own', function () {
    test('it is a separate setting from the news and events one', function () {
        $this->actingAs($this->admin)
            ->post(route('website.academics-card-background'), ['background_image' => wideImage('academics.jpg')])
            ->assertRedirect();

        $website = $this->website->fresh();

        expect($website->academics_card_image_path)->not->toBeNull()
            // Setting one must not touch the other.
            ->and($website->news_events_card_image_path)->toBeNull();
    });

    test('the Academics page carries the control', function () {
        $this->actingAs($this->admin)
            ->get(route('academics.index'))
            ->assertOk()
            ->assertSee('Academic Excellence card background')
            ->assertSee('name="background_image"', false);
    });
});

describe('the image belongs to one school and one school only', function () {
    test('the school comes from the signed-in user, never from the form', function () {
        $other = activateSchool(School::factory()->create(), PlanKey::Standard);
        SchoolWebsite::factory()->create(['school_id' => $other->id]);

        $this->actingAs($this->admin)->post(route('website.news-events-card-background'), [
            'background_image' => wideImage(),
            // Ignored. There is no school id in the form for a reason.
            'school_id' => $other->id,
        ]);

        expect($this->website->fresh()->news_events_card_image_path)->not->toBeNull()
            ->and($other->website->fresh()->news_events_card_image_path)->toBeNull();
    });

    test("school A's background never appears on school B's website", function () {
        $this->actingAs($this->admin)->post(route('website.news-events-card-background'), [
            'background_image' => wideImage(),
        ]);

        $path = $this->website->fresh()->news_events_card_image_path;

        $other = activateSchool(School::factory()->create(), PlanKey::Standard);
        SchoolWebsite::factory()->create(['school_id' => $other->id, 'is_published' => true]);

        $this->get(route('public.school-website', $other))
            ->assertOk()
            ->assertDontSee($path);
    });
});

describe('replacing and removing', function () {
    test('replacing deletes the file it replaced', function () {
        $this->actingAs($this->admin)->post(route('website.news-events-card-background'), ['background_image' => wideImage('first.jpg')]);
        $first = $this->website->fresh()->news_events_card_image_path;

        $this->actingAs($this->admin)->post(route('website.news-events-card-background'), ['background_image' => wideImage('second.jpg')]);
        $second = $this->website->fresh()->news_events_card_image_path;

        expect($second)->not->toBe($first);
        Storage::disk('public')->assertExists($second);
        // Nothing orphaned on disk.
        Storage::disk('public')->assertMissing($first);
    });

    test('removing clears the column and deletes the file', function () {
        $this->actingAs($this->admin)->post(route('website.news-events-card-background'), ['background_image' => wideImage()]);
        $path = $this->website->fresh()->news_events_card_image_path;

        $this->actingAs($this->admin)
            ->post(route('website.news-events-card-background'), ['remove' => 1])
            ->assertRedirect();

        expect($this->website->fresh()->news_events_card_image_path)->toBeNull();
        Storage::disk('public')->assertMissing($path);
    });
});

describe('what it will accept', function () {
    test('it refuses something that is not an image', function () {
        $this->actingAs($this->admin)
            ->post(route('website.news-events-card-background'), [
                'background_image' => UploadedFile::fake()->create('script.php', 40, 'application/x-php'),
            ])
            ->assertSessionHasErrors('background_image');

        expect($this->website->fresh()->news_events_card_image_path)->toBeNull();
    });

    test('it refuses a file renamed to look like an image', function () {
        // The content is checked, not the extension it arrived with.
        $this->actingAs($this->admin)
            ->post(route('website.news-events-card-background'), [
                'background_image' => UploadedFile::fake()->create('payload.jpg', 40, 'application/x-php'),
            ])
            ->assertSessionHasErrors('background_image');
    });

    test('it refuses one that is too large', function () {
        // The limit is 15MB now, not 5MB: a photograph straight off a modern
        // phone is often over 5MB, and it is scaled down on arrival, so what
        // is stored is far smaller than what was sent. See ImageProfile.
        $this->actingAs($this->admin)
            ->post(route('website.news-events-card-background'), [
                'background_image' => UploadedFile::fake()->image('huge.jpg', 3000, 2000)->size(16000),
            ])
            ->assertSessionHasErrors('background_image');
    });

    test('it refuses one too small to be a background', function () {
        // A 200px-wide image stretched across a section is a smear.
        $this->actingAs($this->admin)
            ->post(route('website.news-events-card-background'), [
                'background_image' => UploadedFile::fake()->image('tiny.jpg', 200, 100),
            ])
            ->assertSessionHasErrors('background_image');
    });

    test('and one that would be visibly upscaled across a full-width band', function () {
        // The floor was 600 wide, which passes validation and then gets blown
        // up more than twice on a 1400px section. No amount of care elsewhere
        // makes an upscaled photograph look sharp, and that is precisely how
        // the first background came out.
        $this->actingAs($this->admin)
            ->post(route('website.news-events-card-background'), [
                'background_image' => UploadedFile::fake()->image('smallish.jpg', 800, 400),
            ])
            ->assertSessionHasErrors('background_image');

        // 1200 wide is the floor, and it is accepted.
        $this->actingAs($this->admin)
            ->post(route('website.news-events-card-background'), [
                'background_image' => UploadedFile::fake()->image('ok.jpg', 1200, 500),
            ])
            ->assertSessionHasNoErrors();
    });
});

describe('on the public website', function () {
    test('the background appears behind both panels, from one image', function () {
        $this->actingAs($this->admin)->post(route('website.news-events-card-background'), ['background_image' => wideImage()]);
        $this->actingAs($this->admin)->post(route('website.academics-card-background'), ['background_image' => wideImage('academics.jpg')]);

        $website = $this->website->fresh();
        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($html)->toContain($website->newsEventsCardImageUrl())
            ->toContain($website->academicsCardImageUrl())
            // One image for news and events, so it appears once, not once per panel.
            ->and(substr_count($html, $website->newsEventsCardImageUrl()))->toBe(1);
    });

    test('text stays readable over it', function () {
        $this->actingAs($this->admin)->post(route('website.news-events-card-background'), ['background_image' => wideImage()]);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        // The card text is near-black and the cards are white, so the wash is
        // white too, a dark scrim would mean recolouring the type.
        //
        // A GRADIENT, not the flat 82% this used to assert. Eighty-two per cent
        // across the whole section left almost nothing of the photograph
        // visible, which is what made the first one look washed out. The wash
        // is strong only at the top, where the section headings sit on the
        // picture with nothing behind them, and falls away below, where every
        // card brings its own solid white and the wash was veiling the
        // photograph for no benefit at all.
        expect($html)->toContain('edn-photo-scrim');
    });

    test('the tint is BLACK, not a white wash', function () {
        // The white version, flat, and then as a gradient, is what made the
        // photograph look hazy: white over a bright picture flattens its
        // contrast, and a flattened picture reads as blurred whether or not
        // anything is blurring it. A dark tint deepens the picture's own
        // contrast instead, so the detail stays crisp.
        $css = file_get_contents(base_path('resources/css/app.css'));

        expect($css)->toContain('.edn-photo-scrim')
            ->toContain('background-color: rgb(0 0 0 / 45%)');

        // And no white wash left anywhere in it.
        expect(substr($css, strpos($css, '.edn-photo-scrim'), 900))
            ->not->toContain('rgb(255 255 255 / 92%)');
    });

    test('text on the photograph turns white, and only on the photograph', function () {
        // The project's contrast rule, applied the other way round: the ground
        // beneath these headings is dark now, so the type has to be light. A
        // section with no background image keeps the school's blue.
        $withoutImage = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($withoutImage)->toContain('tracking-[0.18em] text-primary-700')
            ->not->toContain('edn-on-photo');

        $this->actingAs($this->admin)->post(route('website.academics-card-background'), ['background_image' => wideImage()]);
        $this->actingAs($this->admin)->post(route('website.news-events-card-background'), ['background_image' => wideImage('news.jpg')]);

        $withImage = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($withImage)->toContain('edn-on-photo');
    });

    test('and carries a shadow, so it reads over a pale photograph too', function () {
        // A tint is one number and a photograph is not, a school can upload
        // something nearly white. The shadow costs nothing and blurs no image.
        expect(file_get_contents(base_path('resources/css/app.css')))
            ->toContain('text-shadow: 0 1px 3px rgb(0 0 0 / 55%)');
    });

    test('a school that has set none gets the plain background it always had', function () {
        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($html)->toContain('background-color: #f6f9fd;')
            ->toContain('background-color: #f9fafb;')
            ->not->toContain('edn-photo-scrim');
    });

    test('the cards themselves are unchanged', function () {
        // Enough of each to be past the three-item threshold, so the rotating
        // markup is actually on the page to be checked. Without this the
        // panels render in their static form and the assertion below would
        // pass or fail for reasons that have nothing to do with backgrounds.
        foreach (range(1, 4) as $i) {
            NewsPost::factory()->create([
                'school_id' => $this->school->id, 'is_published' => true, 'published_at' => now()->subDays($i),
            ]);
            SchoolEvent::factory()->create([
                'school_id' => $this->school->id, 'starts_at' => now()->addDays($i),
            ]);
        }

        $this->actingAs($this->admin)->post(route('website.news-events-card-background'), ['background_image' => wideImage()]);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        // Same 328px scrolling tracks, same groups of three, same rotation.
        // The image sits behind them and changes nothing about them.
        expect($html)->toContain('h-[328px] overflow-y-auto')
            ->toContain('mb-4 min-h-[312px] space-y-3')
            ->toContain('x-data="marqueeList()"')
            ->toContain('Show events 1 to 3');
    });
});

test('a stranger cannot set another school\'s background', function () {
    $other = activateSchool(School::factory()->create(), PlanKey::Standard);
    SchoolWebsite::factory()->create(['school_id' => $other->id]);
    $stranger = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $other->id]);

    $this->actingAs($stranger)->post(route('website.news-events-card-background'), [
        'background_image' => wideImage(),
    ]);

    // Theirs is set; ours is untouched.
    expect($other->website->fresh()->news_events_card_image_path)->not->toBeNull()
        ->and($this->website->fresh()->news_events_card_image_path)->toBeNull();
});

describe('the About section has one too', function () {
    test('a School Admin can set it from the About tab', function () {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('website.about-card-background'), ['background_image' => wideImage('about-bg.jpg')])
            ->assertRedirect();

        expect($this->school->website->fresh()->about_card_image_path)->not->toBeNull();
    });

    test('it is a SEPARATE picture from the one beside the prose', function () {
        Storage::fake('public');

        // about_image_path is the photograph in the frame; about_card_image_path
        // is the picture behind the whole band. A school can set either, both
        // or neither, and setting one must not disturb the other.
        $this->school->website->update(['about_image_path' => 'website/in-frame.jpg']);

        $this->actingAs($this->admin)->post(route('website.about-card-background'), ['background_image' => wideImage()]);

        $website = $this->school->website->fresh();

        expect($website->about_image_path)->toBe('website/in-frame.jpg')
            ->and($website->about_card_image_path)->not->toBe('website/in-frame.jpg')
            ->and($website->about_card_image_path)->not->toBeNull();
    });

    test('the whole About column turns light over it', function () {
        Storage::fake('public');

        $this->school->website->update([
            'about_headline' => 'Building strong minds.',
            'mission' => 'To teach well.',
            'vision' => 'To lead.',
            'values' => 'Integrity.',
        ]);

        $this->actingAs($this->admin)->post(route('website.about-card-background'), ['background_image' => wideImage()]);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($html)->toContain('edn-on-photo')
            ->toContain('edn-on-photo-muted')
            // The hairlines between the pillars lighten too, a gray-200 rule
            // is invisible on a dark photograph, and the three columns would
            // read as one block of text.
            ->toContain('border-white/30');
    });

    test('and keeps its dark text when no background is set', function () {
        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($html)->not->toContain('edn-on-photo')
            ->toContain('border-gray-200');
    });

    test('it can be removed again', function () {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('website.about-card-background'), ['background_image' => wideImage()]);
        $this->actingAs($this->admin)->post(route('website.about-card-background'), ['remove' => '1']);

        expect($this->school->website->fresh()->about_card_image_path)->toBeNull();
    });

    test('one school cannot set another school background', function () {
        Storage::fake('public');

        $other = activateSchool(School::factory()->create(), PlanKey::Standard);

        $this->actingAs($this->admin)->post(route('website.about-card-background'), [
            'background_image' => wideImage(),
            // Ignored, the school comes from the signed-in user.
            'school_id' => $other->id,
        ]);

        expect($other->website?->about_card_image_path)->toBeNull()
            ->and($this->school->website->fresh()->about_card_image_path)->not->toBeNull();
    });
});

describe('the About tab makes its two pictures tellable apart', function () {
    test('the About background field is on the About Us tab', function () {
        // It was there all along and could not be found, because the field
        // directly beneath it is also a picture, also on the About tab, and
        // was labelled "School photograph", while actually feeding the
        // CONTACT section.
        $this->actingAs($this->admin)
            ->get(route('website.index'))
            ->assertOk()
            ->assertSee('About section background')
            ->assertSee('Sits behind the About section')
            ->assertSee(route('website.about-card-background'), false);
    });

    test('the other picture says plainly that it is not the About background', function () {
        $this->actingAs($this->admin)
            ->get(route('website.index'))
            ->assertOk()
            ->assertSee('This is not the About background')
            ->assertSee('Contact')
            ->assertDontSee('>School photograph<', false);
    });
});

test('the plan-restricted page carries no gradient', function () {
    // Plain white, like the registration and portal pages.
    expect(file_get_contents(resource_path('views/errors/plan-restricted.blade.php')))
        ->not->toContain('bg-gradient');
});

test('the registration page carries no gradient', function () {
    expect(file_get_contents(resource_path('views/auth/register.blade.php')))
        ->not->toContain('bg-gradient');
});

test('the hero slider has a heading somebody can actually find', function () {
    // It was a small grey uppercase label below a long form, and got reported
    // as missing. It is a titled panel now, like the About section.
    $this->actingAs($this->admin)
        ->get(route('website.index'))
        ->assertOk()
        ->assertSee('Hero Slider')
        ->assertSee('The photographs at the very top of your home page')
        ->assertSee(route('website.hero-slides.store'), false);
});

test('the portal chrome is plain white, with no tinted glow behind it', function () {
    // Two 384px blurred blue discs used to sit behind every portal page and
    // read unmistakably as a gradient, while a search for "gradient" found
    // nothing, because that is not what they were.
    // Asserted against the RENDERED page, not the source: the source carries
    // a comment explaining what was removed, which mentions the very class
    // being asserted against.
    $html = $this->get(route('portal.find.show'))->assertOk()->getContent();

    expect($html)->toContain('flex-1 overflow-hidden bg-white')
        ->not->toContain('blur-3xl')
        ->not->toContain('bg-gray-50');
});
