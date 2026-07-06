<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case Created = 'created'; // заказ создан
    case Paid = 'paid'; // заказ оплачен
    case InProgress = 'in_progress'; // заказ готовится
    case Delivering = 'delivering'; // заказ в доставке
    case Completed = 'completed'; // заказ завершён
    case Cancelled = 'cancelled'; // заказ отменён
}
