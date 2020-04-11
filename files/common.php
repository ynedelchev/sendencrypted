<?php
require_once(dirname(__DIR__)."/maxsize.php");
$json_encode_props = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR;

function getheader($name) {
  $upper = str_replace('-', '_', strtoupper($name));
  if (isset($_SERVER['HTTP_'.$upper]) && $_SERVER['HTTP_'.$upper] != null) {
    return $_SERVER['HTTP_'.$upper];
  }
  if (isset($_SERVER['REDIRECT_HTTP_'.$upper]) && $_SERVER['REDIRECT_HTTP_'.$upper] != null) {
    return $_SERVER['REDIRECT_HTTP_'.$upper];
  }
  $apache = apache_request_headers();
  if (isset($apache) && $apache != null) {
    if (isset($apache) && $apache != null && isset($apache[$name]) && $apache[$name] != null) {
      return $apache[$name];
    }
    $lower = strtolower($name);
    if (isset($apache) && $apache != null && isset($apache[$lower]) && $apache[$lower] != null) {
      return $apache[$lower];
    }
    foreach ($apache as $header => $value) {
      $hdr = strtolower($header);
      if ($hdr == $lower) {
        return $value;
      }
    }
  }
  return null;
}

function base64($str) {
  $encoded = base64_encode($str);
  $encoded = str_replace('/', '-', $encoded);
  return $encoded;
}
function randomstr($size) {
  $str = '';
  for ($i =0; $i < $size; $i++) {
    $ch = chr(rand(32, 127));
    $str .= $ch;
  }
  return $str;
}

function auth_verify() {
  $jwt = getheader('Authorization');
  $authentication = explode(' ', $jwt, 2);
  if ($authentication == null || !isset($authentication[0]) || !isset($authentication[1]) || strtolower($authentication[0]) != 'bearer') {
    $m = 'Value of Authorization header should start with Bearer and should be followed by authorization key, token but it is not.';
    $errid = base64(randomstr(32));
    return array('error'=>$m, 'errid'=>$errid);
  }
  $jwt = $authentication[1]; $authentication = null;


  if ($jwt == null || $jwt == '') {
    $m = 'Request does not define authentication key in the Authorization HTTP Header.';
    $errid = base64(randomstr(32));
    return array('error'=>$m, 'errid'=>$errid);
  }

  if ($jwt == "test" || $jwt == "c2VuZC1lbmNyeXB0ZWQtYXV0aGVudGljYXRpb24ta2V5") {
    return array('user'=>$jwt);
  } else {
    $m = "Invalid authentication id specified: $jwt";
    return array('error'=>$m, 'errid'=>$errid);
  }
}

function http_exit($code, $msg, $internal, $fileid) {
    global $maxsizesupported, $json_encode_props; 
    $errid = base64(randomstr(32));
    $fileid = $fileid == null ? '/UNSPECIFIED/' : $fileid;
    $msg = $msg == null ? "" : $msg;
    http_response_code($code);
    $internal = $internal == null ? $msg : $internal;
    error_log($internal.'; Error ID: '.$errid.'; File Id: '.$fileid);
    $msg = $msg.'; Error ID: '.$errid;
    header('Content-Type: application/json');
    header('X-Max-Length-Supported: '.$maxsizesupported);
    header('X-Error-Id: '.$errid);
    $obj = array("code"=>$code, "message"=>$msg, 'errid'=>$errid);
    echo json_encode($obj, $json_encode_props)."\n";
    exit(0);
}

function rest() {
  $method = isset($_SERVER["REQUEST_METHOD"]) ? $_SERVER["REQUEST_METHOD"] : "";
  $user = auth_verify();
  if (!isset($user['user'])) {
    http_exit(401, $user['error']." - ".$user['errid']);
  }
  if ($method == "GET") {
    get();
  } else if ($method == "POST") {
    post();
  } else if ($method == "PUT") {
    put();
  } else if ($method == "DELETE") {
    del();
  } else {
    http_exit(405, "Method not allowed. Allowed methods are 'GET', 'POST', 'PUT' and 'DELETE'.");
  }
}

?>
