<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type',
        'provider',
        'status',
        'records_processed',
        'message',
        'error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'records_processed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function failed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function markSucceeded(int $records, ?string $message = null): void
    {
        $this->update([
            'status' => self::STATUS_SUCCEEDED,
            'records_processed' => $records,
            'message' => $message,
            'finished_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error' => $error,
            'finished_at' => now(),
        ]);
    }
}
