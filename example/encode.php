<?php
require('../ffmpeg.php');

$ffmpeg = new FFmpeg('/mnt/data/encode/', '/mnt/data/encoded/');
$ffmpeg->extract(['subtitle' => 'eng,en,ned,nl,unknown'])
       ->encode(['vcodec' => 'intel_h264_vaapi', 'burn-in' => 'eng,en,ned,nl,unknown', 'args' => ['threads' => 4]])
       ->images()
       ->start();
