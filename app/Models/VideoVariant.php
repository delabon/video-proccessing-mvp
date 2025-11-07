<?php

namespace App\Models;

use App\Enums\VideoResolution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoVariant extends Model
{
    protected $fillable = [
        'video_id',
        'name',
        'resolution',
        'type',
        'disk',
        'path',
    ];

    protected $casts = [
        'resolution' => VideoResolution::class,
    ];

    public function originalVideo(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
