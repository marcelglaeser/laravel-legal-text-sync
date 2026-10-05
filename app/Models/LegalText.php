<?php

namespace App\Models;

use App\Enums\LegalTextType;
use Database\Factories\LegalTextFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read User $user
 */
#[Fillable(['type'])]
class LegalText extends Model
{
    /** @use HasFactory<LegalTextFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<LegalTextVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(LegalTextVersion::class);
    }

    /**
     * @return HasOne<LegalTextVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(LegalTextVersion::class)->ofMany('version');
    }

    /**
     * @return HasOne<LegalTextVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(LegalTextVersion::class)->ofMany(
            ['published_at' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->whereNotNull('published_at'),
        );
    }

    public function createVersion(string $content, LegalTemplateVersion $template): LegalTextVersion
    {
        $latest = $this->latestVersion()->first();

        if ($latest?->content === $content) {
            return $latest;
        }

        $version = $this->versions()->create([
            'legal_template_version_id' => $template->id,
            'version' => ($latest->version ?? 0) + 1,
            'content' => $content,
        ]);

        $this->unsetRelation('latestVersion');

        return $version;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LegalTextType::class,
        ];
    }
}
