<?php

use App\Enums\OrderStatus;

it('allows payment, fulfilment, and refund transitions', function () {
    expect(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Paid))->toBeTrue()
        ->and(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Cancelled))->toBeTrue()
        ->and(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Shipped))->toBeFalse()
        ->and(OrderStatus::Paid->canTransitionTo(OrderStatus::Shipped))->toBeTrue()
        ->and(OrderStatus::Paid->canTransitionTo(OrderStatus::Refunded))->toBeTrue()
        ->and(OrderStatus::Shipped->canTransitionTo(OrderStatus::Completed))->toBeTrue()
        ->and(OrderStatus::Completed->allowed())->toBe([])
        ->and(OrderStatus::Refunded->allowed())->toBe([]);
});
