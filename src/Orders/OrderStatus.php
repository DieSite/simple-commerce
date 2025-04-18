<?php

namespace DuncanMcClean\SimpleCommerce\Orders;

enum OrderStatus: string
{
    case Cart = 'cart';
    case Placed = 'placed';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partiallyrefunded';


    public function is($orderStatus): bool
    {
        if (! is_string($orderStatus)) {
            $orderStatus = $orderStatus->value;
        }

        return $this->value === $orderStatus;
    }
}
