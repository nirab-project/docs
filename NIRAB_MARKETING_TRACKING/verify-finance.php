<?php
// Standalone policy checks: no Laravel boot, database, migrations or HTTP.
require __DIR__ . '/../../Admin Panel/app/Services/Marketing/FinancialEventPolicy.php';
use App\Services\Marketing\FinancialEventPolicy as Policy;
$checks = 0;
function check(bool $ok, string $name): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($name); }
check(!Policy::fullyCollected(false, 115, 0, false, 'cash_after_service'), 'Unpaid cash');
check(!Policy::fullyCollected(true, 115, 0, false, 'cash_after_service'), 'Paid flag without receipts');
check(!Policy::fullyCollected(true, 115, 40, false, 'wallet_payment'), 'Partial wallet');
check(Policy::fullyCollected(true, 115, 115, false, 'tap'), 'Wallet plus captured gateway');
check(!Policy::fullyCollected(true, 115, 115, true, 'tap'), 'Warranty');
check(!Policy::fullyCollected(true, 115, 115, false, 'prepaid_visit'), 'Package consumption');
check(!Policy::fullyCollected(true, 0, 0, false, 'wallet_payment'), 'No zero-value purchase');
check(!Policy::fullyCollected(true, NAN, 115, false, 'tap'), 'Invalid money');
$shares = Policy::allocate(80, ['wallet_only' => 0, 'a' => 30, 'b' => 50]);
check(!isset($shares['wallet_only']), 'Exclude wallet-only group');
check($shares['a'] === 30.0 && $shares['b'] === 50.0, 'Split allocation');
$shares = Policy::allocate(10, ['a' => 1, 'b' => 1, 'c' => 1]);
check(abs(array_sum($shares) - 10) < .0001, 'Conserve rounding remainder');
check(Policy::allocate(80, ['a' => 0]) === [], 'No unsupported allocation');
echo "PASS: {$checks} financial policy assertions; no services used.\n";
