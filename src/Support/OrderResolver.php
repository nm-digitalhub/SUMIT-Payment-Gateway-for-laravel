<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;

class OrderResolver
{
    /**
     * Resolve a Payable order by ID using configured resolver/model.
     */
    public static function resolve(string|int $orderId): ?Payable
    {
        // Custom resolver callable
        if ($callable = config('officeguy.order.resolver')) {
            if (is_callable($callable)) {
                $resolved = call_user_func($callable, $orderId);
                if ($resolved instanceof Payable) {
                    return $resolved;
                }
            }
        }

        // Fallback to model class
        $modelClass = config('officeguy.order.model');
        if ($modelClass && class_exists($modelClass)) {
            $model = $modelClass::find($orderId);
            if ($model instanceof Payable) {
                return $model;
            }
        }

        return null;
    }
}
