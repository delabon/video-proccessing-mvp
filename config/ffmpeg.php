<?php

return [
    'ffmpeg_binaries' => env('FFMPEG_BINARIES', '/usr/bin/ffmpeg'),
    'ffprobe_binaries' => env('FFPROBE_BINARIES', '/usr/bin/ffprobe'),
    'temp_folder' => env('FFMPEG_TEMP_FOLDER', '/tmp/ffmpeg-tmp'),
    'timeout' => 3600,
    'threads' => 12,
];
