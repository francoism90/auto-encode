<?php
class Thumbs
{
  private $config = [
    'target' => null,
    'tmp' => '/tmp/convert',
    'thumbs' => 55,
    'thumbsize' => '320x180',
    'end_offset' => 25,
    'border' => 0,
    'mode' => 'concatenate',
    'tile' => 5,
    'delay' => 85,
    'force' => false,
    'bin' => [
      'ffprobe' => '/usr/bin/ffprobe',
      'mpv' => '/usr/bin/mpv',
      'mogrify' => '/usr/bin/mogrify',
      'jpegoptim' => '/usr/bin/jpegoptim',
      'montage' => '/usr/bin/montage',
      'convert' => '/usr/bin/convert',
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
    $output = "{$this->config['target']}/{$this->input['filename']}.jpg";
    $duration = floor($this->duration($this->input['path']) - $this->config['end_offset']);
    if (($this->config['force'] || !file_exists($output)) && $duration > 5) {
      $splice = floor($duration / $this->config['thumbs']);
      $time = $splice;

      // Loop
      for ($i = 1; $i <= $this->config['thumbs']; $i++) {
        // Create thumb
        $args = "--quiet --no-audio --vo=image --frames=1 ";
        $args.= "-vo-image-outdir={$this->config['tmp']} --start=$time ";
        $args.= escapeshellarg($this->input['path']);
        $this->execute('mpv', $args);

        // Rename
        rename("{$this->config['tmp']}/00000001.jpg", "{$this->config['tmp']}/i-".sprintf("%02d", $i).'.jpg');
        if ($i < $this->config['thumbs'])
          $time += $splice;
      }

      // Resize
      $args = "-resize {$this->config['thumbsize']} -strip -trim ";
      $args.= escapeshellarg("{$this->config['tmp']}/i-*.jpg");
      $this->execute('mogrify', $args);

      // Optimize
      $this->optimize("{$this->config['tmp']}/i-*.jpg");
    }
    return $this;
  }

  public function screen() {
    if (file_exists("{$this->config['tmp']}/i-{$this->config['thumbs']}.jpg")) {
      $output = "{$this->config['target']}/{$this->input['filename']}.jpg";

      // Create Screenshot
      $args = "-strip -border {$this->config['border']} ";
      $args.= "-mode {$this->config['mode']} -tile {$this->config['tile']} ";
      $args.= escapeshellarg("{$this->config['tmp']}/i-*.jpg").' '.escapeshellarg($output);
      $this->execute('montage', $args);

      // Optimize
      $this->optimize($output);
    }
    return $this;
  }

  public function animation() {
    if (file_exists("{$this->config['tmp']}/i-{$this->config['thumbs']}.jpg")) {
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
    $args = "-v error -analyzeduration 2147483647 -probesize 2147483647 ";
    $args.= "-select_streams v:0 -show_entries stream=duration ";
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
