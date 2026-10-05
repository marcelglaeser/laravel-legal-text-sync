<?php

namespace App\Models;

use App\Enums\LegalTextType;
use App\Events\LegalTemplateVersionPublished;
use Database\Factories\LegalTemplateVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'version', 'content'])]
class LegalTemplateVersion extends Model
{
    /** @use HasFactory<LegalTemplateVersionFactory> */
    use HasFactory;

    public static function current(LegalTextType $type): ?self
    {
        return static::query()
            ->where('type', $type)
            ->published()
            ->latest('published_at')
            ->latest('id')
            ->first();
    }

    public static function latestFor(LegalTextType $type): ?self
    {
        return static::query()->where('type', $type)->latest('version')->first();
    }

    public static function draft(LegalTextType $type, string $content): self
    {
        $latest = static::latestFor($type);

        if ($latest?->content === $content) {
            return $latest;
        }

        return static::create([
            'type' => $type,
            'version' => ($latest->version ?? 0) + 1,
            'content' => $content,
        ]);
    }

    /**
     * @return HasMany<LegalTextVersion, $this>
     */
    public function legalTextVersions(): HasMany
    {
        return $this->hasMany(LegalTextVersion::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function isLatest(): bool
    {
        return static::latestFor($this->type)?->is($this) ?? false;
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

        LegalTemplateVersionPublished::dispatch($this);

        return true;
    }

    public function render(MerchantProfile $profile): string
    {
        return strtr($this->content, $profile->placeholders());
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
            'type' => LegalTextType::class,
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
