<?php
namespace tal\dawn\http;

class Response {
  public function __construct($code, $headers, $body = null) {
    $this->code = $code;
    $this->headers = $headers;
    $this->body = $body;
  }

  public function get_code() {
    return $this->code;
  }

  public function get_headers() {
    return $this->headers;
  }

  public function get_body() {
    return $this->body;
  }

  public function succeed() {
    return $this->code >= 200 && $this->code < 300;
  }

  private $code;
  private $headers;
  private $body;
}
