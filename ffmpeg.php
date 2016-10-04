<?php
class FFmpeg
{
  private $path = [];
  private $queue = [];
  private $tasks = [];
  private $entry = [];

  public function __construct(string $base, string $out, string $tmp = '/tmp/convert/') {
    // Init
    @mkdir($tmp, 0775);
    $this->path = ['base' => $base, 'out' => $out, 'tmp' => $tmp];

    // Fill queue
    foreach (glob($this->path['base'] .'*.{avi,divx,flv,m4v,mkv,mov,mp4,mpeg,mpg,ogm,wmv}', GLOB_BRACE) as $file) {
      // Remove invalid symbols
      $path = $this->clean($file);
      rename($file, $path['fullpath']);

      // Add to queue
      $this->queue[] = ['file' => $path['fullpath'], 'path' => $path];
    }

    // Check we have data
    if (empty($this->queue))
      exit('No files to process');
  }

  private function clean(string $file) {
    $path = pathinfo($file);
    $path['filename'] = preg_replace('/\s+/', ' ', $path['filename']);
    $path['filename'] = preg_replace('/[^a-zA-Z0-9 ()_-]/', '', $path['filename']);
    $path['basename'] = trim($path['filename']) . '.' . $path['extension'];
    $path['fullpath'] = $path['dirname'] . '/' . $path['basename'];
    return $path;
  }

  private function streams() {
    return json_decode(shell_exec('ffprobe -v quiet -print_format json -show_format -show_streams '.escapeshellarg($this->entry['file'])), true)['streams'];
  }

  private function videoCodecArgs(string $vcodec) {
    switch ($vcodec) {
      case 'intel_h264_vaapi':
          return [
            'args' => [
              'vf' => "format='nv12|vaapi,hwupload'",
              'vaapi_device' => '/dev/dri/renderD128',
              'hwaccel' => 'vaapi',
              'hwaccel_output_format' => 'vaapi',
              'vcodec' => 'h264_vaapi',
              'profile:v' => 100,
              'level:v' => 42,
              'f' => 'mp4'
            ],
            'fallback' => 'libx264',
            'ext' => '.mp4'
          ];
        break;
      case 'libx264':
        return [
          'args' => [
            'vcodec' => 'libx264',
            'profile:v' => 'high',
            'level:v' => '4.2',
            'f' => 'mp4'
          ],
          'unset' => ['vaapi_device','hwaccel','hwaccel_output_format'],
          'ext' => '.mp4'
        ];
        break;
    }
  }

  private function buildEncodeArgs(array $overrule) {
    $args = [
      'args' => [
        'y' => null,
        'xerror' => null,
        'v' => 'quiet',
        'vaapi_device' => null,
        'hwaccel' => 'none',
        'hwaccel_output_format' => null,
        'i' => escapeshellarg($this->entry['file']),
        'vcodec' => 'libx264',
        'profile:v' => 'high',
        'level:v' => '4.2',
        'preset' => 'ultrafast',
        'movflags' => '+faststart',
        'quality' => 2,
        'vf' => null,
        'threads' => 0,
        'acodec' => 'aac',
        'b:a' => '160k',
        'f' => null
      ],
      'unset' => [],
      'fallback' => '',
      'ext' => '.mp4'
    ];

    // Merge arguments
    $argsVcodec = $this->videoCodecArgs($overrule['vcodec']);
    $args = array_replace_recursive($args, $argsVcodec);
    $args = array_replace_recursive($args, $overrule);

    // Unset if needed
    if (!empty($args['unset'])) {
      foreach ($args['unset'] as $key) {
        unset($args['args'][$key]);
      }
    }

    // Build Filter
    $args['args']['vf'] = !empty($overrule['args']['vf']) ? $overrule['args']['vf'] : '';
    if (!empty($argsVcodec['args']['vf']))
       $args['args']['vf'] = (!empty($args['args']['vf']) ? $args['args']['vf'] . ',' : '') . $argsVcodec['args']['vf'];

    if (empty($args['args']['vf']))
      unset($args['args']['vf']);

    // Build for command
    $args['cli'] = '';
    foreach ($args['args'] as $key => $value) {
      $args['cli'].= " -$key $value";
    }
    return $args;
  }

  public function start() {
    foreach ($this->queue as $entry) {
      // Set data
      $this->entry = $entry;
      $this->entry['streams'] = $this->streams();

      // Perform task
      foreach ($this->tasks as $task => $args) {
        $method = 'task' .ucfirst($task);
        $this->debug("Start $method");
        $this->{$method}($args);
        $this->debug("End $method");
      }
    }
  }

  private function taskExtract(array $args) {
    if (!empty($this->entry['streams'])) {
      foreach ($this->entry['streams'] as $stream) {
        if (empty($args[$stream['codec_type']]))
          continue;

        // Filter
        $codec = strtolower($stream['codec_name']);
        $tags = is_array($stream['tags']) ? array_change_key_case($stream['tags']) : [];
        $language = preg_replace('/[^a-zA-Z]+/', '', $tags['language']) ?: 'unknown';

        // Check Locale
        if (!stristr($args[$stream['codec_type']], $language))
          continue;

        // On Subtitle
        if ($stream['codec_type'] == 'subtitle')
          $codec = in_array($codec, ['ass','ssa','srt','sub']) ? $codec : 'srt';

        // Extract
        $out = $this->path['base'] . $this->entry['path']['filename'] . "_ext_$language-" . $stream['index'] . ".$codec";
        shell_exec(sprintf('ffmpeg -y -xerror -v quiet -threads 0 -i %s -map 0:%d %s', escapeshellarg($this->entry['file']), $stream['index'], escapeshellarg($out)));
      }
    }
    return $this;
  }

