<?php

namespace App\Domain\Accounts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationPreference extends Model
{
    use HasFactory;

    /** @var array<string, array{label: string, description: string}> */
    public const CATEGORIES = [
        'group_news' => [
            'label' => 'Newsletters and group news',
            'description' => 'Occasional news and updates from the group.',
        ],
        'photo_moderation_outcomes' => [
            'label' => 'Photo moderation outcomes',
            'description' => 'Updates about photos you submit when photo sharing is available.',
        ],
        'membership_communications' => [
            'label' => 'Membership-related communication',
            'description' => 'Optional information about the group’s external membership arrangements.',
        ],
    ];

    /** @var list<string> */
    protected $fillable = ['category', 'is_subscribed', 'consented_at'];

    protected function casts(): array
    {
        return [
            'is_subscribed' => 'boolean',
            'consented_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public static function categoryKeys(): array
    {
        return array_keys(self::CATEGORIES);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
