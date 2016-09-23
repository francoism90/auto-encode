<?php
class Encode {
  private $config = [
    'base' => '/home/archie/encode/', // Use this directory for processing video files
    'target' => '/mnt/data/myvideos/', // Encode and put files in this directory
    'tmp' => '/tmp/encode/', // Temp folder (logging, creating screens, etc.)
    'locales' => ['eng','ned','en','nl','unk'], // Languages to scan for subtitles (in order of importance)
    'subtitles' => ['ass','ssa','srt','sub'] // Valid subtitle-format(s)
  ];
  private $data = [];

  public function __construct() {
    // Create directories
    mkdir($this->config['base'] . 'keep', 0775);
    mkdir($this->config['base'] . 'failed', 0775);

    // Scan directory with following extensions
    foreach (glob($this->config['base'].'*.{avi,divx,flv,m4v,mkv,mov,mp4,mpeg,mpg,ogm,wmv}', GLOB_BRACE) as $file) {
      // Init
      $this->data = ['tmp' => [], 'encoded' => []];
      $this->exec(str_replace('%tmp%', $this->config['tmp'], 'rm -rf %tmp%; mkdir -p %tmp%;'));

      // Set data
      $this->set('tmp', $file);
      $this->set('encoded', $this->config['target'] . $this->get('tmp')['path']['filename'] . '.mp4');

      // Tasks to execute
      $this->debug("Processing $file");
      if (!file_exists($this->get('encoded')['fullpath']))
        $this->debug('Encoding started')->extract()->encode()->debug('Encoding finished');
      elseif (!$this->exists('-thumb.jpg') || !$this->exists('-screen.jpg'))
        $this->debug('Creating images')->images()->debug('Images created');
      else
        $this->debug('Moving files')->move();
    }
  }

  public function set(string $key, string $path) {
    $this->data[$key] = ['fullpath' => $path, 'path' => pathinfo($path), 'info' => $this->ffprobe($path)];
  }

  public function exists(string $file, string $path = 'target') {
    return file_exists($this->config[$path] . $this->get('tmp')['path']['filename'] . $file);
  }

  public function debug(string $str) {
    echo date('d-m-Y H:i:s')."|$str\n";
    return $this;
  }

  public function exec(string $str) {
    return shell_exec($str);
  }

  public function get(string $key) {
    return $this->data[$key];
  }

  public function ffprobe(string $path) {
    return file_exists($path) ? json_decode($this->exec('ffprobe -v error -print_format json -show_format -show_streams '.escapeshellarg($path)), true) : [];
  }

  public function extract() {
    foreach ($this->get('tmp')['info']['streams'] as $stream) {
      // Skip non-subtitle stream
      if ($stream['codec_type'] != 'subtitle')
        continue;

      // Validate codec
      $format = strtolower($stream['codec_name']);
      $format = in_array($format, $this->config['subtitles']) ? $format : 'srt'; // fallback srt on non format match

      // Get stream language tag
      $stream['tags'] = is_array($stream['tags']) ? array_change_key_case($stream['tags']) : []; // Tag keys may differ
      $language = preg_replace('/[^a-zA-Z0-9]+/', '', $stream['tags']['language']) ?: 'unknown'; // Strip invalid chars

      // Debug
      $this->debug("Found subtitle ($language)");

      // Extract subtitle
      $path = $this->config['base'] . $this->get('tmp')['path']['filename'] . '_' . $stream['index'] . "$language.$format";
      $this->exec(sprintf("ffmpeg -y -v error -threads 4 -i %s -map 0:%s %s", escapeshellarg($this->get('tmp')['fullpath']), $stream['index'], escapeshellarg($path)));
    }
    return $this;
  }

  public function encode() {
    // Get Subtitle (if exists)
    $subtitle = '';
    foreach (glob(sprintf('%s_*.{%s}', $this->config['base'] . $this->get('tmp')['path']['filename'], implode(',', $this->config['subtitles'])), GLOB_BRACE) as $file) {
      foreach ($this->config['locales'] as $locale) { // find first match
        // Matches locale and contains 150> lines (skip chapter-subtitle, etc.)
        if (stristr($file, $locale) && $this->exec('wc -l < '.escapeshellarg($file)) > 150) {
          $this->debug("Burn-In subtitle ($file)");
          $subtitle = ' subtitles='.escapeshellarg($file);
          break;
        }
      }
    }

    // Try HW-Acceleration encoding
    $cmd = "FFREPORT=file=%s:level=32 ffmpeg -y -report -v quiet -hide_banner -xerror -nostdin -threads 4 -vaapi_device /dev/dri/renderD128 -hwaccel vaapi -hwaccel_output_format vaapi -i %s -vf format='nv12|vaapi,hwupload' $subtitle -c:v h264_vaapi -qp 20 -bf 2 -quality 2 -movflags +faststart -c:a aac -b:a 128k -f mp4 %s";
    $cmd = sprintf($cmd, escapeshellarg($this->config['tmp'] . 'ffreport.log'), escapeshellarg($this->get('tmp')['fullpath']), escapeshellarg($this->get('encoded')['fullpath']));
    $this->exec($cmd);

    // Fallback on safe-mode
    $out = file_get_contents($this->config['tmp'] . 'ffreport.log');
    if (stristr($out, 'No VAAPI support') || stristr($out, 'Conversion failed')) {
      $cmd = str_replace(['-vaapi_device /dev/dri/renderD128 -hwaccel vaapi -hwaccel_output_format vaapi',"-vf format='nv12|vaapi,hwupload'",'h264_vaapi','-quality 2'], ['', (!empty($subtitle)) ? "-vf $subtitle" : '' ,'libx264','-preset veryfast'], $cmd);
      $this->debug('Fallback mode')->exec($cmd);
    }

    // Conversion failed completely
    if (stristr(file_get_contents($this->config['tmp'] . 'ffreport.log'), 'Conversion failed')) {
      $this->debug('Conversion failed')->move('failed')->exec(sprintf('rm -rf %s', escapeshellarg($this->get('encoded')['fullpath'])));
    }
    return $this;
  }

  public function images() {
    $cmd = 'ffmpeg -v error -xerror -threads 4 -i %target%.mp4 -an -s 480x300 -vf fps=1/100 -qscale:v 10 %tmp%1-%03d.jpg && ';
    $cmd.= 'montage -mode concatenate -tile 4x %tmp%1-*.jpg %target%-screen.jpg && ';
    $cmd.= 'cp -f %tmp%1-001.jpg %target%-thumb.jpg; cp -f %tmp%1-002.jpg %target%-thumb.jpg 2>&1;';
    $this->exec(str_replace(['%tmp%','%target%'], [escapeshellarg($this->config['tmp']), escapeshellarg($this->config['target'] . $this->get('tmp')['path']['filename'])], $cmd));
    return $this;
  }

  public function move(string $path = 'keep') {
    $this->exec(str_replace(['%path%','%filename%'], [escapeshellarg($this->config['base'] . $path), escapeshellarg($this->config['base'] . $this->get('tmp')['path']['filename'])], 'mv -t %path% %filename%.* %filename%_* 2>&1'));
    return $this;
  }
}

new Encode();
