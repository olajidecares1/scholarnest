<?php

use Illuminate\Support\Facades\Hash;

/**
 * The test suite hashes passwords cheaply, and this asserts it.
 *
 * config/hashing.php defaults to argon2id at 64MB memory cost. That is the
 * right production setting and the wrong test one: the suite hashes and
 * verifies thousands of times inside one long-lived PHP process, and when
 * that 64MB allocation fails, password_verify() does not raise - it returns
 * FALSE, which surfaces as "These credentials do not match our records" for
 * an entirely correct password.
 *
 * That is not hypothetical. It made a shifting handful of login tests fail in
 * full-suite runs and pass in isolation, and it took the suite from 6 minutes
 * to 24. phpunit.xml sets HASH_DRIVER=bcrypt to prevent it.
 *
 * This test exists because the failure is silent: remove that line and
 * nothing announces it except tests that fail somewhere else, intermittently,
 * for reasons that look nothing like hashing.
 */
test('the suite hashes with cheap bcrypt, not production argon2id', function () {
    expect(config('hashing.driver'))->toBe('bcrypt')
        ->and(config('hashing.bcrypt.rounds'))->toBe('4');

    expect(Hash::make('password'))->toStartWith('$2y$04$');
});
