<?php

use App\Enums\UserRole;
use App\Models\BrandingImage;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\MailDeliveryTestNotification;
use App\Notifications\ResetPasswordNotification;
use App\Services\Mail\MailLogo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * The AkademicNest logo at the top of every email, carried inside the email
 * so it shows without the reader allowing remote images.
 *
 * These send for real through the array mailer (no fakes), then read the
 * message that would have gone out.
 */

/**
 * Send a notification and return the email that left.
 */
function sentEmail(Illuminate\Notifications\Notification $notification, ?User $to = null): Email
{
    $to !== null
        ? $to->notifyNow($notification)
        : Notification::route('mail', 'reader@example.test')->notifyNow($notification);

    $sent = app('mailer')->getSymfonyTransport()->messages()->last();

    expect($sent)->not->toBeNull();

    return $sent->getOriginalMessage();
}

/**
 * The inline logo part of an email, if it has one.
 */
function logoPart(Email $email): ?DataPart
{
    foreach ($email->getAttachments() as $part) {
        if ($part->getName() === MailLogo::CID) {
            return $part;
        }
    }

    return null;
}

test('a password reset code email carries the logo inside it', function () {
    $email = sentEmail(new ResetPasswordNotification('123456'), User::factory()->create());
    $part = logoPart($email);

    expect($part)->not->toBeNull()
        ->and($part->getDisposition())->toBe('inline')
        ->and($part->getMediaType().'/'.$part->getMediaSubtype())->toBe('image/png');

    // The bundled mark, with its transparent border trimmed so it fills the
    // space: smaller than the file, and still the mark (a PNG with alpha).
    $bundled = getimagesize(public_path('images/logo-mark.png'));
    $sent = getimagesizefromstring($part->getBody());

    expect($sent[0])->toBeLessThan($bundled[0])
        ->and($sent[1])->toBeLessThan($bundled[1])
        ->and($sent['mime'])->toBe('image/png');

    // The header asks for the logo by name, and in the message as it goes
    // out that name has been swapped for the attached part's Content-ID.
    $mime = quoted_printable_decode($email->toString());

    expect($email->getHtmlBody())->toContain('src="cid:'.MailLogo::CID.'"')
        ->and($mime)->toContain('src="cid:'.$part->getContentId().'"')
        ->and($mime)->toContain('Content-ID: <'.$part->getContentId().'>');

    expect($email->getHtmlBody())
        ->toContain('alt="'.Setting::current()->site_name.'"')
        ->toContain('123456');
});

test('the test email carries it too, once, and the text version is untouched', function () {
    $email = sentEmail(new MailDeliveryTestNotification('the test suite'));

    $logos = array_filter($email->getAttachments(), fn ($part) => $part->getName() === MailLogo::CID);

    expect($logos)->toHaveCount(1)
        ->and($email->getTextBody())->not->toContain('cid:');
});

test('a logo uploaded by the Super Admin is used instead of the bundled one', function () {
    $image = imagecreatetruecolor(300, 100);
    imagefill($image, 0, 0, imagecolorallocate($image, 30, 90, 200));
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();

    BrandingImage::remember('branding/our-logo.png', $bytes);
    Setting::current()->update(['logo_path' => 'branding/our-logo.png']);

    $email = sentEmail(new MailDeliveryTestNotification('the test suite'));

    expect(logoPart($email)->getBody())->toBe($bytes)
        ->and($email->getHtmlBody())
        // 300x100 with no border to trim, shown 64 high, is 192 wide.
        ->toContain('width="192"')
        ->toContain('height="64"');
});

/**
 * A solid PNG, or WebP, of the given size and colour, as the Themes page would store it.
 */
function brandingImage(int $width, int $height, array $rgb, string $format = 'png'): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));

    ob_start();
    $format === 'webp' ? imagewebp($image) : imagepng($image);

    return ob_get_clean();
}

/**
 * Upload a logo the way the Themes page records it.
 */
function uploadCompanyLogo(string $path, string $bytes): void
{
    BrandingImage::remember($path, $bytes);
    Setting::current()->update(['logo_path' => $path]);
}

test('when the company changes its logo, the very next email carries the new one', function () {
    $first = brandingImage(200, 100, [200, 30, 30]);
    uploadCompanyLogo('branding/logo-first.png', $first);

    expect(logoPart(sentEmail(new MailDeliveryTestNotification('before the change')))->getBody())->toBe($first);

    $second = brandingImage(100, 100, [30, 160, 60]);
    uploadCompanyLogo('branding/logo-second.png', $second);

    $email = sentEmail(new MailDeliveryTestNotification('after the change'));

    expect(logoPart($email)->getBody())->toBe($second)
        ->and($email->getHtmlBody())->toContain('width="64"')->toContain('height="64"');
});

test('a logo uploaded on the Themes page is the one the next email carries', function () {
    Storage::fake('public');
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    // A logo with a wide 4:1 shape, so it cannot be mistaken for the square mark.
    $this->actingAs($superAdmin)
        ->post(route('super-admin.themes.logo.update'), ['logo' => UploadedFile::fake()->image('new-logo.png', 400, 100)])
        ->assertSessionHasNoErrors();

    $stored = BrandingImage::contents(Setting::current()->logo_path);
    $sent = logoPart(sentEmail(new MailDeliveryTestNotification('the test suite')))->getBody();
    $size = getimagesizefromstring($sent);

    expect($stored)->not->toBeNull()
        ->and([$size[0], $size[1]])->toBe([400, 100])
        ->and($sent)->toBe($stored);
});

test('a WebP logo is sent as a PNG, since Outlook cannot show WebP', function () {
    uploadCompanyLogo('branding/logo.webp', brandingImage(240, 80, [20, 60, 180], 'webp'));

    $part = logoPart(sentEmail(new MailDeliveryTestNotification('the test suite')));
    $size = getimagesizefromstring($part->getBody());

    // Still the company's logo: its 3:1 shape, not the bundled square mark.
    expect($part->getMediaSubtype())->toBe('png')
        ->and($size['mime'])->toBe('image/png')
        ->and([$size[0], $size[1]])->toBe([240, 80]);
});

test('the company name with the logo is the Site Name from settings', function () {
    Setting::current()->update(['site_name' => 'Brightpath Learning']);

    $email = sentEmail(new MailDeliveryTestNotification('the test suite'));

    expect($email->getHtmlBody())
        ->toContain('alt="Brightpath Learning"')
        ->toContain('© '.date('Y').' Brightpath Learning.')
        ->and($email->getTextBody())->toContain('Brightpath Learning');
});

test('an uploaded logo mail clients cannot show falls back to the bundled mark', function () {
    $bundled = logoPart(sentEmail(new MailDeliveryTestNotification('a clean install')))->getBody();

    BrandingImage::remember('branding/our-logo.ico', 'not really an icon');
    Setting::current()->update(['logo_path' => 'branding/our-logo.ico']);

    $email = sentEmail(new MailDeliveryTestNotification('the test suite'));

    expect(logoPart($email)->getBody())->toBe($bundled);
});

test('an email is still sent when there is no logo to show', function () {
    $this->mock(MailLogo::class, function ($mock) {
        $mock->shouldReceive('image')->andReturn(null);
        $mock->shouldReceive('embedInto')->andReturnNull();
    });

    $email = sentEmail(new MailDeliveryTestNotification('the test suite'));

    expect(logoPart($email))->toBeNull()
        ->and($email->getHtmlBody())->not->toContain('cid:')
        ->toContain('Email delivery is working.');
});
