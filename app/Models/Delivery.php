<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Jobs\DeliverLegalTextVersion;
use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Shop $shop
 * @property-read LegalTextVersion $legalTextVersion
 */
#[Fillable(['legal_text_version_id', 'status', 'attempts', 'last_error', 'delivered_at'])]
class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => DeliveryStatus::Pending,
        'attempts' => 0,
    ];

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<LegalTextVersion, $this>
     */
    public function legalTextVersion(): BelongsTo
    {
        return $this->belongsTo(LegalTextVersion::class);
    }

    public function markAsAttempted(): void
    {
        $this->increment('attempts');
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => DeliveryStatus::Delivered,
            'last_error' => null,
            'delivered_at' => now(),
        ]);
    }

    public function recordError(string $error): void
    {
        $this->update(['last_error' => $error]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => DeliveryStatus::Failed,
            'last_error' => $error,
        ]);
    }

    public function retry(): void
    {
        if ($this->status !== DeliveryStatus::Failed) {
            return;
        }

        $this->update([
            'status' => DeliveryStatus::Pending,
            'last_error' => null,
        ]);

        DeliverLegalTextVersion::dispatch($this);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'attempts' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }
}
