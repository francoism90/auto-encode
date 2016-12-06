<?php
class FFmpeg
{
  private $config = [
    'target' => null,
    'tmp' => '/tmp/convert',
    'bin' => [
      'ffmpeg' => '/usr/bin/ffmpeg',
      'ffprobe' => '/usr/bin/ffprobe'
    ],
    'map' => [
      'a' => 'audio',
      's' => 'subtitle',
      'v' => 'video'
      //'d' => 'data',
      //'t' => 'attachment'
    ],
    'str_failed' => [
      'moov atom not found', 'Conversion failed', 'Invalid data',
      'Invalid argument', 'Invalid input', 'Could not find tag for codec',
      'Failed to create', 'Error parsing', 'Error opening', 'Error splitting',
      'No supported configuration',  'No VAAPI support'
    ],
    'preset' => 'copy,default',
    'presets' => [
      'default' => [
        'y' => null,
        'v' => 'error',
        'xerror' => null,
        'threads' => 0,
        'dts_delta_threshold' => 1000,
        'timelimit' => 5400,
        'vaapi_device' => null,
        'hwaccel' => null,
        'hwaccel_output_format' => null,
        'fflags' => '+genpts',
        'i' => null,
        'c:v' => 'libx264',
        'crf' => 20, // encoding quality (default 23)
        'preset' => 'veryfast', // preset to use (slower results in more compression)
        'tune' => null,
        'vf' => null,
        'profile:v' => 'high',
        'level:v' => '4.2',
        'movflags' => '+faststart', // optimize for streaming
        'pix_fmt' => 'yuv420p',
        'c:a' => 'libfdk_aac', // try aac when not using non-free
        'b:a' => '160k',
        'c:s' => null,
        'f' => 'mp4'
      ],
      'copy' => [
        'c:v' => 'copy',
        'c:a' => 'copy',
        'profile:v' => null,
        'level:v' => null,
        'b:a' => null,
        'f' => 'mp4'
      ],
      'intel_h264_vaapi' => [
        'threads' => 1, // unstable when using more 1> threads
        'hwaccel' => 'vaapi',
        'hwaccel_output_format' => 'vaapi',
        'vaapi_device' => '/dev/dri/renderD128',
        'c:v' => 'h264_vaapi',
        'profile:v' => 100,
        'level:v' => 42,
        'preset' => null,
        'crf' => null,
        'vf' => "format='nv12|vaapi,hwupload'",
        'pix_fmt' => null,
        'f' => 'mp4'
      ]
    ]
  ];
  private $input = [];

  public function __construct(array $config = []) {
    if (!empty($config))
      $this->config = array_replace_recursive($this->config, $config);

    if (empty($this->config['tmp']) || empty($this->config['target']))
      exit('Please specify the target/tmp directory');

    @mkdir($this->config['tmp'], 0775);
    @mkdir($this->config['target'], 0775);
  }

  public function input(string $path) {
    // File info
    $this->input = [];
    $this->input = pathinfo($path);
    $this->input['filename'] = preg_replace('/[^a-zA-Z0-9 ()-]/', '', $this->input['filename']);
    $this->input['basename'] = trim($this->input['filename']) . '.' . $this->input['extension'];
    $this->input['path'] = $this->input['dirname'] . '/' . $this->input['basename'];

    // Clean-up file
    if (!file_exists($this->input['path']))
      rename($path, $this->input['path']);

    // Get streams
    $this->input['streams'] = $this->streams();

    return $this;
  }

  public function encode() {
    // Set arguments
    $default = $this->config['presets']['default'];
    $input = escapeshellarg($this->input['path']);
    $map = $this->map();

    // Loop through presets
    foreach (explode(',', $this->config['preset']) as $preset) {
      // Preset arguments
      $presetArgs = $this->config['presets'][$preset];
      $target = "{$this->config['target']}/{$this->input['filename']}.{$presetArgs['f']}";

      // Build arguments
      $args = array_merge($default, $presetArgs);
      $args['i'] = $input;
      $args[] = $map;
      $args[] = escapeshellarg($target);

      // Set Logging
      $status = 'error';
      $report = $this->config['tmp'] . '/ffreport.log';
      putenv('FFREPORT=file='.escapeshellarg($report).':level=24');
      @unlink($report);

      // Execute ffmpeg
      $this->execute('ffmpeg', $this->buildArgs($args));

      // Unset logging
      putenv('FFREPORT');

      // Validate encoding
      if (file_exists($target) && file_exists($report)) {
        $output = $this->duration($target);
        $logging  = file_get_contents($report);
        foreach ($this->config['str_failed'] as $str) {
          if (stristr($logging, $str) || stristr($output, $str)) {
            $status = 'failed';
            break;
          }
        }

        // Check video duration
        if (!empty($output) && $status != 'failed') {
          preg_match("/Duration: (.*?), start:/", $logging, $durations);
          preg_match_all("/time=(.*?) bitrate/", $logging, $times);
          $duration = substr($durations[1], 0, 8);
          $time = substr(end($times[1]), 0, 8);
          $status = ($duration == $time) ? 'success' : 'mismatch';
          break;
        }
      }

      // Delete on fail/error
      @unlink($target);
    }

    // Rename on status
    rename($this->input['path'], $this->input['path'] . '.' . $status);

    return $this;
  }

  private function buildArgs(array $args) {
    // Remove unneeded (empty) argument
    $unset = [
      'crf','preset','vaapi_device','hwaccel','hwaccel_output_format',
      'tune','c:s','b:a','pix_fmt','profile:v','level:v','vf'
    ];
    foreach ($unset as $key) {
      if (empty($args[$key]))
        unset($args[$key]);
    }

    // Build as string
    $str = '';
    foreach ($args as $key => $value) {
      if (!is_int($key))
        $str.= "-$key $value ";
      else
        $str.= "$value ";
    }
    return preg_replace('!\s+!', ' ', trim($str));
  }

  private function execute(string $cmd, string $args) {
    return shell_exec("{$this->config['bin'][$cmd]} $args 2>&1");
  }

  private function streams() {
    $args = "-v error -analyzeduration 2147483647 -probesize 2147483647 ";
    $args.= "-print_format json -show_format -show_streams ";
    $args.= escapeshellarg($this->input['path']);
    return json_decode($this->execute('ffprobe', $args), true)['streams'];
  }

  private function duration(string $path) {
    $args = "-v error -select_streams v -show_entries stream=duration ";
    $args.= "-of default=noprint_wrappers=1:nokey=1 ";
    $args.= escapeshellarg($path);
    return $this->execute('ffprobe', $args);
  }

  private function map() {
    $str = '';
    foreach ($this->input['streams'] as $stream) {
      $key = array_search($stream['codec_type'], $this->config['map']);
      if (empty($key))
        continue;

      $str.= "-map 0:{$stream['index']} ";
      if (!empty($stream['tags'])) {
        $metaKey = "-metadata:s:$key:{$stream['index']}";
        foreach ($stream['tags'] as $tagKey => $tagValue) {
          if (!empty($tagValue))
            $str.= "$metaKey $tagKey='$tagValue' ";
        }
      }
    }
    return trim($str);
  }
}
