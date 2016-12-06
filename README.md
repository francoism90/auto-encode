**WARNING:** The scripts may clean-up invalid filenames (e.g. stripping special symbols, double whitespace, etc.). The original filename will be updated (e.g. `original-video.avi >> original-video.avi.success`). If the original filename is important to you, please backup first! Use the scripts at your own risk!

# auto-encode
Massconvert (or just a video file) into any videoformat readable by ffmpeg to H.264 video optimized for web.

This script has been written without the need of any other PHP dependencies/libraries.

## Requirements
* [Linux](https://www.archlinux.org/) (or any other distro, e.g. Ubuntu, Debian, etc.)
* [php](https://www.archlinux.org/packages/extra/x86_64/php/)
* [ffmpeg](https://www.archlinux.org/packages/extra/x86_64/ffmpeg/) (ffprobe should be included)

## Features
* Fast convert ('copy') on supported formats (e.g. MKV > MP4, FLV > MP4, etc.), with fallback to software encoding
* HW-Acceleration encoding support (optimized for Intel GPU) with fallback to software encoding, may speed up encoding (TODO: Add more profiles)
* Validate if encoding has been successful (may need a bit more tweaking)
* Map metadata (e.g. subtitles, tags, etc.) of the original file, so it is available in the encoded file.

## Todo's
* External subtitle mapping support (e.g. `/path/of/original-video.srt`) (removed in last revision). Note: Subtitles included in the original file are already converted into the encoded video.
* Add more HWAccel-presets (please let me know your test results!)

## Examples
See *example*.

You should at least provide a target and temporary-path (used for logs, etc. default: `/tmp/convert`):
```
$ffmpeg = new FFmpeg(['target' => '/path/to/output', 'tmp' => '/path/to/temp']);

// How presets work
$ffmpeg = new FFmpeg([
  'target' => '/path/to/output',
  'preset' => 'copy,intel_h264_vaapi,default' // try to use 'copy' preset first, if that fails, try intel_h264_vaapi preset, SW-preset is the last preset to try - see source of ffmpeg.php for the preset options
]);

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
  'preset' => 'default' // or 'copy,default'
]);

```

# thumbs (optional)
Create thumbnails, a screenshot and an animated gif of video files.

## Requirements
* [Linux](https://www.archlinux.org/)
* [php](https://www.archlinux.org/packages/extra/x86_64/php/)
* [ffmpeg](https://www.archlinux.org/packages/extra/x86_64/ffmpeg/) (ffprobe should be included)
* [imagemagick](https://www.archlinux.org/packages/extra/x86_64/imagemagick/)
* [mpv](https://www.archlinux.org/packages/community/x86_64/mpv/)
* [jpegoptim](https://www.archlinux.org/packages/community/x86_64/jpegoptim/)

## Examples
See *example*.

```
$thumbs = new Thumbs([
  'target' => '/path/to/output',
  'thumbs' => 25, // number of thumbnails
  'delay' => 100, // animation (gif) delay
  //'force' => true // overwrite already produced images
]);

// You should always use thumbs() as first method
$thumbs->input('/path/of/video/file.mp4')->thumbs()->screen()->animation();
$thumbs->input('/path/of/video/file.mp4')->thumbs()->animation();
$thumbs->input('/path/of/video/file.mp4')->thumbs()->screen();
```
