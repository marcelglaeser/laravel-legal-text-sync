<?php

namespace App\Jobs;

use App\Enums\DeliveryStatus;
use App\Exceptions\UnsafeEndpointException;
use App\Models\Delivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DeliverLegalTextVersion implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Delivery $delivery) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(): void
    {
        if ($this->delivery->status === DeliveryStatus::Delivered) {
            return;
        }

        $shop = $this->delivery->shop;
        $adapter = app($shop->type->adapter());

        $this->delivery->markAsAttempted();

        try {
            $adapter->deliver($shop, $this->delivery);
        } catch (UnsafeEndpointException $exception) {
            $this->fail($exception);

            return;
        } catch (Throwable $exception) {
            $this->delivery->recordError($exception->getMessage());

            throw $exception;
        }

        $this->delivery->markAsDelivered();
    }

    public function failed(?Throwable $exception): void
    {
        $this->delivery->markAsFailed($exception?->getMessage() ?? 'Unknown error');
    }
}
