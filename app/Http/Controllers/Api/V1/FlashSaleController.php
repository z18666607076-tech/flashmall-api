<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FlashSaleStatus;
use App\Exceptions\CommerceException;
use App\FlashSales\CloseFlashSale;
use App\FlashSales\CreateFlashSale;
use App\FlashSales\PurchaseFlashSale;
use App\Http\Controllers\Controller;
use App\Http\Requests\FlashSales\PurchaseFlashSaleRequest;
use App\Http\Requests\FlashSales\StoreFlashSaleRequest;
use App\Http\Resources\FlashSaleOrderResource;
use App\Http\Resources\FlashSaleResource;
use App\Models\FlashSale;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Carbon;

#[Group('Flash sales', weight: 5)]
#[Authorize('admin', only: ['store', 'cancel'])]
class FlashSaleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $sales = FlashSale::query()
            ->with('sku.product')
            ->whereIn('status', [FlashSaleStatus::Scheduled, FlashSaleStatus::Active])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        return FlashSaleResource::collection($sales);
    }

    public function show(FlashSale $flashSale): FlashSaleResource
    {
        return FlashSaleResource::make($flashSale->load('sku.product'));
    }

    public function store(StoreFlashSaleRequest $request, CreateFlashSale $create): JsonResponse
    {
        $sale = $create->execute(
            $request->integer('sku_id'),
            $request->string('title')->toString(),
            $request->integer('price_cents'),
            Carbon::parse($request->string('starts_at')->toString()),
            Carbon::parse($request->string('ends_at')->toString()),
            $request->integer('total_stock'),
            $request->integer('per_user_limit'),
        );

        return FlashSaleResource::make($sale)
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(FlashSale $flashSale, CloseFlashSale $close): FlashSaleResource
    {
        return FlashSaleResource::make($close->execute($flashSale, FlashSaleStatus::Cancelled));
    }

    #[Middleware('throttle:flash-purchase')]
    public function purchase(PurchaseFlashSaleRequest $request, FlashSale $flashSale, PurchaseFlashSale $purchase): JsonResponse
    {
        $reservation = $purchase->execute(
            $this->user($request),
            $flashSale,
            $request->integer('quantity'),
        );

        return FlashSaleOrderResource::make($reservation)
            ->response()
            ->setStatusCode(202);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new CommerceException('Unauthenticated.', 401);
        }

        return $user;
    }
}
