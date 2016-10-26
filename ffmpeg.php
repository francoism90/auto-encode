<?php
class FFmpeg
{
  private $config = [
    'tmp' => '/tmp/convert/', // used for logging and creating images
    'output' => null, // output folder
    'copy' => ['flv','mkv','mp4'], // try to 'copy' videos with this extension (prevent re-encoding)
    'subformat' => ['ass','ssa','srt','sub'], // valid subtitles formats
    'hwaccel' => null, // try to use HW-acceleration
    'image' => [
      'number' => 15, // number of thumbs
      'offset' => 25, // try to prevent credits
      'size' => '319x180', // thumb size
      'tile' => 3,
      'delay' => 120 // animation speed
    ]
  ];
  private $input = [];
  private $args = [
    'encode' => [
      'y' => null, // allow overwrite
      'xerror' => null, // fail on error
      'v' => 'error', // logging level
      'threads' => 0, // number of threads (0 = optimal)
      'timelimit' => 3600, // timeout when encoding takes more <num> seconds
      'vaapi_device' => null, // used for HWaccel
      'hwaccel' => null, // used for HWaccel
      'hwaccel_output_format' => null, // used for HWaccel
      'i' => null, // input
      'map' => '0:0 -map 0:1', // map video and audio
      'c:v' => 'libx264', // codec to use for encoding
      'crf' => 18, // encoding quality (default 22)
      'preset' => 'ultrafast', // preset to use (slower results in more compression)
      'tune' => 'zerolatency', // film, animation, fastdecode, etc. (or null to disable)
      'vf' => null, // filter (TODO: burn-in subtitle(s))
      'profile:v' => 'high', // high: iPad Air and later, iPhone 5s and later
      'level:v' => '4.2', // 4.2: iPad Air and later, iPhone 5s and later
      'movflags' => '+faststart', //  allow video playback before it is completely downloaded
      'pix_fmt' => 'yuv420p', // use YUV pixel format and 4:2:0 Chroma subsampling
      'c:a' => 'copy', // audio codec
      'c:s' => null, // subtitle codec
      'f' => 'mp4' // extension
    ],

    'copy' => [
      'y' => null,
      'xerror' => null,
      'v' => 'error',
      'threads' => 0,
      'timelimit' => 1800, // timeout when copying takes more <num> seconds
      'i' => null,
      'map' => '0:0 -map 0:1',
      'c:v' => 'copy',
      'c:a' => 'copy',
      'c:s' => null,
      'f' => 'mp4'
    ]
  ];

  public function __construct(array $config = []) {
    $this->config = array_replace_recursive($this->config, $config);
    if (empty($this->config['tmp']) || empty($this->config['output']))
      exit('Please specify the output and/or tmp path');

    @mkdir($this->config['tmp'], 0775);
    @mkdir($this->config['output'], 0775);
  }

  public function set(string $key, $value, string $propery = 'ffmpeg') {
    $this->args[$propery][$key] = $value;
  }

  public function input(string $path) {
    // Set input
    $this->input = $this->pathInfo($path);
    rename($path, $this->input['path']);

    // Set streams
    $this->input['streams'] = $this->getStreams();

    return $this;
  }

  private function buildArgs(array $args) {
    $ret = '';
    foreach ($args as $key => $value) {
      $ret.= " -$key $value";
    }
    return rtrim($ret);
  }

  private function pathInfo(string $path) {
    $path = pathinfo($path);
    $path['filename'] = preg_replace('/\s+/', ' ', $path['filename']);
    $path['filename'] = preg_replace('/[^a-zA-Z0-9 ()-]/', '', $path['filename']);
    $path['basename'] = trim($path['filename']) . '.' . $path['extension'];
    $path['path'] = $path['dirname'] . '/' . $path['basename'];
    return $path;
  }

