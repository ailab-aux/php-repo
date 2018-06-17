<?php
namespace tal\dawn\curl;

class cURL {
  public function __construct() {
    $this->handle = curl_init();
  }

  public function __destruct() {
    curl_close($this->handle);
  }

  public function set_method($method) {
    if (isset($method)) {
      $method = strtoupper($method);
      if ($method === 'GET' || $method === 'PUT' || $method === 'POST' ||
        $method === 'HEAD' || $method === 'DELETE' || $method === 'OPTIONS')
        $this->method = $method;
    }
    return $this;
  }

  public function set_url($url) {
    $this->url = $url;
    return $this;
  }

  public function set_headers($headers) {
    $this->headers = $headers;
    return $this;
  }

  public function set_obody($stream, $len) {
    $this->obody = $stream;
    $this->obody_len = $len;
    return $this;
  }

  public function set_ibody($stream, $len) {
    $this->ibody = $stream;
    $this->ibody_len = $len;
    return $this;
  }

  // curl option set...

  public function set_connect_timeout($seconds) {
    $this->conn_timeout = $seconds;
    return $this;
  }

  public function set_progress_callback($progress, $data) {
    $this->progress = $progress;
    $this->progress_data = $data;
    return $this;
  }

  public function set_timeout($seconds) {
    $this->timeout = $seconds;
    return $this;
  }

  public function set_max_send_speed($bytes_per_second) {
    $this->max_send_speed = $bytes_per_second;
    return $this;
  }
  public function set_max_recv_speed($bytes_per_second) {
    $this->max_recv_speed = $bytes_per_second;
    return $this;
  }

  public function set_low_speed_limit($low_speed, $low_speed_time) {
    $this->low_speed = $low_speed;
    $this->low_speed_time = $low_speed_time;
    return $this;
  }

  // call backs...
  public function header_callback($handle, $data) {
    // header callback's data format: <field>: <value>
    $kv = explode(': ', $data);
    if ($kv && isset($kv[1])) {
      $k = trim($kv[0]);
      $v = isset($kv[1]) ? trim($kv[1]) : '';
      $this->response_headers[strtolower($k)] = $v;
    }

    // set input body len.
    if (array_key_exists('content-length', $this->response_headers)) {
      $len = intval($this->response_headers['content-length']);
      $this->ibody_len = $len;
    }

    return strlen($data);
  }

  public function read_callback($handle, $resouce, $len) {
    if ($this->transfered >= $this->obody_len)
      return '';

    $data = $this->obody->read($len);
    $this->transfered += strlen($data);

    if ($this->progress) {
      call_user_func($this->progress, $this->obody_len, $this->transfered, $this->progress_data);
    }

    return ($data === false ? '' : $data);
  }

  public function write_callback($handle, $data) {
    $code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
    if ($code < 200 || $code >= 300) {
      $this->response_body .= $data;
      return strlen($data);
    }

    $this->response_body .= $data;
    $len = strlen($data);
    if (isset($this->ibody))
      $len = $this->ibody->write($data);

    $this->transfered += $len;

    if ($this->progress) {
      call_user_func($this->progress, $this->ibody_len, $this->transfered, $this->progress_data);
    }

    return $len;
  }

