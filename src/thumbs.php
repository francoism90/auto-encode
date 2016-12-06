<?php
class Thumbs
{
  private $config = [
    'target' => null,
    'tmp' => '/tmp/convert',
    'thumbs' => 55,
    'thumbsize' => '320x180',
    'qscale' => 8,
    'end_offset' => 25, // in seconds
    'border' => 0,
    'mode' => 'concatenate',
    'tile' => 5,
    'delay' => 85,
    'force' => false,
    'optimize' => true,
    'bin' => [
      'ffmpeg' => '/usr/bin/ffmpeg',
      'ffprobe' => '/usr/bin/ffprobe',
      'montage' => '/usr/bin/montage',
      'convert' => '/usr/bin/convert',
      'jpegoptim' => '/usr/bin/jpegoptim'
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

    return $this;
  }

  public function thumbs() {
    // Clean-up
    $this->clean();

    // File info
    $input = escapeshellarg($this->input['path']);
    $screen = "{$this->config['target']}/{$this->input['filename']}.jpg";
    $duration = floor($this->duration($this->input['path']) - $this->config['end_offset']);
    if (($this->config['force'] || !file_exists($screen)) && $duration > 5) {
      // Splice video
      $splice = floor($duration / $this->config['thumbs']);
      $time = $splice;

      // Loop
      for ($i = 1; $i <= $this->config['thumbs']; $i++) {
        // Time format for ffmpeg
        $format = gmdate('H:i:s', $time);
        $output = "{$this->config['tmp']}/i-".sprintf("%03d", $i).".jpg";

        // Create thumb
        $args = "-y -v error -threads 0 -timelimit 60 -ss $format -i $input ";
        $args.= "-to $format -an -vframes 1 -s {$this->config['thumbsize']} ";
        $args.= "-qscale:v {$this->config['qscale']} ";
        $args.= escapeshellarg($output);
        $this->execute('ffmpeg', $args);

        // Increase for next part
        $time += $splice;
      }

      // Optimize
      $this->optimize("{$this->config['tmp']}/i-*.jpg");
    }
    return $this;
  }

  public function screen() {
    if (file_exists("{$this->config['tmp']}/i-001.jpg")) {
      $output = "{$this->config['target']}/{$this->input['filename']}.jpg";

      // Create Screenshot
      $args = "-strip -border {$this->config['border']} ";
      $args.= "-mode {$this->config['mode']} -tile {$this->config['tile']} ";
      $args.= escapeshellarg("{$this->config['tmp']}/i-*.jpg").' '.escapeshellarg($output);
      $this->execute('montage', $args);

      // Optimize
      if ($this->config['optimize'])
        $this->optimize($output);
    }
    return $this;
  }

  public function animation() {
    if (file_exists("{$this->config['tmp']}/i-001.jpg")) {
      $output = "{$this->config['target']}/{$this->input['filename']}.gif";

      // Create animation
      $args = "-resize {$this->config['thumbsize']} -delay {$this->config['delay']} ";
      $args.= "-loop 0 -strip -layers OptimizePlus -layers OptimizeTransparency ";
      $args.= escapeshellarg("{$this->config['tmp']}/i-*.jpg").' '.escapeshellarg($output);
      $this->execute('convert', $args);
    }
    return $this;
  }

  private function clean(string $exts = 'gif,jpg') {
    array_map('unlink', glob("{$this->config['tmp']}/*.{".$exts.'}', GLOB_BRACE));
  }

  private function execute(string $cmd, string $args) {
    return shell_exec("{$this->config['bin'][$cmd]} $args 2>&1");
  }

  private function duration(string $path) {
    $args = "-v error -select_streams v:0 -show_entries stream=duration ";
    $args.= "-of default=noprint_wrappers=1:nokey=1 ";
    $args.= escapeshellarg($path);
    return $this->execute('ffprobe', $args);
  }

  private function optimize(string $path) {
    $args = "--quiet --strip-all ";
    $args.= escapeshellarg($path);
    return $this->execute('jpegoptim', $args);
  }
}