  private function getStreams() {
    $ffprobe = json_decode(shell_exec('ffprobe -v quiet -print_format json -show_format -show_streams '.escapeshellarg($this->input['path'])), true)['streams'];
    $streams = [];
    foreach ($ffprobe as $stream) {
      // Is this even possible?
      if (empty($stream['codec_name']))
        continue;

      // Filters
      $stream['codec_name'] = strtolower($stream['codec_name']);
      if (!empty($stream['tags'])) {
         $stream['tags'] = array_change_key_case($stream['tags']);
         if (!empty($stream['tags']['language']))
           $stream['locale'] = preg_replace('/[^a-zA-Z0-9 ()-]/', '', $stream['tags']['language']) ?: 'unknown';
      }

      // Fix Subtitle codec_name
      if ($stream['codec_type'] == 'subtitle')
        $stream['codec_name'] = in_array($stream['codec_name'], $this->config['sub_formats']) ? $stream['codec_name'] : 'srt';

      // Add as stream
      $streams[$stream['codec_type']][] = $stream;
    }
    return $streams;
  }

  public function extract(string $type = 'subtitle') {
    if (!empty($this->input['streams'][$type])) {
      foreach ($this->input['streams'][$type] as $stream) {
        $path = $this->input['dirname'] . '/' .$this->input['filename'] . '_ext_' . $stream['locale'] . '_' . $stream['index'] . '.' . $stream['codec_name'];
        shell_exec(sprintf('ffmpeg -y -xerror -v quiet -i %s -map 0:%d %s', escapeshellarg($this->input['path']), $stream['index'], escapeshellarg($path)));
      }
    }
    return $this;
  }

  private function getExtract(array $exts) {
    $matches = [];
    foreach (glob($this->input['dirname'] . '/' . $this->input['filename'] . '_ext_*.{'.implode(',', $exts).'}', GLOB_BRACE) as $file) {
      $matches[] = $file;
    }
    return $matches;
  }

  private function getHWAccel(string $profile = 'intel_h264_vaapi') {
    switch ($profile) {
      case 'intel_h264_vaapi':
        return [
          'threads' => 1, // unstable when using more 1> threads
          'vaapi_device' => '/dev/dri/renderD128',
          'hwaccel' => 'vaapi',
          'hwaccel_output_format' => 'vaapi',
          'c:v' => 'h264_vaapi',
          'profile:v' => 100,
          'level:v' => 42,
          'vf' => "format='nv12|vaapi,hwupload'",
          'pix_fmt' => null,
          'f' => 'mp4'
        ];
        break;
      default:
        exit('Unsupported HWAccel profile given');
    }
  }

  private function validate() {
    // Not encoded
    if (!file_exists($this->config['tmp'] . 'ffreport.log'))
      return 'encode';

    // Logging
    $log = file_get_contents($this->config['tmp'] . 'ffreport.log');
    if (!stristr($log, 'No VAAPI support') && !stristr($log, 'Failed to create a VAAPI device') && !stristr($log, 'Conversion failed') && !stristr($log, 'Error opening filters') && !stristr($log, 'Invalid data found when processing input')) {
      // Duration (TODO: add margin)
      preg_match("/Duration: (.*?), start:/", $log, $durations);
      preg_match_all("/time=(.*?) bitrate/", $log, $times);
      if (!empty($durations[1]) && !empty($times[1])) {
        $duration = substr($durations[1], 0, 8) ?: true;
        $time = substr(end($times[1]), 0, 8) ?: false;
        return ($duration == $time) ? 'success' : 'mismatch';
      }
    }
    return 'failed';
  }

  public function getDuration(string $path) {
    return shell_exec(sprintf('ffprobe -v error -select_streams v:0 -show_entries stream=duration -of default=noprint_wrappers=1:nokey=1 %s', escapeshellarg($path)));
  }

