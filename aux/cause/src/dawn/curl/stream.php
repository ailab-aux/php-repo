<?php
namespace tal\dawn\curl;

interface stream {
  public function ready();
  public function read($len = null);
  public function write($data);
}
