<?php
namespace tal\dawn\http;

use tal\dawn\curl\fstream;
use tal\dawn\curl\mstream;

define('GET', 'GET');
define('POST', 'POST');
define('PUT', 'PUT');
define('DELETE', 'DELETE');
define('HEAD', 'HEAD');

class Request {
  public function __construct($url = null) {
    $this->url = $url;
    $this->method = GET;
    $this->headers = array();
  }

  // add request header `field: value` format...
  public function add_header($field, $value) {
    $this->headers[$field] = $value;
    return $this;
  }

  // delete request header if headers exist `field`.
  public function del_header($field) {
    if (isset($this->headers[$field]))
      unset($this->headers[$field]);
    return $this;
  }

  // set method
  public function set_method($method) {
    $this->method = strtoupper($method);
    return $this;
  }

  // set url.
  public function set_url($url) {
    $this->url = $url;
    return $this;
  }

  // set body.
  public function set_body($data) {
    $this->body_stream = new mstream($data);
    $this->body_len = strlen($data);
    return $this;
  }

  // set file.
  public function set_body_file($file, $seek = null, $len = null) {
    $this->body_stream = new fstream($file, $seek, $len);
    $this->body_len = $this->body_stream->length();
    return $this;
  }

  public function set_body_stream($stm, $len) {
    $this->body_stream = $stm;
    $this->body_len = $len;
    return $this;
  }

  public function attach($curl) {
    $curl->set_url($this->url);
    $curl->set_method($this->method);
    $curl->set_headers($this->headers);

    if (isset($this->body_stream))
      $curl->set_obody($this->body_stream, $this->body_len);
    $curl->prepare();
  }

  public function send($curl) {
    if ($curl->perform() === false)
      return false;

    return new Response($curl->resp_code(), $curl->resp_headers(), $curl->resp_body());
  }

  public function get_headers() {
    return $this->headers;
  }

  private $url;
  private $headers;
  private $method;

  private $body_stream = null;
  private $body_len = 0;

}
