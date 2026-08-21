<?php

namespace App\Domain\Accounts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

final class PersonalDataExport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime', 'processing_started_at' => 'datetime', 'ready_at' => 'datetime',
            'expires_at' => 'datetime', 'failed_at' => 'datetime', 'download_token' => 'encrypted',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['requested', 'processing', 'ready'], true) && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function setDownloadToken(string $token): void
    {
        $this->forceFill(['download_token' => $token, 'download_token_hash' => Hash::make($token)])->save();
    }

    public function acceptsDownloadToken(?string $token): bool
    {
        return is_string($token) && $this->download_token_hash !== null && Hash::check($token, $this->download_token_hash);
    }

    public function hasSafeStoragePath(): bool
    {
        return is_string($this->storage_path)
            && preg_match('#\Aaccount-exports/'.$this->user_id.'/[a-f0-9-]{36}\.json\z#', $this->storage_path) === 1;
    }

    public function downloadUrl(): string
    {
        abort_unless($this->status === 'ready' && ! $this->isExpired(), 403);

        return URL::temporarySignedRoute('account.privacy.exports.download', $this->expires_at, ['export' => $this, 'token' => $this->download_token]);
    }
}