  private function localeMatches(string $locales, string $exts) {
    $matches = [];
    foreach (glob($this->path['base'] . $this->entry['path']['filename'] . '_ext_{'.$locales.'}*.{'.$exts.'}', GLOB_BRACE) as $file) {
      $matches[] = $file;
    }
    return $matches;
  }

  private function debug(string $str) {
    $str = implode('|', [date('d-m-Y H:i:s'), $this->entry['file'], $str]);
    file_put_contents($this->path['tmp'] . 'debug.log', $str . "\n", FILE_APPEND | LOCK_EX);
    return $this;
  }

  private function validate() {
    $log = file_get_contents($this->path['tmp'] . 'ffreport.log') ?: 'Conversion failed';
    if (!stristr($log, 'No VAAPI support') && !stristr($log, 'Conversion failed') && !stristr($log, 'Error opening filters') && !stristr($log, 'Invalid data found when processing input')) {
      // Validate duration
      preg_match("/Duration: (.*?), start:/", $log, $duration);
      preg_match_all("/time=(.*?) bitrate/", $log, $time);

      $duration = substr($duration[1], 0, 8);
      $time = substr(end($time[1]), 0, 8);
      if ($duration != $time) {
        $this->debug("Duration mismatch ($duration <> $time)");
        return false;
      }
      return true;
    }
    return false;
  }

  private function execEncode(array $args) {
    // Set arguments
    $out = $this->path['out'] . $this->entry['path']['filename'];
    $args = $this->buildEncodeArgs($args);

    // Execute ffmpeg
    putenv('FFREPORT=file='. $this->path['tmp'] . 'ffreport.log:level=32');
    shell_exec(sprintf('ffmpeg' . $args['cli'] . ' %s', escapeshellarg($out . $args['ext'])));
    putenv('FFREPORT');
    return $args;
  }

  private function taskEncode(array $args) {
    // Burn-In Subtitle
    if (!empty($args['burn-in'])) {
      foreach ($this->localeMatches($args['burn-in'], 'ass,ssa,srt,sub') as $file) {
        if (shell_exec('wc -l < '.escapeshellarg($file)) > 150) {
          $args['args']['vf'] = 'subtitles='.escapeshellarg($file);
          break;
        }
      }
    }

    // Execute encoding
    $encode = $this->execEncode($args);
    if (!$this->validate() && !empty($encode['fallback'])) {
      $args['vcodec'] = $encode['fallback'];
      $this->debug('Use fallback')->execEncode($args);
    }

    // Rename file
    if (!$this->validate()) {
      rename($this->entry['file'], $this->entry['file'] . '.failed');
    }
    else {
      rename($this->entry['file'], $this->entry['file'] . '.done');
    }

    return $this;
  }

  public function taskImages(array $args) {
    $args = array_replace(['threads' => 0, 'size' => '319x180', 'images' => '60', 'qscale' => 10, 'mode' => 'concatenate', 'tile' => 3, 'quality' => 75, 'delay' => 60, 'exts' => 'mp4'], $args);
    $out = $this->path['out'] . $this->entry['path']['filename'];
    $scan = escapeshellarg($this->path['tmp'].'*.jpg');
    foreach (glob($out . '.{'.$args['exts'].'}', GLOB_BRACE) as $file) {
      // Clean-up
      array_map('unlink', glob($this->path['tmp'] . '*.jpg'));

      // Create images
      $duration = ceil(shell_exec(sprintf('ffprobe -v error -select_streams v:0 -show_entries stream=duration -of default=noprint_wrappers=1:nokey=1 %s', escapeshellarg($file))));
      shell_exec(sprintf('ffmpeg -v quiet -xerror -threads %d -ss 00:00:30 -i %s -t %d -an -s %s -vf fps=1/%d -qscale:v %d %s', $args['threads'], escapeshellarg($file), $duration - 15, $args['size'], $duration / 15, $args['qscale'], escapeshellarg($this->path['tmp'] . '%03d.jpg')));
      shell_exec(sprintf('montage -border 0 -trim -crop 957x900 -mode %s -quality %d -tile %dx %s %s', $args['mode'], $args['quality'], $args['tile'], $scan, escapeshellarg("$out.jpg")));
      shell_exec(sprintf('convert -delay %d -trim -loop 0 -quality %d %s %s', $args['delay'], $args['quality'], $scan, escapeshellarg("$out.gif")));
    }
    return $this;
  }

  public function extract(array $args) {
    $this->tasks['extract'] = $args;
    return $this;
  }

  public function encode(array $args = ['vcodec' => 'libx264']) {
    $this->tasks['encode'] = $args;
    return $this;
  }

  public function images(array $args = []) {
    $this->tasks['images'] = $args;
    return $this;
  }
}
