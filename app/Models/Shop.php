<?php

namespace App\Models;

use App\Enums\ShopType;
use App\Jobs\DeliverLegalTextVersion;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read User $user
 */
#[Fillable(['name', 'type', 'endpoint_url', 'secret'])]
#[Hidden(['secret'])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function deliver(LegalTextVersion $version): void
    {
        $delivery = $this->deliveries()->createOrFirst([
            'legal_text_version_id' => $version->id,
        ]);

        if ($delivery->wasRecentlyCreated) {
            DeliverLegalTextVersion::dispatch($delivery);
        }
    }

    public function deliverCurrentLegalTexts(): void
    {
        foreach ($this->user->legalTexts()->with('currentVersion')->get() as $legalText) {
            if ($legalText->currentVersion !== null) {
                $this->deliver($legalText->currentVersion);
            }
        }
    }

    public function usesMockEndpoint(): bool
    {
        return $this->exists && $this->endpoint_url === route('mock-shop', $this);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ShopType::class,
            'secret' => 'encrypted',
        ];
    }
}
