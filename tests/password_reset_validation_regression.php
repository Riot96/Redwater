<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * @param mixed $actual
 * @param mixed $expected
 */
function assertSameValue(mixed $actual, mixed $expected, string $message): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(
            $message
            . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function assertTrueValue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function buildPrefixedResetToken(int $issuedAt, string $suffixChar = 'a'): string
{
    return PASSWORD_RESET_TOKEN_PREFIX
        . sprintf('%0' . PASSWORD_RESET_TOKEN_TIMESTAMP_DIGITS . 'd', $issuedAt)
        . str_repeat($suffixChar, PASSWORD_RESET_TOKEN_RANDOM_BYTES * 2);
}

/**
 * @param array{id:int,email:string,display_name:string,reset_token_is_valid:int} $record
 */
function assertEvaluation(
    array $record,
    string $token,
    int $now,
    ?string $expectedReason,
    bool $shouldReturnUser,
    string $message
): void {
    $evaluation = evaluatePasswordResetTokenRecord($record, $token, $now);

    assertSameValue($evaluation['reason'], $expectedReason, $message . ' reason mismatch.');
    assertSameValue($evaluation['user'] !== null, $shouldReturnUser, $message . ' user presence mismatch.');
}

$fixedNow = 1_700_000_000;
$baseRecord = [
    'id' => 42,
    'email' => 'tester@example.com',
    'display_name' => 'Tester',
    'reset_token_is_valid' => 1,
];
$prefixedToken = buildPrefixedResetToken($fixedNow - 60, 'a');

assertTrueValue(function_exists('passwordResetTokenStorageValue'), 'Password reset token storage helper should exist.');
if (function_exists('passwordResetTokenStorageValue')) {
    $storedTokenValue = passwordResetTokenStorageValue($prefixedToken);
    assertSameValue(
        $storedTokenValue,
        hash('sha256', $prefixedToken),
        'Password reset tokens should be stored as SHA-256 hashes.'
    );
    assertTrueValue(
        $storedTokenValue !== $prefixedToken,
        'Password reset tokens must not be stored in plaintext.'
    );
}

assertTrueValue(function_exists('passwordResetTokenLookupCandidates'), 'Password reset token lookup helper should exist.');
if (function_exists('passwordResetTokenLookupCandidates')) {
    assertSameValue(
        passwordResetTokenLookupCandidates($prefixedToken),
        [hash('sha256', $prefixedToken), $prefixedToken],
        'Password reset token lookup should try the hashed token first and retain a raw-token fallback for legacy compatibility.'
    );
}

assertTrueValue(defined('PASSWORD_RESET_RATE_LIMIT_WINDOW_SECONDS'), 'Password reset request rate limit window constant should exist.');
assertTrueValue(defined('PASSWORD_RESET_RATE_LIMIT_MAX_ATTEMPTS_PER_IP'), 'Password reset request per-IP limit constant should exist.');
assertTrueValue(defined('PASSWORD_RESET_RATE_LIMIT_MAX_ATTEMPTS_PER_EMAIL_IP'), 'Password reset request per-email+IP limit constant should exist.');
assertTrueValue(function_exists('evaluatePasswordResetRateLimit'), 'Password reset request rate limit evaluator should exist.');
if (
    function_exists('evaluatePasswordResetRateLimit')
    && defined('PASSWORD_RESET_RATE_LIMIT_MAX_ATTEMPTS_PER_IP')
    && defined('PASSWORD_RESET_RATE_LIMIT_MAX_ATTEMPTS_PER_EMAIL_IP')
) {
    $allowedResetStatus = evaluatePasswordResetRateLimit($fixedNow, [$fixedNow - 60], [$fixedNow - 30]);
    assertSameValue($allowedResetStatus['allowed'], true, 'Password reset requests below the threshold should be allowed.');
    assertSameValue($allowedResetStatus['scope'], 'none', 'Password reset requests below the threshold should not report a blocking scope.');

    $ipLimitedStatus = evaluatePasswordResetRateLimit(
        $fixedNow,
        array_fill(0, PASSWORD_RESET_RATE_LIMIT_MAX_ATTEMPTS_PER_IP, $fixedNow - 10),
        []
    );
    assertSameValue($ipLimitedStatus['allowed'], false, 'Password reset requests should be throttled after too many requests from one IP.');
    assertSameValue($ipLimitedStatus['scope'], 'ip', 'Password reset IP throttling should report the IP scope.');

    $emailIpLimitedStatus = evaluatePasswordResetRateLimit(
        $fixedNow,
        [],
        array_fill(0, PASSWORD_RESET_RATE_LIMIT_MAX_ATTEMPTS_PER_EMAIL_IP, $fixedNow - 10)
    );
    assertSameValue($emailIpLimitedStatus['allowed'], false, 'Password reset requests should be throttled after too many requests for one email+IP pair.');
    assertSameValue($emailIpLimitedStatus['scope'], 'email_ip', 'Password reset email+IP throttling should report the email_ip scope.');
}

assertEvaluation(
    $baseRecord,
    $prefixedToken,
    $fixedNow,
    null,
    true,
    'Fresh prefixed token should validate immediately.'
);

assertEvaluation(
    $baseRecord,
    buildPrefixedResetToken($fixedNow - PASSWORD_RESET_TOKEN_LIFETIME - 1, 'b'),
    $fixedNow,
    'prefixed_expired',
    false,
    'Expired prefixed token should be rejected without DB fallback.'
);

$originalTimezone = date_default_timezone_get();

date_default_timezone_set('Europe/Berlin');
assertEvaluation(
    $baseRecord,
    'legacy-reset-token',
    $fixedNow,
    null,
    true,
    'Legacy token should remain valid when the database expiry check says it is current.'
);

assertEvaluation(
    array_merge($baseRecord, ['reset_token_is_valid' => 0]),
    'legacy-reset-token',
    $fixedNow,
    'db_expired',
    false,
    'Legacy token should be rejected when the database expiry check says it is expired.'
);

foreach (['UTC', 'America/Los_Angeles', 'Europe/Berlin'] as $timezone) {
    date_default_timezone_set($timezone);

    $freshEvaluation = evaluatePasswordResetTokenRecord(
        $baseRecord,
        buildPrefixedResetToken($fixedNow - 60, 'c'),
        $fixedNow
    );
    assertSameValue($freshEvaluation['reason'], null, 'Fresh prefixed token should stay valid in ' . $timezone . '.');
    assertTrueValue($freshEvaluation['user'] !== null, 'Fresh prefixed token should return a user in ' . $timezone . '.');

    $legacyEvaluation = evaluatePasswordResetTokenRecord(
        $baseRecord,
        'legacy-reset-token',
        $fixedNow
    );
    assertSameValue($legacyEvaluation['reason'], null, 'Legacy token should stay valid in ' . $timezone . '.');
    assertTrueValue($legacyEvaluation['user'] !== null, 'Legacy token should return a user in ' . $timezone . '.');
}

date_default_timezone_set($originalTimezone);

echo "password reset validation regression checks passed\n";
