<?php

use App\Enums\ReserveOutcome;
use App\FlashSales\FlashSaleInventory;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$saleId = (int) ($argv[1] ?? 0);
$userId = (int) ($argv[2] ?? 0);
$quantity = (int) ($argv[3] ?? 1);
$limit = (int) ($argv[4] ?? 1);

$result = app(FlashSaleInventory::class)->tryReserve($saleId, $userId, $quantity, $limit);

fwrite(STDOUT, $result->value);

exit($result === ReserveOutcome::Reserved ? 0 : 2);
