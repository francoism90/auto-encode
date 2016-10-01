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
      rename($file, $this->path['base'] . preg_replace('/[^A-Za-z0-9\. -()]/', '', basename($file)));

      // Add to queue
      $this->queue[] = ['file' => $file, 'path' => pathinfo($file), 'added' => time(), 'status' => 0];
    }

    // Check we have data
    if (empty($this->queue))
      exit('No files to process');
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
              'vcodec' => 'h264_vaapi'
            ],
            'unset' => ['preset'],
            'fallback' => 'libx264',
            'ext' => '.mp4'
          ];
        break;
      case 'libx264':
        return [
          'args' => [
            'vcodec' => 'libx264'
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
        'threads' => 0,
        'vaapi_device' => null,
        'hwaccel' => null,
        'hwaccel_output_format' => null,
        'i' => escapeshellarg($this->entry['file']),
        'vcodec' => 'libx264',
        'vf' => null,
        'qp' => 20,
        'bf' => 2,
        'quality' => 2,
        'preset' => 'veryfast',
        'movflags' => '+faststart'
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
        $this->{'task'.ucfirst($task)}($args);
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
    $path = $this->path['base'] . $this->entry['path']['filename'];
    $matches = [];
    foreach (glob($path . '_ext_{'.$locales.'}*.{'.$exts.'}', GLOB_BRACE) as $file) {
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
    if (!stristr($log, 'No VAAPI support') && !stristr($log, 'Conversion failed') && !stristr($log, 'Error opening filters')) {
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

  private function taskEncode(array $args) {
    // Set export
    $out = $this->path['out'] . $this->entry['path']['filename'];
    putenv('FFREPORT=file='. $this->path['tmp'] . 'ffreport.log:level=32');

    // Burn-In Subtitle
    if (!empty($args['burn-in'])) {
      foreach ($this->localeMatches($args['burn-in'], 'ass,ssa,srt,sub') as $file) {
        if (shell_exec('wc -l < '.escapeshellarg($file)) > 150) {
          $args['args']['vf'] = 'subtitles=' . escapeshellarg($file);
          break;
        }
      }
    }

    // Execute encoding
    $encode = $this->buildEncodeArgs($args);
    shell_exec(sprintf('ffmpeg' . $encode['cli'] . ' %s', escapeshellarg($out . $encode['ext'])));
    if (!$this->validate() && !empty($encode['fallback'])) {
      $this->debug('Use fallback');
      $args['vcodec'] = $encode['fallback'];
      $fallback = $this->buildEncodeArgs($args);
      shell_exec(sprintf('ffmpeg' . $fallback['cli'] . ' %s', escapeshellarg($out . $fallback['ext'])));
    }

    // Rename file
    putenv('FFREPORT');
    if (!$this->validate())
      return rename($this->entry['file'], $this->entry['file'] . '.failed');

    return rename($this->entry['file'], $this->entry['file'] . '.done');
  }

  public function taskThumbnail(array $args) {
    foreach (glob($this->path['out'] . $this->entry['path']['filename'] . '*.{'.$args['exts'].'}', GLOB_BRACE) as $file) {
      $out = escapeshellarg($this->path['tmp'] . '%03d.jpg');
      shell_exec(sprintf('rm -rf %s; ffmpeg -v quiet -xerror -threads %d -i %s -an -s %s -vf fps=%s -qscale:v %d %s', escapeshellarg($this->path['tmp'] . '*'), $args['threads'], escapeshellarg($file), $args['size'], $args['fps'], $args['qscale'], $out));
      @copy($this->path['tmp'] . '001.jpg', $this->path['out'] . $this->entry['path']['filename'] . '-thumb.jpg');
      @copy($this->path['tmp'] . '002.jpg', $this->path['out'] . $this->entry['path']['filename'] . '-thumb.jpg');
    }
  }

  public function taskMontage(array $args) {
    $out = escapeshellarg($this->path['out'] . $this->entry['path']['filename'] . '-screen.jpg');
    shell_exec(sprintf('montage -mode %s -tile %dx %s %s', $args['mode'], $args['tile'], escapeshellarg($this->path['tmp'] . '*.jpg'), $out));
  }

  public function extract(array $args) {
    $this->tasks['extract'] = $args;
    return $this;
  }

  public function encode(array $args = ['vcodec' => 'libx264']) {
    $this->tasks['encode'] = $args;
    return $this;
  }

  public function thumbnail(array $args = ['threads' => 0, 'size' => '480x300', 'fps' => '1/100', 'qscale' => 10, 'exts' => 'mp4']) {
    $this->tasks['thumbnail'] = $args;
    return $this;
  }

  public function montage(array $args = ['mode' => 'concatenate', 'tile' => 3]) {
    $this->tasks['montage'] = $args;
    return $this;
  }
}

$ffmpeg = new FFmpeg('/mnt/data/convert/', '/mnt/data/convert/out/');
$ffmpeg->extract(['subtitle' => 'eng,en,ned,nl,unknown'])
       ->encode(['vcodec' => 'intel_h264_vaapi', 'burn-in' => 'eng,en,ned,nl,unknown', 'args' => ['threads' => 4]])
       ->thumbnail()
       ->montage()
       ->start();
