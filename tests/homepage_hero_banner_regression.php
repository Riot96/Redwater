<?php

$homepageSource = file_get_contents(__DIR__ . '/../index.php');
if (!is_string($homepageSource)) {
    throw new RuntimeException('Unable to read index.php for homepage hero banner checks.');
}

$bannerAssetPath = __DIR__ . '/../assets/images/hollyrom-banner.svg';
$bannerAssetSource = file_get_contents($bannerAssetPath);
if (!is_string($bannerAssetSource)) {
    throw new RuntimeException('Unable to read the Hollyrom hero banner asset.');
}

if (!str_contains($homepageSource, '/assets/images/hollyrom-banner.svg')) {
    throw new RuntimeException('Homepage hero should reference the Hollyrom banner image asset.');
}

if (str_contains($homepageSource, 'Experience the Fear')) {
    throw new RuntimeException('Homepage hero should no longer render the old "Experience the Fear" heading.');
}

if (!str_contains($bannerAssetSource, '>Hollyrom</text>')) {
    throw new RuntimeException('The Hollyrom banner asset should render the word Hollyrom.');
}

echo "homepage hero banner regression checks passed\n";
