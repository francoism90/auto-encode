<?php
require(__DIR__ . '/../src/thumbs.php');

// Init
$thumbs = new Thumbs(['target' => '/path/to/output']);

// Scan path and loop
foreach (glob('/path/of/video/files/*.{mp4,mkv}', GLOB_BRACE) as $file) {
  // Show file
  echo "Processing $file\n";

  // Create thumbs
  $thumbs->input($file)->thumbs()->screen()->animation();

  // All done
  echo date('d-m-Y @ H:i:s') . ": Tasks done\n";
}

// Only one file
$thumbs->input('/path/of/video/file.mp4')->thumbs()->screen()->animation();
