<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * @param mixed $actual
 * @param mixed $expected
 */
function assertSameHardening(mixed $actual, mixed $expected, string $message): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(
            $message
            . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function assertContainsHardening(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . "\nMissing: " . $needle . "\nIn: " . $haystack);
    }
}

$window = defined('LOGIN_RATE_LIMIT_WINDOW_SECONDS') ? LOGIN_RATE_LIMIT_WINDOW_SECONDS : 900;
$fixedNow = 1_700_000_000;

$allowed = evaluateLoginRateLimit($fixedNow, [], []);
assertSameHardening($allowed['allowed'], true, 'Fresh login attempts should be allowed.');
assertSameHardening($allowed['retry_after'], 0, 'Fresh login attempts should not have a retry delay.');

$ipBlocked = evaluateLoginRateLimit(
    $fixedNow,
    array_fill(0, defined('LOGIN_RATE_LIMIT_MAX_ATTEMPTS_PER_IP') ? LOGIN_RATE_LIMIT_MAX_ATTEMPTS_PER_IP : 15, $fixedNow - 10),
    []
);
assertSameHardening($ipBlocked['allowed'], false, 'The IP bucket should block after too many attempts.');
assertSameHardening($ipBlocked['scope'], 'ip', 'IP throttling should identify the blocking scope.');
assertSameHardening($ipBlocked['retry_after'], $window - 10, 'IP throttling should report the remaining lockout time.');

$emailBlocked = evaluateLoginRateLimit(
    $fixedNow,
    [],
    array_fill(0, defined('LOGIN_RATE_LIMIT_MAX_ATTEMPTS_PER_EMAIL_IP') ? LOGIN_RATE_LIMIT_MAX_ATTEMPTS_PER_EMAIL_IP : 6, $fixedNow - 25)
);
assertSameHardening($emailBlocked['allowed'], false, 'The email+IP bucket should block after too many attempts.');
assertSameHardening($emailBlocked['scope'], 'email_ip', 'Email+IP throttling should identify the blocking scope.');
assertSameHardening($emailBlocked['retry_after'], $window - 25, 'Email+IP throttling should report the remaining lockout time.');

$pruned = pruneLoginRateLimitAttempts([$fixedNow - $window - 1, $fixedNow - 60, $fixedNow], $fixedNow, $window);
assertSameHardening($pruned, [$fixedNow - 60, $fixedNow], 'Rate-limit pruning should discard expired attempts only.');

$cspHeader = buildContentSecurityPolicyHeader('nonce-test-value');
assertContainsHardening("default-src 'self'", $cspHeader, 'CSP should set a restrictive default source.');
assertContainsHardening("object-src 'none'", $cspHeader, 'CSP should disable plugin/object execution.');
assertContainsHardening("script-src 'self' 'nonce-nonce-test-value' https:", $cspHeader, 'CSP should require a nonce for inline scripts while allowing trusted HTTPS embeds.');
assertContainsHardening("style-src 'self' 'unsafe-inline' https://fonts.googleapis.com", $cspHeader, 'CSP should permit the existing stylesheet sources.');
assertContainsHardening("frame-ancestors 'self'", $cspHeader, 'CSP should prevent third-party framing.');

echo "production hardening regression checks passed\n";
