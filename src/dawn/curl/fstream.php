<?php
namespace tal\dawn\curl;

class fstream implements stream {
  public function __construct($file, $seek = null, $len = null, $mode = "rb") {
    $this->handle = @fopen($file, $mode);
    if ($this->handle === false) {
      $this->error = "file://" . $file . " open failed!";
      return;
    }

    $stat = fstat($this->handle);
    $flen = 0;
    if ($stat === false || !isset($stat['size'])) {
      $this->error = "file://" . $file . " get file length by calling fstat() failed!";
      return;
    }
    $flen = $stat['size'];

    $pos = 0;
    if ($seek)
      $pos = $seek;

    if ($pos > 0) {
      if ($this->seek($pos) === false) {
        $this->error = "file://" . $file . " seek() to " . $pos . " failed!";
        return;
      }
    }

    // left.
    $this->left = 0;
    if ($flen > $pos)
      $this->left = $flen - $pos;
    
    if ($len && $len < $this->left)
      $this->left = $len;

  }

  public function __destruct() {
    if ($this->handle)
      fclose($this->handle);
  }

  public function ready() {
    return !isset($this->error);
  }

  public function get_error() {
    return $this->error;
  }

  public function length() {
    return $this->left;
  }

  // return true/false for succeed/failed.
  public function seek($pos) {
    return fseek($this->handle, $pos, SEEK_SET) === 0;
  }

  public function read($len = null) {
    if ($this->left == 0)
      return '';

    if (empty($len))
      $len = $this->left;
    elseif ($this->left < $len)
      $len = $this->left;

    $data = fread($this->handle, $len);
    $this->left -= strlen($data);

    return $data;
  }

  // return 0/len for failed/succeed.
  public function write($data) {
    $len = fwrite($this->handle, $data);
    if ($len === false)
      return 0;

    return $len;
  }
  private $handle;
  private $left;
  private $error = null;
}
