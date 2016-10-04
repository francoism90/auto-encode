# auto-encode
Massconvert any videoformat readable by ffmpeg to H.264 video optimized for web.
As a bonus we'll also create a screenshot and a gif for you.

**WARNING:** This script may clean-up invalid filenames (e.g. removing special symbols, double whitespace, etc.). If the original filenames are important to you, please backup first!

# Features
* Encode with ffmpeg, optimized for web (faststart)
* Auto extract subtitles and burn-in the subtitle matching prefered locale
* Create a screenshot and gif of the encoded video
* Validate if encoding has been successful (needs a bit more tweaking)
* HW-Acceleration encoding (optimized for Intel GPU) with fallback to software encoding

# Todo
* Video/audio extract
* '-map' selection

# Readme
## Requirements
* [Linux](https://www.archlinux.org/)
* [php](https://www.archlinux.org/packages/extra/x86_64/php/)
* [ffmpeg](https://www.archlinux.org/packages/extra/x86_64/ffmpeg)
* [imagemagick](https://www.archlinux.org/packages/extra/x86_64/imagemagick)
