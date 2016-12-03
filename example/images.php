<?php
require(__DIR__ . '/../src/thumbs.php');

// Init
$target = '/path/to/video/files';
$thumbs = new Thumbs(['target' => $target]);

// Scan path and loop
foreach (glob($target . '/*.{mp4,mkv}', GLOB_BRACE) as $file) {
  // Show file
  echo "Processing $file\n";

  // Create thumbs
  $thumbs->input($file)->thumbs()->screen()->animation();

  // All done
  echo date('d-m-Y @ H:i:s') . ": Tasks done\n";
}

// Only one file
$thumbs->input('/path/to/video/file.mp4')->thumbs()->screen()->animation();
