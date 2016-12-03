# auto-encode
Massconvert any videoformat readable by ffmpeg to H.264 video optimized for web.
As a bonus we'll also create a screenshot and a gif for you (optional).

**WARNING:** This script may clean-up invalid filenames (e.g. removing special symbols, double whitespace, etc.). If the original filenames are important to you, please backup first! Use this script at your own risk!

# Features
* Fast convert ('copy') on supported formats (e.g. MKV > MP4, FLV > MP4, etc.)
* Encode with ffmpeg, optimized for web (faststart)
* Auto extract subtitles and map subtitles support (it is possible to add extra subtitles when needed)
* Create a montage and animation (gif) of (encoded) video's
* Validate if encoding has been successful (may need a bit more tweaking)
* HW-Acceleration encoding support (optimized for Intel GPU) with fallback to software encoding, may speed up encoding

# Todo
* Video/audio extract
* Burn-in subtitle support (removed in last revision, in favor of mapping)
* Add more HWAccel-profiles
* ..

# Readme
## Requirements
### Encoding
* [Linux](https://www.archlinux.org/)
* [php](https://www.archlinux.org/packages/extra/x86_64/php/)
* [ffmpeg](https://www.archlinux.org/packages/extra/x86_64/ffmpeg)

### Images (optional)
* [imagemagick](https://www.archlinux.org/packages/extra/x86_64/imagemagick)
* [mpv](https://www.archlinux.org/packages/extra/x86_64/imagemagick)
