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

assertEvaluation(
    $baseRecord,
    buildPrefixedResetToken($fixedNow - 60, 'a'),
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
