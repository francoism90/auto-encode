<?php
$base = '/mnt/tmp/convert';
$target = '/mnt/tmp/convert';
//$target = '/mnt/data/www/dom4/storage/app/upload/video';
$tmp = '/tmp';
$encode = ['.mp4','.mkv','.wmv','.mpeg','.mpg','.avi'];
$subs = ['ass','ssa','srt','sub'];
$extract = ['ned','eng','nl','en'];

// Scan directory
foreach (array_diff(scandir($base), array('..', '.')) as $key => $file) {
  $name = strstr($file, '.', true);
  $ext = strstr($file, '.');
  $data = json_decode(shell_exec("ffprobe -v quiet -print_format json -show_format -show_streams $file"), true);
  if (empty($data))
    continue;

  // Encode to mp4
  if (in_array($ext, $encode) && !file_exists("$target/$name.mp4")) {
    // Extract Subtitles
    foreach ($data['streams'] as $subKey => $subVal) {
      if (in_array($subVal['codec_name'], $subs) && in_array($subVal['tags']['language'], $extract)) {
        $path = "$base/$name.".$subVal['codec_name'];
        if (!file_exists($path))
          shell_exec("ffmpeg -i $base/$file $path");
      }
    }

    // Add Subtitle (External)
    $cmd = "ffmpeg -i $base/$file -c:v libx264 -preset ultrafast -tune zerolatency -movflags +faststart";
    foreach ($subs as $sub) {
			$path = "$base/$name.$sub";
			if (file_exists($path)) {
        $cmd.= " -vf subtitles=$path ";
				break;
      }
		}

    // Start Encode
    shell_exec("$cmd $target/$name.mp4");
  }

  // When Encoded
  elseif (in_array($ext, $encode) && file_exists("$target/$name.mp4")) {
    // Create Thumb and Screen
    if (!file_exists("$target/$name-thumb.jpg") || !file_exists("$target/$name-screen.jpg")) {
      shell_exec("mkdir -p $tmp/conv; rm -rf $tmp/conv/*;
                  ffmpeg -hwaccel auto -threads 4 -i $target/$name.mp4 -an -s 480x300 -vf fps=1/100 -qscale:v 10 $tmp/conv/1-%03d.jpg;
                  montage -mode concatenate -tile 4x $tmp/conv/1-*.jpg $target/$name-screen.jpg;
                  cp -f $tmp/conv/1-001.jpg $target/$name-thumb.jpg; cp -f $tmp/conv/1-002.jpg $target/$name-thumb.jpg");
    }

    // Move to complete-dir
    shell_exec("mkdir -p $base/done; mv $base/$name* $base/done");
  }
}
