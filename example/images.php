<?php
require('../ffmpeg.php');

// Init
$ffmpeg = new FFmpeg(['output' => '/home/archie/Videos/Images/', 'hwaccel' => 'intel_h264_vaapi']);

// Scan
foreach (glob('/home/archie/Videos/' . '*.{avi,divx,flv,m4v,mkv,mov,mp4,mpeg,mpg,ogm,wmv}', GLOB_BRACE) as $file) {
  // Show file
  echo "Processing $file\n";

  // Exec encoding
  echo date('d-m-Y @ H:i:s') . ": Creating images\n";
}
