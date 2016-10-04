<?php
require('../ffmpeg.php');

$ffmpeg = new FFmpeg('/mnt/data/convert/', '/mnt/data/convert/');
$ffmpeg->images(['threads' => 4])
       ->start();
