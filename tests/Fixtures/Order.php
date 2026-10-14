<?php

namespace Mgamalhz\Foundation\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Mgamalhz\Audit\Concerns\HasAuditTrail;
use Mgamalhz\Lifecycle\Concerns\HasLifecycle;
use Mgamalhz\Lifecycle\StateMachine;

class Order extends Model
{
    use HasAuditTrail;
    use HasLifecycle;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => OrderStatus::class];
    }

    public static function stateMachine(): StateMachine
    {
        return StateMachine::for('status')
            ->initial(OrderStatus::Pending)
            ->allow(OrderStatus::Pending, [OrderStatus::Paid, OrderStatus::Cancelled])
            ->allow(OrderStatus::Paid, [OrderStatus::Shipped, OrderStatus::Refunded])
            ->allow(OrderStatus::Shipped, [OrderStatus::Delivered])
            ->allow(OrderStatus::Delivered, [OrderStatus::Refunded])
            ->guard(OrderStatus::Refunded, fn (Order $order) => $order->total > 0 ?: 'Nothing was charged, so there is nothing to refund.');
    }
}
