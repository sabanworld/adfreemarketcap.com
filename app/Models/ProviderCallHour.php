<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ProviderCallHour extends Model
{
    protected $fillable = [
        'provider',
        'hour_starts_at',
        'calls',
    ];

    public function hourStartsAt(): Carbon
    {
        /** @var Carbon $hour */
        $hour = $this->hour_starts_at;

        return $hour;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hour_starts_at' => 'datetime',
            'calls' => 'integer',
        ];
    }
}
