<?php

use App\Exceptions\CommerceException;
use App\Models\User;
use App\Orders\PlaceOrder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$userId = (int) ($argv[1] ?? 0);
$user = User::query()->find($userId);

if ($user === null) {
    fwrite(STDERR, "missing user {$userId}\n");
    exit(1);
}

try {
    app(PlaceOrder::class)->execute($user, 'race-'.$userId);
} catch (CommerceException $exception) {
    fwrite(STDOUT, $exception->getMessage());
    exit(2);
}

exit(0);
