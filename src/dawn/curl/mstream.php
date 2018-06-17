<?php
namespace tal\dawn\curl;

class mstream implements stream {
  public function __construct($data = '') {
    $this->data = $data;
    $this->dlen = strlen($data);
    $this->rpos = 0;
  }

  public function ready() {
    return true;
  }

  // return true/false for succeed/failed.
  public function seek($pos) {
    if ($this->dlen < $pos)
      return false;
    $this->rpos = $pos;
    return true;
  }

  public function read($len = null) {
    if ($this->dlen - $this->rpos <= 0)
      return '';

    $left = $this->dlen - $this->rpos;
    if (empty($len))
      $len = $left;
    elseif ($left < $len)
      $len = $left;
    $ret = substr($this->data, $this->rpos, $len);
    $this->rpos += $len;

    return $ret;
  }

  // return 0/len for failed/succeed.
  public function write($data) {
    $this->data .= $data;
    return strlen($data);
  }

  private $data;
  private $dlen;
  private $rpos;
}