  public function encode() {
    // Logging
    putenv('FFREPORT=file='. $this->config['tmp'] . 'ffreport.log:level=32');
    @unlink($this->config['tmp'] . 'ffreport.log');

    // Set input
    $this->args['encode']['i'] = escapeshellarg($this->input['path']);
    $this->args['copy']['i'] = escapeshellarg($this->input['path']);

    // Map subtitles
    if (!empty($this->config['subformat'])) {
      foreach ($this->getExtract($this->config['subformat']) as $key => $file) {
        $file = escapeshellarg($file);
        $language = explode('_', $file)[2];

        // Set to args
        foreach(['encode','copy'] as $arr) {
          $this->args[$arr]['i'] = $this->args[$arr]['i'] . " -i $file";
          $this->args[$arr]['map'] = $this->args[$arr]['map'] . ' -map 0:3';
          $this->args[$arr]['c:s'] = 'mov_text';
          $this->args[$arr]["metadata:s:s:$key"] = "language='$language'";
        }
      }
    }

    // When using HWAccel
    $hwAccel = [];
    if (!empty($this->config['hwaccel']))
      $hwAccel = array_replace($this->args['encode'], $this->getHWAccel($this->config['hwaccel']));

    // Unset unneeded
    foreach (['vaapi_device','hwaccel','hwaccel_output_format','vf','tune','c:s','pix_fmt'] as $key) {
      if (empty($this->args['encode'][$key])) {
        unset($this->args['encode'][$key]);
        unset($this->args['copy'][$key]);
      }

      if (empty($hwAccel[$key]))
        unset($hwAccel[$key]);
    }

    // Copy
    $output = escapeshellarg($this->config['output'] . $this->input['filename'] . '.' . $this->args['encode']['f']);
    if (!empty($this->config['copy']) && in_array($this->input['extension'], $this->config['copy']))
      shell_exec(sprintf('ffmpeg %s %s', $this->buildArgs($this->args['copy']), $output));

    // Encode
    if (in_array($this->validate(), ['encode','failed'])) {
      // Use HWAccel
      if (!empty($hwAccel['hwaccel']))
        shell_exec(sprintf('ffmpeg %s %s', $this->buildArgs($hwAccel), $output));

      // Failed or SW-encoding
      if (in_array($this->validate(), ['encode','failed']))
        shell_exec(sprintf('ffmpeg %s %s', $this->buildArgs($this->args['encode']), $output));
    }

    // Unset export
    putenv('FFREPORT');

    // Add status as extension
    rename($this->input['path'], $this->input['path'] . '.' . $this->validate());

    return $this;
  }

  public function images() {
    foreach (glob($this->config['output'] . $this->input['filename'] . '.{mkv,mp4}', GLOB_BRACE) as $file) {
      // Clean-up
      array_map('unlink', glob($this->config['tmp'] . '*.jpg'));

      // Needed variables
      $image = $this->config['image'];
      $output = $this->config['output'] . $this->input['filename'];
      $duration = floor($this->getDuration($file)) - $image['offset'];
      $splice = floor($duration / $image['number']);
      $time = $splice;

      // Should be 10> secs long
      if ($duration > 10) {
        // Create thumbs
        for ($i = 1; $i <= $image['number']; $i++) {
          shell_exec(sprintf("cd %s && mpv --quiet --no-audio --vo=image --start=%s --frames=1 %s", escapeshellarg($this->config['tmp']), $time, escapeshellarg($file)));
          rename($this->config['tmp'] . '00000001.jpg', $this->config['tmp'] . "frame-$time.jpg");
          $time += $splice;
        }

        // Resize thumbs, create screen and animation
        shell_exec(sprintf('mogrify -resize %s %s', $image['size'], escapeshellarg($this->config['tmp'] . 'frame-*.jpg')));
        shell_exec(sprintf('montage -border 0 -mode concatenate -quality 90 -tile %d %s %s', $image['tile'], escapeshellarg($this->config['tmp'] . 'frame-*.jpg'), escapeshellarg($output . '.jpg')));
        shell_exec(sprintf('convert -loop 0 -delay %d -quality 90 %s %s', $image['delay'], escapeshellarg($this->config['tmp'] . 'frame-*.jpg'), escapeshellarg($output . '.gif')));
      }
    }
    return $this;
  }
}
