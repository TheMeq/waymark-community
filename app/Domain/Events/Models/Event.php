<?php

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Holidays\Models\Holiday;
use App\Domain\Socials\Models\Social;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'type',
    'title',
    'slug',
    'summary',
    'description',
    'starts_at',
    'ends_at',
    'status',
    'is_public',
    'published_at',
    'completion_override',
    'organiser_id',
    'parent_event_id',
    'recurring_series_id',
    'occurrence_number',
])]
final class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        self::creating(function (Event $event): void {
            $event->calendar_uid ??= (string) Str::uuid();
            $event->calendar_sequence ??= 0;
        });
        self::updating(function (Event $event): void {
            if ($event->isDirty(['title', 'summary', 'description', 'starts_at', 'ends_at', 'status'])) {
                $event->calendar_sequence = (int) $event->getOriginal('calendar_sequence') + 1;
            }
        });
    }

    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function organiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organiser_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_event_id');
    }

    /** @return HasMany<Event, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_event_id')->orderBy('starts_at')->orderBy('id');
    }

    /** @return BelongsTo<RecurringSeries, $this> */
    public function recurringSeries(): BelongsTo
    {
        return $this->belongsTo(RecurringSeries::class);
    }

    /** @return HasOne<Walk, $this> */
    public function walk(): HasOne
    {
        return $this->hasOne(Walk::class);
    }

    /** @return HasOne<Social, $this> */
    public function social(): HasOne
    {
        return $this->hasOne(Social::class);
    }

    /** @return HasOne<Holiday, $this> */
    public function holiday(): HasOne
    {
        return $this->hasOne(Holiday::class);
    }

    /** @return HasMany<EventUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(EventUpdate::class)->latest('created_at')->latest('id');
    }

    public function isPast(?CarbonInterface $at = null): bool
    {
        if ($this->completion_override !== null) {
            return $this->completion_override;
        }

        return ($this->ends_at ?? $this->starts_at)->lessThanOrEqualTo($at ?? now());
    }

    public function isCompleted(?CarbonInterface $at = null): bool
    {
        return $this->isPast($at);
    }

    /** @param Builder<Event> $query */
    public function scopePast(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query->where(function (Builder $query) use ($at): void {
            $query->where('completion_override', true)
                ->orWhere(function (Builder $query) use ($at): void {
                    $query->whereNull('completion_override')
                        ->where(function (Builder $query) use ($at): void {
                            $query->where('ends_at', '<=', $at)
                                ->orWhere(function (Builder $query) use ($at): void {
                                    $query->whereNull('ends_at')
                                        ->where('starts_at', '<=', $at);
                                });
                        });
                });
        });
    }

    /** @param Builder<Event> $query */
    public function scopeCompleted(Builder $query, ?CarbonInterface $at = null): Builder
    {
        return $this->scopePast($query, $at);
    }

    /** @param Builder<Event> $query */
    public function scopeCurrentOrUpcoming(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query->where(function (Builder $query) use ($at): void {
            $query->where('completion_override', false)
                ->orWhere(function (Builder $query) use ($at): void {
                    $query->whereNull('completion_override')
                        ->where(function (Builder $query) use ($at): void {
                            $query->where('ends_at', '>', $at)
                                ->orWhere(function (Builder $query) use ($at): void {
                                    $query->whereNull('ends_at')
                                        ->where('starts_at', '>', $at);
                                });
                        });
                });
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => EventStatus::class,
            'is_public' => 'boolean',
            'published_at' => 'datetime',
            'completion_override' => 'boolean',
            'occurrence_number' => 'integer',
            'calendar_sequence' => 'integer',
        ];
    }
}
