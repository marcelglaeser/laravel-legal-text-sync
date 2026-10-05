<?php

namespace App\Models;

use Database\Factories\MerchantProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read User $user
 */
#[Fillable(['company_name', 'representative', 'street', 'postal_code', 'city', 'email', 'vat_id', 'requires_approval'])]
class MerchantProfile extends Model
{
    /** @use HasFactory<MerchantProfileFactory> */
    use HasFactory;

    public const PLACEHOLDERS = ['company_name', 'representative', 'street', 'postal_code', 'city', 'email', 'vat_id'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function placeholderTag(string $field): string
    {
        return '{{ '.$field.' }}';
    }

    /**
     * @return array<string, string>
     */
    public function placeholders(): array
    {
        return collect(self::PLACEHOLDERS)
            ->mapWithKeys(fn (string $field) => [self::placeholderTag($field) => (string) $this->getAttribute($field)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
        ];
    }
}
