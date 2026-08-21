<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['source_event_id', 'frequency', 'interval', 'occurrence_count'])]
final class RecurringSeries extends Model
{
    protected $table = 'recurring_series';

    /** @return BelongsTo<Event, $this> */
    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'source_event_id');
    }

    /** @return HasMany<Event, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('occurrence_number');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['interval' => 'integer', 'occurrence_count' => 'integer'];
    }
}
