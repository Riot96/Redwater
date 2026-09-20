<?php

$ticketsSource = file_get_contents(__DIR__ . '/../tickets.php');
if (!is_string($ticketsSource)) {
    throw new RuntimeException('Unable to read tickets.php for banner regression checks.');
}

if (!str_contains($ticketsSource, 'Get Your <span style="color:var(--red)">Tickets</span>')) {
    throw new RuntimeException('Tickets page should keep the "Get Your Tickets" heading.');
}

if (str_contains($ticketsSource, 'Secure your spot at the Red Water Haunted Homestead and all upcoming RedWater Entertainment events.')) {
    throw new RuntimeException('Tickets page should no longer render the removed ticketing promo copy.');
}

if (str_contains($ticketsSource, '<h2>HauntPay Events</h2>')) {
    throw new RuntimeException('Tickets page should no longer render the "HauntPay Events" heading.');
}

if (str_contains($ticketsSource, 'Browse the live HauntPay event feed for current ticket availability.')) {
    throw new RuntimeException('Tickets page should no longer render the removed HauntPay feed description.');
}

if (substr_count($ticketsSource, '/assets/images/hollyrom-banner.svg') !== 2) {
    throw new RuntimeException('Tickets page should render the homepage Hollyrom banner image in both replacement locations.');
}

if (!str_contains($ticketsSource, '<?= $embedCode /* Admin-controlled, stored securely in DB */ ?>')) {
    throw new RuntimeException('Tickets page should keep the HauntPay embed output intact.');
}

echo "tickets page banner regression checks passed\n";
