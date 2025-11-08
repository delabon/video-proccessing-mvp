<?php

namespace App\Models;

use App\Enums\VideoStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Video extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'disk',
        'path',
        'status',
        'error',
    ];

    protected $casts = [
        'status' => VideoStatus::class,
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(VideoVariant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
