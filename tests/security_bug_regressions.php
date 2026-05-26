<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * @param mixed $actual
 * @param mixed $expected
 */
function assertSameRegression(mixed $actual, mixed $expected, string $message): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(
            $message
            . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function assertTrueRegression(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$managedPolicyPath = '/uploads/policies/security-test-image.jpg';
$managedPolicyDiskPath = __DIR__ . '/../uploads/policies/security-test-image.jpg';
$managedGalleryPath = '/uploads/gallery/security-test-image.jpg';
$managedGalleryDiskPath = __DIR__ . '/../uploads/gallery/security-test-image.jpg';
file_put_contents($managedPolicyDiskPath, 'policy-image-test');
file_put_contents($managedGalleryDiskPath, 'gallery-image-test');

try {
    assertSameRegression(
        normalizeInternalRedirectTarget('/member/gallery.php?edit=4#details'),
        '/member/gallery.php?edit=4#details',
        'Valid internal redirects should be preserved.'
    );
    assertSameRegression(
        normalizeInternalRedirectTarget('//evil.example/phish'),
        '',
        'Protocol-relative redirect targets must be rejected.'
    );
    assertSameRegression(
        normalizeInternalRedirectTarget('/\\evil.example/phish'),
        '',
        'Backslash-based redirect targets must be rejected.'
    );
    assertSameRegression(
        normalizeInternalRedirectTarget("/admin/\r\nX-Test: injected"),
        '',
        'Redirect targets with control characters must be rejected.'
    );

    assertSameRegression(
        normalizeLocalPolicyImagePath($managedPolicyPath),
        $managedPolicyPath,
        'Managed policy image paths should be preserved.'
    );
    assertSameRegression(
        normalizeLocalPolicyImagePath('../secrets.txt'),
        '',
        'Policy image path traversal attempts must be rejected.'
    );
    assertSameRegression(
        normalizeLocalPolicyImagePath('/uploads/policies/policy.php'),
        '',
        'Non-image policy upload paths must be rejected.'
    );

    $resolvedPolicyPath = policyImageAbsolutePath($managedPolicyPath);
    assertTrueRegression($resolvedPolicyPath !== '', 'Managed policy images should resolve to an absolute disk path.');
    assertSameRegression(
        str_replace('\\', '/', (string) $resolvedPolicyPath),
        str_replace('\\', '/', realpath($managedPolicyDiskPath) ?: ''),
        'Managed policy image paths should resolve to the expected file on disk.'
    );
    assertTrueRegression(policyImageExists($managedPolicyPath), 'Existing managed policy images should be detected.');
    assertSameRegression(
        policyImageAbsolutePath('/uploads/policies/missing-file.jpg'),
        '',
        'Missing managed policy images should not resolve to a usable disk path.'
    );

    assertSameRegression(
        normalizeLocalGalleryUploadPath($managedGalleryPath),
        $managedGalleryPath,
        'Managed gallery upload paths should be preserved.'
    );
    assertSameRegression(
        normalizeLocalGalleryUploadPath('/uploads/gallery/security-test-image.php'),
        '',
        'Gallery upload helper should reject non-media extensions.'
    );
    $resolvedGalleryPath = galleryUploadAbsolutePath($managedGalleryPath);
    assertTrueRegression($resolvedGalleryPath !== '', 'Managed gallery uploads should resolve to an absolute disk path.');
    assertSameRegression(
        str_replace('\\', '/', (string) $resolvedGalleryPath),
        str_replace('\\', '/', realpath($managedGalleryDiskPath) ?: ''),
        'Managed gallery upload paths should resolve to the expected file on disk.'
    );

    assertSameRegression(
        uploadExtensionForMimeType('image/jpeg', 'avatar.php'),
        'jpg',
        'JPEG uploads should be normalized to a safe jpg extension.'
    );
    assertSameRegression(
        uploadExtensionForMimeType('video/webm', 'clip.mov'),
        'webm',
        'Known video MIME types should keep a matching safe extension.'
    );
    assertSameRegression(
        uploadExtensionForMimeType('application/octet-stream', 'poster.final.PNG'),
        'png',
        'Unknown MIME types should fall back to a sanitized original image extension.'
    );
    assertSameRegression(
        uploadExtensionForMimeType('application/octet-stream', 'no-extension'),
        'bin',
        'Uploads without a safe extension should fall back to a neutral binary extension.'
    );
} finally {
    if (is_file($managedPolicyDiskPath)) {
        unlink($managedPolicyDiskPath);
    }
    if (is_file($managedGalleryDiskPath)) {
        unlink($managedGalleryDiskPath);
    }
}

echo "security and bug regression checks passed\n";
