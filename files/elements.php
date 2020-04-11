<?php
require_once("common.php");

$id    = isset($_SERVER["REDIRECT_ELEMENT"])    ? $_SERVER["REDIRECT_ELEMENT"]    : null;

function get() {
  global $id, $json_encode_props, $maxsizesupported;
  $data = __DIR__.'/'.$id.'.data';
  $json = __DIR__.'/'.$id.'.json';
  if (!file_exists($data)) {
    http_exit(404, 'File not found', 'File "'.$data.'" is not found. Cannot download it.', $id);
  }
  if (!file_exists($data)) {
    http_exit(404, 'File not found', 'File "'.$data.'" is available, but a corresponding metadata file "'.$json.'" cannot be found.', $id);
  }
  $meta_content = file_get_contents($json);
  if ($meta_content === FALSE) {
    http_exit(500, 'Internal Server Error', 'Cannot read metadata file "'.$json.'" that corresponds to a data file "'.$data.'".', $id); 
  }
  $meta = json_decode($meta_content, TRUE, 7);
  if ($meta == NULL) {
    http_exit(500, 'Internal Server Error', 'Error while parsing JSON content of metadata file "'.$json.'" that corresponds to a data file "'.$data.'".'
                   .'Either cannot decode raw content or the recursion recursion level is more than 7. Raw content is: '.$meta_content, $id);
  }
  $downloads = isset($meta['downloads']) ? $meta['downloads'] : 0x7FFFFFFF;
  $allowed   = isset($meta['allowed-downloads']) ? $meta['allowed-downloads'] : 0;
  $status    = isset($meta['status']) ?  $meta['status']  : 'unknown';
  $expires   = isset($meta['expires']) ? $meta['expires'] : 0;
  $expires   = intval($expires/1000);
  if ($status == 'uploading' || $status == 'unknown') {
    http_exit(409, 'Coflict', 'Invalid state "'.$status.'" read from metadata file "'.$json.'" for a data file "'.$data
          . '". Data file could be still being written to. Need to wait till the status is "ready".', $id);
  }
  if ($downloads >= $allowed) {
     $o = array('status'=>'downloaded', 'allowed-downloads'=>$allowed, 'expires'=>($expires*1000), 'message'=>"Maximum number of allowed downloads $allowed for this file reached.");
     echo json_encode($o, $json_encode_props)."\n";
     exit(0);
  }
  $now = time(); 
  if ($now > $expires) {
     $now  = date("Y-m-d H:i s (ГринуичP)", $now);
     $expired  = date("Y-m-d H:i s (ГринуичP)", $expires);
     $o = array('status'=>'expired', 'allowed-downloads'=>$allowed, 'expires'=>($expires*1000), 'message'=>"File expired on ".$expired.". It is currently ".$now.".");
     echo json_encode($o, $json_encode_props)."\n";
     exit(0);
  }
  
  $downloads++;
  $meta['downloads'] = $downloads;
  $result = file_put_contents($json, json_encode($meta, $json_encode_props)."\n");
  if ($result === FALSE) {
    $errid = $errid = base64(randomstr(32));
    http_exit(500, 'Internal Server Error', 'Cannot update metada with the new number of downloads (incremented) : '.$downloads.'. Metadata file "'.$json.'".', $id);
  }
  $size = filesize($data);
  if ($size === FALSE) {
    http_exit(500, 'Internal Server Error', 'Cannot determine the size (content length in bytes) of data file "'.$data.'". Plese check that the file exists and is readable and the directory that contains it has read permissions (can list files/dirs inside it.', $errid);
  }
  $md5 = isset($meta['md5']) ? $meta['md5'] : null;
  header('Content-Type: application/octet-stream');
  header('Content-Length: '.$size);
  header('X-Max-Length-Supported: '.$maxsizesupported);
  header('X-File-Id: '.$id);
  header('X-Download: '.$downloads);
  header('X-Allowed-Downloads: '.$allowed);
  header('X-Expires: '. $expires);
  header('X-Expires-Date: '.date("Y-m-d H:i s (P)", $expires));
  if ($md5 != null) {
    header('X-File-Checksum: '.$md5);
  }
  
  $fh = fopen($data, 'r');
  if ($fh === FALSE) {
    http_exit(500, 'Internal Server Error', 'Cannot open data file "'.$data.'" for reading. Please check that the file exist and is readable.', $id);
  }  
  $result = flock($fh, LOCK_SH);
  if ($result === FALSE) {
    flock($fh, LOCK_UN);
    fclose($fh);
    http_exit(500, 'Internal Server Error', 'Opened data file "'.$data.'" for reading, but cannot obtain a shared (reader) lock on that file.', $id);
  }
  while (!feof($fh)) {
    $bytes = fread($fh, 8192);
    if ($bytes === FALSE) {
      flock($fh, LOCK_UN);
      fclose($fh);
      http_exit(500, 'Internal Server Error', 'Error while readin from data file "'.$data.'". File has been successfully opene and a shared (reader) lock has been succesfully obtained, but there is an error while reading data from the file.', $id);
    }
    if ($bytes != '') {
      echo $bytes;
    } 
  }
  flock($fh, LOCK_UN);
  fclose($fh);
  exit(0); 
}

function post() {
  global $id;
  global $json_encode_props;
  $o= array('status'=>'unsupported', 'message'=>'Unsupported operation : method POST.');
  echo json_encode($obj, $json_encode_props)."\n";
}

function put() {
  global $id;
  global $json_encode_props;
  $o= array('status'=>'unsupported', 'message'=>'Unsupported operation : method PUT.');
  echo json_encode($obj, $json_encode_props)."\n";
}

function del() {
  global $id;
  global $json_encode_props;
  $o= array('status'=>'unsupported', 'message'=>'Unsupported operation : method DELETE.');
  echo json_encode($obj, $json_encode_props)."\n";
}

rest();
?>
