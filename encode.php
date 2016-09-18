<?php
$base = '/home/archie/encode';
$target = '/home/archie/video';
$tmp = '/tmp';
$encode = ['.mp4','.mkv','.wmv','.mpeg','.mpg','.avi','.ogm','.divx','.mov','.m4v','.flv'];
$subs = ['ass','ssa','srt','sub'];
$locales = ['eng','ned','en','nl','unk'];

// functions
function debug(string $str) {
  echo date(DATE_RFC2822).": $str\n";
}

// Scan directory
foreach (array_diff(scandir($base), array('..', '.')) as $key => $file) {
  $name = strstr($file, '.', true);
  $ext = strstr($file, '.');

  // Needs to be encoded
  if (!in_array($ext, $encode)) {
    debug("Skip: $file");
    continue;
  }

  // Start!
  debug("Processing $file");

  // Encode to mp4
  if (!file_exists("$target/$name.mp4")) {
    // Extract Subtitles
    $data = json_decode(shell_exec("ffprobe -v quiet -print_format json -show_format -show_streams $file"), true);
    if (!empty($data)) {
      foreach ($data['streams'] as $subKey => $subVal) {
        // No subtitle format is given
        if (empty($subVal['codec_name']) && $subVal['codec_type'] == 'subtitle' || !empty($subVal['codec_name']) && stristr($subVal['codec_name'], 'subrip'))
          $subVal['codec_name'] = 'srt';

        // Check is subtitle stream
        if (empty($subVal['codec_name']) || !in_array($subVal['codec_name'], $subs))
          continue;

        // When no language-tag has been given, select the first locale match
        $subVal['tags'] = !empty($subVal['tags']) ? array_change_key_case($subVal['tags']) : []; // tags can be UPPERCASE
        if (empty($subVal['tags']) || empty($subVal['tags']['language']))
          $subVal['tags']['language'] = $locales[0];

        // Loop and extract on locale match
        $path = "$base/$name.".$subVal['codec_name'];
        foreach ($locales as $locale) {
          if (stristr($subVal['tags']['language'], $locale) && !file_exists($path)) {
            debug("Extract subtitle ($locale => $path)");
            shell_exec("ffmpeg -v fatal -threads 0 -i $base/$file $path");
          }
        }
      }
    }

    // Add Subtitle (External)
    $cmd = "ffmpeg -y -v fatal -threads 0 -i $base/$file -c:v libx264 -crf 23 -preset ultrafast -tune zerolatency -movflags +faststart";
    foreach ($subs as $sub) {
			$path = "$base/$name.$sub";
			if (file_exists($path)) {
        $cmd.= " -vf subtitles=$path";
        debug("Burn-In Subtitle ($path)");
				break;
      }
		}

    // Start Encode
    debug("Start Encoding ($cmd)");
    shell_exec("$cmd $target/$name.mp4");
    debug("Encoding finished ($target/$name.mp4)");
  }

  // When Encoded
  elseif (file_exists("$target/$name.mp4")) {
    // Create Thumb and screen
    if (!file_exists("$target/$name-thumb.jpg") || !file_exists("$target/$name-screen.jpg")) {
      debug("Create thumb and screen");
      shell_exec("mkdir -p $tmp/conv; rm -rf $tmp/conv/*;
                  ffmpeg -threads 0 -v fatal -i $target/$name.mp4 -an -s 480x300 -vf fps=1/100 -qscale:v 10 $tmp/conv/1-%03d.jpg;
                  montage -mode concatenate -tile 4x $tmp/conv/1-*.jpg $target/$name-screen.jpg;
                  cp -f $tmp/conv/1-001.jpg $target/$name-thumb.jpg; cp -f $tmp/conv/1-002.jpg $target/$name-thumb.jpg");
    }

    // Move to complete-dir
    else {
      shell_exec("mkdir -p $base/done; mv $base/$name.* $base/done");
      debug("Done: $base/$name.* => $base/done");
    }
  }
}
