# auto-encode
Massconvert any videoformat readable by ffmpeg to H.264 video optimized for web.

# Features
* Auto. extract (in-built) subtitles of video files (with language code)
* Encode with ffmpeg, optimized for web (preset: ultrafast, tune: zerolatency, faststart)
* Burn-in (extracted) subtitles-files into the video
* Create a screenshot and thumbnail of the (encoded) video

# FAQ
## What packages need to be installed?
* [php](https://www.archlinux.org/packages/extra/x86_64/php/)
* [ffmpeg](https://www.archlinux.org/packages/extra/x86_64/ffmpeg)

## Why is this written in PHP?
PHP(7) isn't that bad, and has many in-built functions.

## Why is their no video validation checking?
Planned, but I assume you only encode video files that work.

## Why encode mp4 files (again)?
Usually the video isn't optimized for web.

## Why burn-in subtitles?
Their is still no (out-of-the-box) HTML5 solution for subtitles.
