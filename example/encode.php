<?php
require(__DIR__ . '/../src/ffmpeg.php');

// Init
$target = '/path/to/video/files';
$ffmpeg = new FFmpeg(['target' => $target, 'preset' => 'copy,intel_h264_vaapi,default']);

// Scan path and loop
foreach (glob($target . '/*.{avi,divx,flv,m4v,mkv,mov,mp4,mpeg,mpg,ogm,wmv}', GLOB_BRACE) as $file) {
  // Show file
  echo "Processing $file\n";

  // Exec encoding
  $ffmpeg->input($file)->encode();

  // All done
  echo date('d-m-Y @ H:i:s') . ": Tasks done\n";
}

// Only one file
$ffmpeg->input('/path/to/video/file.avi')->encode();
