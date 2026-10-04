<?php

namespace App\FlashSales;

use App\Models\FlashSaleOrder;

class AbandonFlashSaleReservation
{
    public function __construct(private FlashSaleInventory $inventory) {}

    public function execute(int $reservationId): void
    {
        $reservation = FlashSaleOrder::query()->find($reservationId);

        if ($reservation === null || $reservation->order_id !== null) {
            return;
        }

        $this->inventory->release((int) $reservation->flash_sale_id, (int) $reservation->user_id);
        $reservation->delete();
    }
}
