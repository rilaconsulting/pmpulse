<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class AppfolioConnection extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'client_id',
        'client_secret_encrypted',
        'api_base_url',
        'status',
        'last_success_at',
        'last_error',
        'sync_config',
    ];

    protected function casts(): array
    {
        return [
            'last_success_at' => 'datetime',
            'sync_config' => 'array',
        ];
    }

    /**
     * Get the decrypted client secret.
     */
    public function getClientSecretAttribute(): ?string
    {
        if (empty($this->client_secret_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->client_secret_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the sync runs for this connection.
     */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    /**
     * Check if the connection is configured with credentials.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->client_id) && ! empty($this->client_secret_encrypted);
    }

    /**
     * Mark the connection as successfully synced.
     */
    public function markAsSuccess(): void
    {
        $this->update([
            'status' => 'connected',
            'last_success_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Mark the connection as having an error.
     */
    public function markAsError(string $error): void
    {
        $this->update([
            'status' => 'error',
            'last_error' => $error,
        ]);
    }
}
