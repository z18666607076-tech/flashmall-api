<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CommerceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\CheckoutOrderRequest;
use App\Http\Requests\Orders\PayOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentIntentResource;
use App\Models\Order;
use App\Models\User;
use App\Orders\CancelOrder;
use App\Orders\PlaceOrder;
use App\Orders\RefundOrder;
use App\Orders\TransitionOrder;
use App\Payments\PayOrder;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Group('Orders', weight: 4)]
#[Authorize('admin', only: ['ship', 'complete', 'refund'])]
class OrderController extends Controller
{
    #[HeaderParameter('Idempotency-Key', description: 'Replays the same checkout instead of creating a second order.', required: false, type: 'string')]
    #[Middleware('throttle:checkout')]
    public function store(CheckoutOrderRequest $request, PlaceOrder $place): JsonResponse
    {
        $key = $request->input('idempotency_key');
        $result = $place->execute($this->user($request), is_string($key) ? $key : null);

        return OrderResource::make($result->order)
            ->response()
            ->setStatusCode($result->replayed ? 200 : 201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->where('user_id', $this->user($request)->id)
            ->with('items')
            ->orderByDesc('id')
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        $this->visibleTo($request, $order);

        return OrderResource::make($order->load('items'));
    }

    public function cancel(Request $request, Order $order, CancelOrder $cancel): OrderResource
    {
        $this->visibleTo($request, $order);

        if ($order->user_id !== $this->user($request)->id) {
            throw new CommerceException('Resource not found.', 404);
        }

        return OrderResource::make($cancel->execute($order));
    }

    public function ship(Order $order, TransitionOrder $transitions): OrderResource
    {
        return OrderResource::make($transitions->ship($order));
    }

    public function complete(Order $order, TransitionOrder $transitions): OrderResource
    {
        return OrderResource::make($transitions->complete($order));
    }

    #[Middleware('throttle:checkout')]
    public function pay(PayOrderRequest $request, Order $order, PayOrder $pay): PaymentIntentResource
    {
        return PaymentIntentResource::make($pay->execute(
            $this->user($request),
            $order,
            $request->string('channel')->toString(),
        ));
    }

    public function refund(Order $order, RefundOrder $refunds): OrderResource
    {
        return OrderResource::make($refunds->execute($order));
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new CommerceException('Unauthenticated.', 401);
        }

        return $user;
    }

    private function visibleTo(Request $request, Order $order): void
    {
        $user = $this->user($request);

        if ($user->is_admin || $user->id === $order->user_id) {
            return;
        }

        throw new CommerceException('Resource not found.', 404);
    }
}
