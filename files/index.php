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
    header('Content-Length: '.strlen($meta_content));
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
  $user = auth_verify();
  $ip = getenv('HTTP_CLIENT_IP')?:
  getenv('HTTP_X_FORWARDED_FOR')?:
  getenv('HTTP_X_FORWARDED')?:
  getenv('HTTP_FORWARDED_FOR')?:
  getenv('HTTP_FORWARDED')?:
  getenv('REMOTE_ADDR');
  if ($ip != 'localhost' && $ip != '127.0.0.1' && $ip != '::1') {
var_dump($ip);
    http_response_code(404);
    error_log('Trying to access '.__FILE__.' from IP '.$ip.'. That is not allowed. Only calls from localhost or 127.0.0.1 are allowed.');
    exit(1);
  }
  $obj = array('status'=>'unsupported', 'message'=>'Unsupported operation : method DELETE.');
  if (!isset($_GET["filter"]) || $_GET["filter"] != "expired") {
    http_exit(412, "Тази операция в момента се поддържа само заедно с параметър \"filter\" (цедка) със стойност \"expired\" (изтекли). "
             .(!isset($_GET["filter"])?"Не е подадан параметър \"filter\" (цедка). Моля подайте го като добавите към адреса следното \"?filter=expired\" (дедка - изтекли)."
                                      :"Стойността на параметъра \"filter\" (цедка) е в момента \"".(isset($_GET['filter'])?$_GET['filter']:'UNKNOWN')."\"."), 
             "This operation is currently only supported with the \"filter\" parameter equals to \"expired\". "
             .(!isset($_GET["filter"])?"No parameter \"filter\" is currently set. Set it in the URL by adding \"?filter=expired\"."
                                      :"The value of the \"filter\" parameter is currently \"".(isset($_GET['filter'])?$meta['filter']:'UNKNOWN')."\"."), null);
  }
  $deleted = array();
  $failed  = array(); 
  if ($dh = opendir(__DIR__)) {
    while (($file = readdir($dh)) !== FALSE) {
      if (is_dir(__DIR__.'/'.$file)) {
        continue;
      }
      if (substr($file, -strlen(".json")) != ".json") {
        continue;
      }
      $id = substr($file, 0, strlen($file)-5);
      $content = file_get_contents(__DIR__.'/'.$file);
      if ($content === FALSE) {
        error_log("Content of file \"".__DIR__.'/'.$file."\" cannot be read. Thus not deleting it as part of delete expired files operation.");
        $failed[$id] = "Cannot read metadata.";
        continue;
      }
      $objects_to_associative_arrays = TRUE;
      $recursion_limit = 7;
      $meta = json_decode($content, $objects_to_associative_arrays, $recursion_limit, JSON_BIGINT_AS_STRING);
      if ($meta === NULL) {
        $content = strlen($content) > 1024 ? "Content starts with : \"".substr($content, 0, 1024)."\" ..." : "Content is: \"".$content."\".";
        error_log("Content of file \"".__DIR__.'/'.$file."\" cannot be JSON decoded or the encoded data is deeper than the recursion limit ($recursion_limit). Thus not deleting it as part of delete expired files operation.");
        $failed[$id] = "Cannot parse metadata.";
        continue;
      } 
      if (!isset($meta['status'])) {
        error_log("Metadata file \"".__DIR__.'/'.$file."\" does not specify \"status\" property, thus it is unknown what the status of the corresponding data file is. Not deleting it as part of delete expired files operation. Content of metadata file is: ".json_encode($meta));
        $failed[$id] = "Cannot find status.";
        continue;
      }
      if (!isset($meta['expires'])) {
        error_log("Metadata file \"".__DIR__.'/'.$file."\" does not specify \"expires\" property, thus it is unknown when the corresponding data file expires. Not deleting it as part of delete expired files operation. Content of metadata file is: ".json_encode($meta));
        $failed[$id] = "Cannot find expiration date.";
        continue;
      }
      if ($meta["status"] == "uploading") {
        continue;
      }
      if ($meta['status'] == "unknown") {
        error_log("Status defined in metadata file \"".__DIR__.'/'.$file."\" is \"".$meta["status"]."\", so it would not be deleted.");
        $failed[$id] = "Unknown status.";
        continue;
      }
      $expires   = $meta['expires'];
      $expires   = intval($expires/1000);
      $now = time();
      if ($now > $expires) {
        $now  = date("Y-m-d H:i s (P)", $now);
        $expired  = date("Y-m-d H:i s (P)", $expires);
        $data = substr($file, 0, strlen($file)-5).".data";
        $msgmeta = "Deleting \"".__DIR__.'/'.$file."\" expired on ".$expired.". Now : ".$now.".";
        $msgdata = "Deleting \"".__DIR__.'/'.$data."\" expired on ".$expired.". Now : ".$now.".";
        $successdata = FALSE;
        $successmeta = FALSE;
        error_log($msgdata);
        if (file_exists(__DIR__.'/'.$data)) {
          $successdata = unlink(__DIR__.'/'.$data);
          if ($successdata === FALSE) {
            error_log("Failed   \"".__DIR__.'/'.$data."\" deletion.");
            $failed[$id] = "Data deletion failure.";
          }
        } else {
          $successdata = TRUE;
        }
        error_log($msgmeta);
        if (file_exists(__DIR__.'/'.$file)) {
          $successmeta = unlink(__DIR__.'/'.$file);
          if ($successmeta === FALSE) {
            error_log("Failed   \"".__DIR__.'/'.$file."\" deletion.");
            $failed[$id] = "Metadata deletion failure.";
          }
        } else {
          $successmeta = TRUE; 
        }
        if ($successdata && $successmeta) {
          array_push($deleted, $id);
        }
      } 
    }
    closedir($dh);
    $obj = array("deleted"=>$deleted, "failed"=>$failed);
    echo json_encode($obj, $json_encode_props)."\n";
  } else {
    http_exit(500, "Вътрешносървърна грешка", "Internal Server Error. Cannot open directory \"".__DIR__."\" for reading. Cannot process data and metadata files in this directory.", null);
  }
}   

rest();

?>
