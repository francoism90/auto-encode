**WARNING:** The scripts may clean-up invalid filenames (e.g. stripping special symbols, double whitespace, etc.). If the original filenames are important to you, please backup first! Use the scripts at your own risk!

# auto-encode
Massconvert (or just a video file) into any videoformat readable by ffmpeg to H.264 video optimized for web.

This script has been written without the need of any other PHP dependencies/libraries.

## Requirements
* [Linux](https://www.archlinux.org/)
* [php](https://www.archlinux.org/packages/extra/x86_64/php/)
* [ffmpeg](https://www.archlinux.org/packages/extra/x86_64/ffmpeg) (ffprobe should be included)

## Features
* Fast convert ('copy') on supported formats (e.g. MKV > MP4, FLV > MP4, etc.)
* HW-Acceleration encoding support (optimized for Intel GPU) with fallback to software encoding, may speed up encoding (TODO: Add more profiles)
* Validate if encoding has been successful (may need a bit more tweaking)
* Map metadata (e.g. subtitles, tags, etc.) of the original file, so it is available in the encoded file.

## Todo's
* Burn-in subtitle support (removed in last revision, in favor of mapping)
* Add more HWAccel-presets (please let me know your test results!)

## Examples
See *examples*.

You should at least provide a target and temporary-path (used for logs, etc. default: `/tmp/convert`):
```
$ffmpeg = new FFmpeg(['target' => '/path/to/output', 'tmp' => '/path/to/temp']);

// More advanced configuration
$ffmpeg = new FFmpeg([
  'target' => '/path/to/output',
  'bin' => [
    'ffmpeg' => '/usr/local/bin/ffmpeg'
    'ffprobe' => '/usr/local/bin/ffprobe'
  ],  
  'presets' => [
    'default' => [
      'threads' => 4,
      'preset' => 'superfast',
      'tune' => 'film'
    ]
  ],
  'preset' => 'default'
]);

```

# thumbs (optional)
Create thumbnails, a screenshot and an animated gif of video files.

## Requirements
* [imagemagick](https://www.archlinux.org/packages/extra/x86_64/imagemagick)
* [mpv](https://www.archlinux.org/packages/extra/x86_64/imagemagick)
