<?php declare(strict_types = 1);

/**
 * Test: Security\Authenticator
 */

use Contributte\Middlewares\Security\CompositeAuthenticator;
use Contributte\Middlewares\Security\DebugAuthenticator;
use Contributte\Psr7\Psr7ServerRequest;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

// Possitive identity
test(function (): void {
	$composite = new CompositeAuthenticator();
	$composite->addAuthenticator(new DebugAuthenticator('FOOBAR'));

	Assert::equal('FOOBAR', $composite->authenticate(Psr7ServerRequest::fromGlobals()));
});

// Negative identity
test(function (): void {
	$composite = new CompositeAuthenticator();

	Assert::false($composite->authenticate(Psr7ServerRequest::fromGlobals()));
});
