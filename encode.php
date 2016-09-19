<?php
class Encode {
  private $config = [
    'base' => '/home/archie/encode/',
    'target' => '/home/archie/encoded/',
    'tmp' => '/tmp/conv/',
    'locales' => ['eng','ned','en','nl','unk'],
    'subtitles' => ['ass','ssa','srt','sub'],
  ];
  private $data = ['tmp' => [], 'encoded' => []];

  public function __construct() {
    // Create required directories
    mkdir($this->config['tmp'], 0770);
    mkdir($this->config['base'] . 'done', 0770);

    // Scan directory
    foreach (glob($this->config['base'].'*.{mp4,mkv,wmv,mpeg,mpeg,avi,ogm,divx,mov,m4v,flv}', GLOB_BRACE) as $file) {
      // Set Data
      $this->set('tmp', $file);
      $this->set('encoded', $this->config['target'] . $this->get('tmp')['path']['filename'] . '.mp4');

      // Is already encoded and valid?
      $this->debug("Processing $file");
      if (!$this->valid()) {
        $this->debug('Encoding');
        $this->extract()->encode();
        continue;
      }

      // Final steps
      $this->debug('Creating Images');
      $this->images()->move();
    }
  }

  public function set(string $key, string $path) {
    $this->data[$key] = ['fullpath' => $path, 'path' => pathinfo($path), 'info' => $this->ffprobe($path)];
  }

  public function debug(string $str) {
    echo date(DATE_RFC2822)." $str\n";
  }

  public function get(string $key) {
    return $this->data[$key];
  }

  public function ffprobe(string $path) {
    return file_exists($path) ? json_decode(shell_exec("ffprobe -v quiet -print_format json -show_format -show_streams $path"), true) : [];
  }

  public function valid() {
    if (!empty($this->get('encoded')['info'])) {
      $original = $this->get('tmp')['info']['format']['duration'];
      $encoded = $this->get('encoded')['info']['format']['duration'];
      $size = 100 *(($encoded - $original) / $original);
      $this->debug("Percentage diff: $size");
      return ($size > -10 && $size < 10); // max. allowed margin
    }
    return false;
  }

  public function extract() {
    foreach ($this->get('tmp')['info']['streams'] as $stream) {
      // Skip non-subtitle stream
      if ($stream['codec_type'] != 'subtitle')
        continue;

      // Validate codec
      $format = strtolower($stream['codec_name']);
      $format = in_array($format, $this->config['subtitles']) ? $format : 'srt';

      // Strip tags
      $stream['tags'] = is_array($stream['tags']) ? array_change_key_case($stream['tags']) : []; // Tags case can differ
      $language = preg_replace('/[^a-zA-Z0-9]+/', '', $stream['tags']['language']) ?: 'unknown';

      // Debug
      $this->debug("Found subtitle ($language)");

      // Extract subtitle
      $path = $this->config['base'] . $this->get('tmp')['path']['filename'] . '_' . $stream['index'] . "$language.$format";
      shell_exec(sprintf('ffmpeg -y -v fatal -threads 0 -i %s -map 0:%s %s', $this->get('tmp')['fullpath'], $stream['index'], $path));
    }
    return $this;
  }

  public function encode() {
    $cmd = sprintf('ffmpeg -y -v fatal -threads 0 -i %s -c:v libx264 -crf 23 -preset ultrafast -c:a aac -b:a 160k -tune zerolatency -movflags +faststart', $this->get('tmp')['fullpath']);
    foreach (glob(sprintf('%s_*.{%s}', $this->config['base'] . $this->get('tmp')['path']['filename'], implode(',', $this->config['subtitles'])), GLOB_BRACE) as $file) {
      foreach ($this->config['locales'] as $locale) { // find first match
        // Matches locale match and contains at least 150 lines (skip chapter-subtitle, etc.)
        if (stristr($file, $locale) && shell_exec("wc -l < $file") > 150) {
          $this->debug("Burn-In subtitle ($file)");
          $cmd.= " -vf subtitles=$file";
          break;
        }
      }
    }
    shell_exec(sprintf("$cmd %s", $this->get('encoded')['fullpath']));
    return $this;
  }

  public function images() {
    $cmd = 'rm -rf %tmp%*; ffmpeg -threads 0 -v fatal -i %target%.mp4 -an -s 480x300 -vf fps=1/100 -qscale:v 10 %tmp%1-%03d.jpg;';
    $cmd.= 'cp -f %tmp%1-001.jpg %target%-thumb.jpg; cp -f %tmp%1-002.jpg %target%-thumb.jpg;';
    $cmd.= 'montage -mode concatenate -tile 4x %tmp%1-*.jpg %target%-screen.jpg';
    shell_exec(str_replace(['%tmp%','%target%'], [$this->config['tmp'], $this->config['target'] . $this->get('tmp')['path']['filename']], $cmd));
    return $this;
  }

  public function move() {
    shell_exec(str_replace(['%keep%','%filename%'], [$this->config['base'] . 'done', $this->config['base'] . $this->get('tmp')['path']['filename']], 'mv -t %keep% %filename%.* %filename%_*'));
    return $this;
  }
}

new Encode();
