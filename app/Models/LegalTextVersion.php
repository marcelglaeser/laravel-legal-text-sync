<?php

namespace App\Models;

use App\Events\LegalTextVersionPublished;
use Database\Factories\LegalTextVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read LegalText $legalText
 */
#[Fillable(['version', 'content'])]
class LegalTextVersion extends Model
{
    /** @use HasFactory<LegalTextVersionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<LegalText, $this>
     */
    public function legalText(): BelongsTo
    {
        return $this->belongsTo(LegalText::class);
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function publish(): bool
    {
        $published = static::query()
            ->whereKey($this->getKey())
            ->whereNull('published_at')
            ->update(['published_at' => now()]);

        if ($published === 0) {
            return false;
        }

        $this->refresh();

        LegalTextVersionPublished::dispatch($this);

        return true;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
