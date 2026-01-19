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
     * @param string|int $orderId The order ID
     * @return Payable|null The order model or null if not found
     */
    public function findById(string|int $orderId): ?Payable;

    /**
     * Find an order by its order key
     *
     * The order key is a unique security token used for webhook validation
     * and secure access to order details without authentication.
     *
     * @param string $orderKey The unique order security key
     * @return Payable|null The order model or null if not found
     */
    public function findByOrderKey(string $orderKey): ?Payable;

    /**
     * Find an order by its SUMIT transaction ID
     *
     * @param string|int $transactionId The SUMIT transaction ID
     * @return Payable|null The order model or null if not found
     */
    public function findByTransactionId(string|int $transactionId): ?Payable;

    /**
     * Find orders by customer ID
     *
     * Returns all orders associated with a specific customer.
     *
     * @param string|int $customerId The customer's ID
     * @return array<int, Payable> Array of order models
     */
    public function findByCustomerId(string|int $customerId): array;

    /**
     * Find pending orders (unpaid/processing)
     *
     * Returns orders that are awaiting payment or are currently being processed.
     *
     * @param string|int|null $customerId Optional customer ID to filter by
     * @return array<int, Payable> Array of pending order models
     */
    public function findPending(string|int|null $customerId = null): array;

    /**
     * Find completed orders (paid/fulfilled)
     *
     * Returns orders that have been successfully paid and/or fulfilled.
     *
     * @param string|int|null $customerId Optional customer ID to filter by
     * @return array<int, Payable> Array of completed order models
     */
    public function findCompleted(string|int|null $customerId = null): array;

    /**
     * Create a new order record
     *
     * @param array<string, mixed> $data Order data
     * @return Payable The created order model
     */
    public function create(array $data): Payable;

    /**
     * Update an existing order record
     *
     * @param Payable $order The order to update
     * @param array<string, mixed> $data Updated order data
     * @return Payable The updated order model
     */
    public function update(Payable $order, array $data): Payable;

    /**
     * Delete an order record
     *
     * @param Payable $order The order to delete
     * @return bool True if deletion was successful, false otherwise
     */
    public function delete(Payable $order): bool;

    /**
     * Mark an order as paid
     *
     * Updates the order status to reflect successful payment.
     *
     * @param Payable $order The order to mark as paid
     * @param string|int $transactionId The SUMIT transaction ID
     * @return Payable The updated order model
     */
    public function markAsPaid(Payable $order, string|int $transactionId): Payable;

    /**
     * Mark an order as failed
     *
     * Updates the order status to reflect failed payment.
     *
     * @param Payable $order The order to mark as failed
     * @param string|null $reason Optional failure reason
     * @return Payable The updated order model
     */
    public function markAsFailed(Payable $order, ?string $reason = null): Payable;
}
