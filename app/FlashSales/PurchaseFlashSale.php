<?php

namespace App\FlashSales;

use App\Enums\FlashSaleStatus;
use App\Enums\ReserveOutcome;
use App\Exceptions\CommerceException;
use App\Jobs\CreateFlashSaleOrder;
use App\Models\FlashSale;
use App\Models\FlashSaleOrder;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

class PurchaseFlashSale
{
    public function __construct(private FlashSaleInventory $inventory) {}

    public function execute(User $user, FlashSale $sale, int $quantity): FlashSaleOrder
    {
        $sale->refresh();

        if (! $sale->isOpen()) {
            throw new CommerceException('This flash sale is not open.', 409);
        }

        if ($quantity > $sale->per_user_limit) {
            throw new CommerceException('Quantity exceeds the per-user limit.');
        }

        $outcome = $this->inventory->tryReserve((int) $sale->id, (int) $user->id, $quantity, $sale->per_user_limit);

        if ($outcome !== ReserveOutcome::Reserved) {
            throw new CommerceException($this->message($outcome), $outcome === ReserveOutcome::OverLimit ? 422 : 409);
        }

        try {
            $reservation = FlashSaleOrder::query()->create([
                'flash_sale_id' => $sale->id,
                'user_id' => $user->id,
                'quantity' => $quantity,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->inventory->release((int) $sale->id, (int) $user->id);

            throw new CommerceException('You have already joined this flash sale.', 409);
        } catch (Throwable $exception) {
            $this->inventory->release((int) $sale->id, (int) $user->id);

            throw $exception;
        }

        if ($sale->status === FlashSaleStatus::Scheduled) {
            $sale->status = FlashSaleStatus::Active;
            $sale->save();
        }

        CreateFlashSaleOrder::dispatch((int) $reservation->id);

        return $reservation->refresh();
    }

    private function message(ReserveOutcome $outcome): string
    {
        return match ($outcome) {
            ReserveOutcome::DuplicateUser => 'You have already joined this flash sale.',
            ReserveOutcome::InsufficientStock => 'This flash sale is sold out.',
            ReserveOutcome::OverLimit => 'Quantity exceeds the per-user limit.',
            ReserveOutcome::NotSeeded => 'This flash sale is not open.',
            ReserveOutcome::Reserved => 'Reserved.',
        };
    }
}
