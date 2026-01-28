<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Contracts;

/**
 * Interface for order repository operations
 *
 * Provides a standardized way to query and manage order records that implement
 * the Payable interface, regardless of the underlying model implementation.
 *
 * Example implementation:
 * ```php
 * use OfficeGuy\LaravelSumitGateway\Contracts\OrderRepository;
 * use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
 *
 * class EloquentOrderRepository implements OrderRepository
 * {
 *     public function findById(string|int $orderId): ?Payable
 *     {
 *         return Order::find($orderId);
 *     }
 *     // ... other methods
 * }
 * ```
 */
interface OrderRepository
{
    /**
     * Find an order by its unique identifier
     *
     * @param  string|int  $orderId  The order ID
     * @return Payable|null The order model or null if not found
     */
    public function findById(string | int $orderId): ?Payable;

    /**
     * Find an order by its order key
     *
     * The order key is a unique security token used for webhook validation
     * and secure access to order details without authentication.
     *
     * @param  string  $orderKey  The unique order security key
     * @return Payable|null The order model or null if not found
     */
    public function findByOrderKey(string $orderKey): ?Payable;

    /**
     * Create a new order record
     *
     * @param  array<string, mixed>  $data  Order data
     * @return Payable The created order model
     */
    public function create(array $data): Payable;

    /**
     * Update an existing order record
     *
     * @param  Payable  $order  The order to update
     * @param  array<string, mixed>  $data  Updated order data
     * @return Payable The updated order model
     */
    public function update(Payable $order, array $data): Payable;
}
