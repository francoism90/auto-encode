# auto-encode
Massconvert any videoformat readable by ffmpeg to H.264 video optimized for web.
As a bonus we'll also create a screenshot and a thumbnail for you.

# Features
* Auto. extract (in-built) subtitles of video files (with language code)
* Encode with ffmpeg, optimized for web (preset: ultrafast, tune: zerolatency, faststart)
* Burn-in (extracted) subtitles-files into the video
* Create a screenshot and thumbnail of the (encoded) video
* Validate if encoding has been successfully done

# Readme
## Requirements
* [Linux](https://www.archlinux.org/)
* [php](https://www.archlinux.org/packages/extra/x86_64/php/)
* [ffmpeg](https://www.archlinux.org/packages/extra/x86_64/ffmpeg)
* [imagemagick](https://www.archlinux.org/packages/extra/x86_64/imagemagick)
