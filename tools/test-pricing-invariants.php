<?php
function effectiveRate(int $base, float $customer, float $project): int {
    return (int) round($base * $customer * $project);
}
function amount(int $rate, int $seconds): int {
    return (int) round($rate * ($seconds / 3600));
}

assert(effectiveRate(400000, 1.10, 1.20) === 528000);
assert(amount(528000, 1200) === 176000);

// Two legitimate 20-minute concurrent activities remain two billable line items.
assert(amount(400000, 1200) + amount(300000, 1200) === 233333);

echo "Pricing invariants PASS\n";
