<?php
require(__DIR__ . '/../src/ffmpeg.php');

// Init
$ffmpeg = new FFmpeg(['target' => '/path/to/output', 'preset' => 'copy,intel_h264_vaapi,default']);

// Scan path and loop
foreach (glob('/path/of/video/files/*.{avi,divx,flv,m4v,mkv,mov,mp4,mpeg,mpg,ogm,wmv}', GLOB_BRACE) as $file) {
  // Show file
  echo "Processing $file\n";

  // Exec encoding
  $ffmpeg->input($file)->encode();

  // All done
  echo date('d-m-Y @ H:i:s') . ": Tasks done\n";
}

// Only one file
$ffmpeg->input('/path/of/video/file.avi')->encode();
