<?php
require_once("common.php");
$json_encode_props = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR;

function get() {
    header("Location: ".full_url(1).'/', true, 301); 
    exit(0);
}

function post() {
    global $maxsizesupported, $json_encode_props;
    $user = auth_verify();
    $size = getheader('Content-Length');
      if ( isset($size)) {
      $size = intval($size);
      if ($size > $maxsizesupported) {
        http_exit(413, 'Request Entity Too Large. Content too big ('.$size.' bytes). Max supported is : '.$maxsizesupported.'.', null, null);
      } 
    }
    $fileid = base64(randomstr(32));
    $datafile = __DIR__."/".$fileid.".data";
    $metafile = __DIR__."/".$fileid.".json";

    $expireseconds = 60 * 60 * 24 * 7;
    $time = time();
    $expire = $time + $expireseconds;
    $expiredate = date("Y-m-d H:i s (ГринуичP)", $expire);

    $obj = array("id"=> $fileid, 'status'=>'uploading',"md5" =>'', "size" =>0, "downloads" => 0, "allowed-downloads" => 1, "expires" => ($expire*1000) , "expire-date" => $expiredate);
    $meta_content = json_encode($obj, $json_encode_props)."\n";
    file_put_contents($metafile, $meta_content, LOCK_EX);

    $size = 0;
    $md5ctx = hash_init('md5');
    $output = fopen($datafile, "w");
    $fh = fopen("php://input", "r");
    if (FALSE === $fh) {
       http_exit(500, 'Upload of file failed', 'Cannot read bytes from input.', $fileid);
    }
    $lock = flock($output, LOCK_EX);
    if ($lock === FALSE) {
        flock($output, LOCK_UN);
	fclose($output);
	fclose($fh);
        http_exit(500, "Unable to store file.", 'File lock of file "'.$datafile.'" did not work for some reason', $fileid);
    }
    ftruncate($output, 0);
    while (!feof($fh)) {
        $bytes = fread($fh, 8192);
        if ($bytes === FALSE) {
            flock($output, LOCK_UN);
            fclose($fh);
            fclose($output);
            http_exit(500, "Error receiving file content.", "Can no longer read from stream into file \"$datafile\"", $fileid);
        }
        $count = fwrite($output, $bytes); 
        hash_update($md5ctx, $bytes);
        if ($count === FALSE) {
            flock($output, LOCK_UN);
            fclose($fh);
            fclose($output);
            http_exit(500, "Error storing file on server.", 'Some bytes red form input stream ('.strlen($bytes).' bytes), but cannot be written into data file "'.$datafile.'".', $fileid);
        }
        $size += $count; 
        if ($size > $maxsizesupported) {
            flock($output, LOCK_UN);
            fclose($fh);
            fclose($output);
            http_exit(413, 'Request Entity Too Large. Content too big (more than '.($size-1).' bytes). Max supported is : '.$maxsizesupported.'.', 
                      'Whatever has been stored in data file "'.$datafile.'" however still stays there. Please manually remove it or wait untill it expires and is deleted by job.', 
                      $fileid);
        }
    }
    flock($output, LOCK_UN);
    fclose($fh);
    fflush($output);
    fclose($output);
    $md5 = hash_final($md5ctx);

    $time = time();
    $expire = $time + $expireseconds;
    $expiredate = date("Y-m-d H:i s (ГринуичP)", $expire);

    $obj = array("id"=> $fileid, 'status'=>'ready',"md5" => $md5, "size" => $size, "downloads" => 0, "allowed-downloads" => 1, "expires" => ($expire*1000) , "expire-date" => $expiredate);
    $meta_content = json_encode($obj, $json_encode_props)."\n";
    file_put_contents($metafile, $meta_content);
    header('Content-Type: application/json');
    header('Content-Length: '.strlen($output));
    header('X-Max-Length-Supported: '.$maxsizesupported);
    header('X-File-Id: '.$fileid);
    header('X-Download: 0');
    header('X-Allowed-Downloads: 1');
    header('X-Expires: '. ($expire*1000));
    header('X-Expires-Date: '.date("Y-m-d H:i s (P)", $expire));
    header('X-File-Checksum: '.$md5);
    echo $meta_content;
}
   

function put() {
  global $json_encode_props;
  $obj = array('status'=>'unsupported', 'message'=>'Unsupported operation : method PUT.');
  echo json_encode($obj, $json_encode_props)."\n";
}

function del() {
  global $json_encode_props;
  $obj = array('status'=>'unsupported', 'message'=>'Unsupported operation : method DELETE.');
  echo json_encode($obj, $json_encode_props)."\n";
}   

rest();

?>