  public function set_default() {
    //curl default value.
    curl_setopt($this->handle, CURLOPT_FILETIME, true);
    curl_setopt($this->handle, CURLOPT_FRESH_CONNECT, false);
    curl_setopt($this->handle, CURLOPT_MAXREDIRS, 5);
    // header as output or not, call curl_exec() return if set true.
    curl_setopt($this->handle, CURLOPT_HEADER, false);
    // call curl_exec() for stream not stdout.
    curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->handle, CURLOPT_NOSIGNAL, true);
  }

  public function prepare() {
    // Set default options.
    curl_setopt($this->handle, CURLOPT_URL, $this->url);
    curl_setopt($this->handle, CURLOPT_REFERER, $this->url);

    // timeout.
    if (isset($this->timeout) && $this->timeout > 0)
      curl_setopt($this->handle, CURLOPT_TIMEOUT, $this->timeout);
    if (isset($this->conn_timeout) && $this->conn_timeout > 0)
      curl_setopt($this->handle, CURLOPT_CONNECTTIMEOUT, $this->conn_timeout);

    // speed limit.
    if (isset($this->max_send_speed) && $this->max_send_speed > 0)
      curl_setopt($this->handle, CURLOPT_MAX_SEND_SPEED_LARGE, $this->max_send_speed);
    if (isset($this->max_recv_speed) && $this->max_recv_speed > 0)
      curl_setopt($this->handle, CURLOPT_MAX_RECV_SPEED_LARGE, $this->max_recv_speed);

    // low speed limited.
    if (isset($this->low_speed) && $this->low_speed > 0 && isset($this->low_speed_time) && $this->low_speed_time > 0) {
      curl_setopt($this->handle, CURLOPT_LOW_SPEED_TIME, $this->low_speed_time);
      curl_setopt($this->handle, CURLOPT_LOW_SPEED_LIMIT, $this->low_speed);
    }

    //curl_setopt($this->handle, CURLOPT_USERAGENT, $this->useragent);
    curl_setopt($this->handle, CURLOPT_HEADERFUNCTION, array($this, "header_callback"));
    curl_setopt($this->handle, CURLOPT_READFUNCTION, array($this, "read_callback"));
    curl_setopt($this->handle, CURLOPT_WRITEFUNCTION, array($this, "write_callback"));

    if (isset($this->obody) && $this->obody_len > 0)
      curl_setopt($this->handle, CURLOPT_INFILESIZE, $this->obody_len);

    // Process custom headers
    if (isset($this->headers) && count($this->headers)) {
      $headers = array();

      foreach ($this->headers as $k => $v) {
        $headers[] = $k . ': ' . $v;
      }
      curl_setopt($this->handle, CURLOPT_HTTPHEADER, $headers);
    }

    $this->make_method();
  }

  public function perform() {
    $this->transfered = 0;
    $response = curl_exec($this->handle);
    if ($response === false) {
      $this->error = curl_error($this->handle);
      $this->errno = curl_errno($this->handle);
      return false;
    }

    $this->code = intval(curl_getinfo($this->handle, CURLINFO_HTTP_CODE));

    $this->response_info = curl_getinfo($this->handle);
    $this->response_headers['info'] = $this->response_info;
    $this->response_headers['info']['method'] = $this->method;

    return true;
  }

  private function make_method() {
    switch ($this->method) {
    case 'PUT':
      curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, 'PUT');
      curl_setopt($this->handle, CURLOPT_UPLOAD, true);
      break;
    case 'POST':
      curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, 'POST');
      if (isset($this->obody))
        curl_setopt($this->handle, CURLOPT_UPLOAD, true);
      break;
    case 'HEAD':
      curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, 'HEAD');
      curl_setopt($this->handle, CURLOPT_NOBODY, 1);
      break;
    case 'DELETE':
      curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, $this->method);
      break;
    default: // 'get'
      curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, $this->method);
      break;
    }
  }

  public function resp_code() { return $this->code; }
  public function resp_headers() { return $this->response_headers; }
  public function resp_body() { return $this->response_body; }
  public function resp_info() { return $this->response_info; }

  public function get_error() { return $this->error; }
  public function get_errno() { return $this->errno; }


  private $handle;
  private $url;
  private $headers = array();         // like array("field" => "value", ... ) format.
  private $obody = null;
  private $obody_len = 0;
  private $ibody = null;
  private $ibody_len = 0;
  private $transfered = 0;

  private $method = 'get';

  private $conn_timeout = null;
  private $timeout = null;

  private $progress = null;
  private $progress_data = null;

  private $max_send_speed = null;
  private $max_recv_speed = null;

  private $low_speed_time = null;
  private $low_speed = null;

  private $code = null;
  private $response_headers = array();
  private $response_body = '';
  private $response_info = array();

  private $error = null;
  private $errno = 0;
}
