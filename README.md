# auto-encode
Massconvert any videoformat readable by ffmpeg to H.264 video optimized for web.
As a bonus we'll also create a screenshot and a thumbnail for you.

# Features
* Auto. extract (in-built) subtitles of video files (with language code)
* Encode with ffmpeg, optimized for web (preset: ultrafast, tune: zerolatency, faststart)
* Burn-in (extracted) subtitles-files into the video
* Create a screenshot and thumbnail of the (encoded) video

# FAQ
## What packages need to be installed?
* [php](https://www.archlinux.org/packages/extra/x86_64/php/)
* [ffmpeg](https://www.archlinux.org/packages/extra/x86_64/ffmpeg)
* [imagemagick](https://www.archlinux.org/packages/extra/x86_64/imagemagick)

## Why is this written in PHP?
PHP(7) isn't that bad, and has many in-built functions.
I agree ```shell_exec``` should be avoid, but I want to keep it asap.

## Why no (full) video validation checking?
Planned, but I assume you only want encode video files that have been tested.
At the moment we simple do a file extension check.

## Why encode mp4 files (again)?
Usually the video isn't optimized for web (faststart), doesn't have a burned-in subtitle, etc.

## Why burn-in subtitles?
Their is still no (out-of-the-box) HTML5 solution for subtitles.
It is however possible to simple change the ffmpeg command to your own needs.
