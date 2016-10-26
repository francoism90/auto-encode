<?php
require('../ffmpeg.php');

// Init
$ffmpeg = new FFmpeg(['output' => '/home/archie/Videos/HTML5/', 'hwaccel' => 'intel_h264_vaapi']);
$ffmpeg->set('threads', 4);
$ffmpeg->set('threads', 4, 'copy');

// Scan
foreach (glob('/home/archie/Videos/' . '*.{avi,divx,flv,m4v,mkv,mov,mp4,mpeg,mpg,ogm,wmv}', GLOB_BRACE) as $file) {
  // Show file
  echo "Processing $file\n";

  // Exec encoding
  echo date('d-m-Y @ H:i:s') . ": Encoding started\n";
  $ffmpeg->input($file)->extract('subtitle')->encode();

  // Exec images
  echo date('d-m-Y @ H:i:s') . ": Creating images\n";
  $ffmpeg->images();
}
