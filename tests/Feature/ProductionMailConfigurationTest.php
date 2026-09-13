<?php

use Dotenv\Dotenv;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;

/**
 * The production mail settings build a transport that can actually send.
 *
 * .env.production.example shipped MAIL_SCHEME=tls. It reads like an encryption
 * setting and is not one: the scheme is handed to Symfony's SMTP transport,
 * which accepts "smtp" and "smtps" and throws on anything else. Nothing fails
 * at boot - the exception waits for the first email, which in production is a
 * parent's password reset.
 *
 * Building the transport is enough to prove it. The scheme is checked when the
 * transport is created, and no connection is opened until something is sent,
 * so this runs offline.
 */
beforeEach(function () {
    Mail::purge('smtp');
});

test('the production example builds an SMTP transport without throwing', function () {
    $production = Dotenv::parse((string) file_get_contents(base_path('.env.production.example')));

    config([
        'mail.mailers.smtp.scheme' => $production['MAIL_SCHEME'] ?: null,
        'mail.mailers.smtp.host' => $production['MAIL_HOST'],
        'mail.mailers.smtp.port' => (int) $production['MAIL_PORT'],
    ]);

    expect(Mail::mailer('smtp')->getSymfonyTransport())->not->toBeNull();
});

test('the scheme and port agree, or the handshake fails against a real server', function () {
    $production = Dotenv::parse((string) file_get_contents(base_path('.env.production.example')));

    // smtps is TLS from the first byte and belongs on 465; smtp upgrades with
    // STARTTLS and belongs on 587. The wrong pairing builds fine and then hangs
    // or is refused at connect time, which no offline test would catch.
    $expectedPort = ['smtps' => 465, 'smtp' => 587][$production['MAIL_SCHEME']] ?? null;

    expect($expectedPort)->not->toBeNull()
        ->and((int) $production['MAIL_PORT'])->toBe($expectedPort);
});

test('"tls" is exactly the value that throws, which is why it is gone', function () {
    config(['mail.mailers.smtp.scheme' => 'tls']);

    Mail::mailer('smtp')->getSymfonyTransport();
})->throws(UnsupportedSchemeException::class);
