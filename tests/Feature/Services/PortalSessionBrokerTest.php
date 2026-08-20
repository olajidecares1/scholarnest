<?php

use App\Models\School;
use App\Models\User;
use App\Services\PortalSessionBroker;

beforeEach(function () {
    $this->broker = app(PortalSessionBroker::class);
    $this->school = School::factory()->create();
    $this->user = User::factory()->create(['school_id' => $this->school->id]);
});

test('issuing a token creates a row with the expected data', function () {
    $portalSession = $this->broker->issue($this->school, 'web', $this->user, 'session-abc');

    expect($portalSession->school_id)->toBe($this->school->id);
    expect($portalSession->guard)->toBe('web');
    expect($portalSession->authenticatable_type)->toBe($this->user->getMorphClass());
    expect($portalSession->authenticatable_id)->toBe($this->user->id);
    expect($portalSession->laravel_session_id)->toBe('session-abc');
    expect(strlen($portalSession->token))->toBeGreaterThanOrEqual(22);
    expect($portalSession->expires_at->isFuture())->toBeTrue();
    expect($portalSession->revoked_at)->toBeNull();
});

test('each issued token is unique', function () {
    $first = $this->broker->issue($this->school, 'web', $this->user, 'session-1');
    $second = $this->broker->issue($this->school, 'web', $this->user, 'session-2');

    expect($first->token)->not->toBe($second->token);
});

test('resolving a valid token for the matching guard and session succeeds', function () {
    $portalSession = $this->broker->issue($this->school, 'web', $this->user, 'session-abc');

    $resolved = $this->broker->resolve($portalSession->token, 'web', 'session-abc');

    expect($resolved)->not->toBeNull();
    expect($resolved->id)->toBe($portalSession->id);
});

test('resolving fails when the guard does not match', function () {
    $portalSession = $this->broker->issue($this->school, 'web', $this->user, 'session-abc');

    expect($this->broker->resolve($portalSession->token, 'student', 'session-abc'))->toBeNull();
});

test('resolving fails when the laravel session id does not match', function () {
    $portalSession = $this->broker->issue($this->school, 'web', $this->user, 'session-abc');

    expect($this->broker->resolve($portalSession->token, 'web', 'a-different-session'))->toBeNull();
});

test('resolving fails once the token has expired', function () {
    $portalSession = $this->broker->issue($this->school, 'web', $this->user, 'session-abc');
    $portalSession->update(['expires_at' => now()->subMinute()]);

    expect($this->broker->resolve($portalSession->token, 'web', 'session-abc'))->toBeNull();
});

test('resolving fails once the token has been revoked', function () {
    $portalSession = $this->broker->issue($this->school, 'web', $this->user, 'session-abc');

    $this->broker->revoke($portalSession->token);

    expect($this->broker->resolve($portalSession->token, 'web', 'session-abc'))->toBeNull();
    expect($portalSession->fresh()->revoked_at)->not->toBeNull();
});

test('revoking by session id revokes only that guard\'s session', function () {
    $webSession = $this->broker->issue($this->school, 'web', $this->user, 'shared-session');
    $studentSession = $this->broker->issue($this->school, 'student', $this->user, 'shared-session');

    $this->broker->revokeForSession('shared-session', 'web');

    expect($webSession->fresh()->revoked_at)->not->toBeNull();
    expect($studentSession->fresh()->revoked_at)->toBeNull();
});

test('resolving a valid token records last_used_at', function () {
    $portalSession = $this->broker->issue($this->school, 'web', $this->user, 'session-abc');
    expect($portalSession->last_used_at)->toBeNull();

    $this->broker->resolve($portalSession->token, 'web', 'session-abc');

    expect($portalSession->fresh()->last_used_at)->not->toBeNull();
});

test('resolving an unknown token returns null', function () {
    expect($this->broker->resolve('not-a-real-token', 'web', 'session-abc'))->toBeNull();
});
