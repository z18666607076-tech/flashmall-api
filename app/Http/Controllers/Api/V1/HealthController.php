<?php

namespace App\Http\Controllers\Api\V1;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('System', weight: 0)]
class HealthController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
        ]);
    }
}
