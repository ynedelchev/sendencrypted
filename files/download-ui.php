<?php
require_once("common.php");
function humanSize($size) {
  $merki = array(
    array("one" => "един байт",     "many" => "байта"    ),
    array("one" => "един Кабайт",   "many" => "К байта"  ),
    array("one" => "един Мегабайт", "many" => "Мегабайта"),
    array("one" => "един Гигабайт", "many" => "Гигабайта"),
    array("one" => "един Терабайт", "many" => "Терабайта")
  );
  for ($i = 0; $i < count($merki)-1; $i++) {
    $remain = $size % 1024;
    $dev = ($size - $remain) / 1024;
    if ($dev == 0) {
      return ($size == 1) ? $merki[$i]["one"] : $size . " " . $merki[$i]["many"];
    } 
    $size = $dev;
  }
  return ($size == 1) ? $merki[count($merki)-1]["one"] : $size . " " . $merki[count($merki)-1]["many"];
}
$id    = isset($_SERVER["REDIRECT_ELEMENT"])    ? $_SERVER["REDIRECT_ELEMENT"]    : null;
$errors = array();
$data = __DIR__.'/'.$id.'.data';
$json = __DIR__.'/'.$id.'.json';
$meta = array();
$meta_content = file_get_contents($json);
$errid = base64(randomstr(32));
if ($meta_content === FALSE) {
  error_log('Metadata file "'.$json.'" cannot be read. Please check that the file exists, is readable an is not locked by some other process. Errror Id Ref: '.$errid.'; File Id: '.$id.'.');
  array_push($errors, 'Търсеният файл не е намерен ('.$id.').');
} else {
  $meta = json_decode($meta_content, TRUE, 7);
  if ($meta === FALSE) {
    error_log('Metadata file "'.$json.'" that corresponds to data file "'.$data.'", has been read, but its content cannot be parsed as JSON. Content: `'.$meta_content.'`. Error Id Ref: '.$errid.'; File Id: '.$id.'.');
    array_push($errors, 'Търсеният файл не е наличен ('.$id.')');
    $meta = array();
  }
}
$filesize = (isset($meta['size'])?$meta['size']:0);
$humansiz = humanSize($filesize);
header('Content-Type: text/html; charset=UTF-8');
header('X-Max-Length-Supported: '.$maxsizesupported);
header('X-File-Id: '.$id);
header('X-Download: '.(isset($meta['downloads'])?$meta['downloads']+1:1));
header('X-Allowed-Downloads: '.(isset($meta['allowed-downloads'])?$meta['allowed-downloads']:0));
header('X-Expires: '. (isset($meta['expires'])?$meta['expires']:0));
header('X-Expires-Date: '.date("Y-m-d H:i s (P)", (isset($meta['expires'])?$meta['expires']:0) / 1000));

?><!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
  <link rel="stylesheet" type="text/css" href="../general.css">
  <title>Получаване на криптиран файл (<?php echo $i;?>)</title>
  <script type="text/javascript" src="../zip.js"></script>
  <script type="text/javascript" src="../zip-ext.js"></script>
  <script type="text/javascript" src="../forge.all.js"></script>
<script type="text/javascript">
var filesize    = <?php echo (isset($meta['size'])?$meta['size']:0);?>;
var fileid      = "<?php echo (isset($meta['id'])?$meta['id']:'');?>";
var filemd5     = "<?php echo (isset($meta['md5'])?$meta['md5']:'');?>";
var fileexpires = <?php echo (isset($meta['expires'])?$meta['expires']:0);?>; 
function onpageload() {
  var error = document.getElementById("error");
  if (error == null) { return; }
  if (window.File == null ||  window.FileReader == null || window.FileList == null || window.Blob == null) {
    error.style.display = "inline-block";
  } else {
    error.style.display = "none";
  }
} 

function dirname (path) {
  return path.replace(/\\/g, '/').replace(/\/[^/]*\/?$/, '');
}

function humanSize(size) {
  var merki = [{one: "един байт", many: "байта"}, {one: "един Кабайт", many: "К байта"}, {one: "един Мегабайт", many: "Мегабайта"}, {one: "един Гигабайт", many: "Гигабайта"}, {one: "един Терабайт", many: "Терабайта"}];
  for (var i = 0; i < merki.length-1; i++) {
    var remain = size % 1024;
    var dev = (size - remain) / 1024;
    if (dev == 0) {
      return (size == 1) ? merki[i].one : size + " " + merki[i].many;
    } 
    size = dev;
  }
  return (size == 1) ? merki[merki.length-1].one : size + " " + merki[merki.length-1].many;
}

function humanDate(date) {
  if (date == null) {
    return "неизвестно кога";
  }
  if (date.toLocaleDateString != null) {
    return date.toLocaleDateString();
  } 
  var object = new Date(date);
  return object.toLocaleDateString();
}

function send() {
  var url = window.location.protocol + "//" + window.location.hostname + "/" + dirname(dirname(window.location.pathname)) + "/"; 
  try { 
    // Simulate mouse click 
    window.location.href = url;
  } catch (e) { 
    // If simulate mouse click does not work for some reason, then 
    // Simulate redirect
    window.location.replace(url); 
  }
}

var escapearea = document.createElement('textarea');
function escapeHtml(html) {
    escapearea.textContent = html;
    return escapearea.innerHTML;
}
function br()       { return document.createElement("br");        }
function txt(value) { return document.createTextNode("" + value); }

function error(message) {
  var errors = document.getElementById("errors-log");
  if (errors == null) {
    return;
  }
  var lines = message.split("\n");
  for (var i = 0; lines != null && i < lines.length; i++) {
    var line = lines[i].trim();
    errors.appendChild(txt(escapeHtml(line)));
    errors.appendChild(br());
  }
  errors.style.display = "inline-block";
}

function clearerrors() {
  var errors = document.getElementById("errors-log");
  if (errors == null) {
    return;
  }
  while (child = errors.firstChild) {
    child.remove();
  }
}

var headers = {};
var key = "";
function download() {
  var url = window.location.href;
  var hashIndex = url.lastIndexOf("#");
  if (hashIndex < 0 || hashIndex >= url.length-1) {
    error("Ключ за разкриптиране не е наличен.\nКлючът за разкриптиране е тази част от адреса която следва след знака диез (#).");   
    return;
  }
  key = url.substring(hashIndex+1);
  var url = url.substring(0, hashIndex);
  var http = new XMLHttpRequest();
  http.open("GET", url, true);
  http.responseType = 'blob';
  http.onreadystatechange = function () {
    var state = "";
    switch (this.readyState) {
      case XMLHttpRequest.UNSENT:           state = "Неустановена връзка";     break;  
      case XMLHttpRequest.OPENED:           state = "Връзката е отворена";     break;
      case XMLHttpRequest.HEADERS_RECEIVED: state = "Получени заглавни части"; break;  
      case XMLHttpRequest.LOADING:          state = "Зареждане";               break;
      case XMLHttpRequest.DONE:             state = "Готово";                  break;
      default:                              state = "Неизвестно състояние: " + state;
    };
    console.log("Сваляне на файл: " + state + " ... ");
    if (this.readyState == XMLHttpRequest.HEADERS_RECEIVED) {
      var headersString = http.getAllResponseHeaders();
      var arr = headersString.trim().split(/[\n]+/);
      headers = {};
      arr.forEach(function (line) {
        var index = line.indexOf(':');
        if (index <0 || index >= line.length-1) {
           headers[line] = ""; 
        } else {  
          var header = line.substring(0, index).trim();
          var value  = line.substring(index+1).trim();
          headers[header] = value;
        }
      });
      var size = headers['x-file-id'];
      var downloadindex = headers['x-download'];
      var allowed       = headers['x-allowed-downloads'];
      var expires       = headers['x-expires'];
      var errorid       = headers["x-error-id"];
      if (errorid != null) {
        error("Случи се грешка с код: " + errorid + ".");
        return;
      } 
    } 
    if (this.readyState != XMLHttpRequest.DONE) {
      return;
    }           
    if (this.status < 200 || this.status >= 300) {
      // Error TODO:
      if (headers["content-type"] == "application/json" || this.response.type == "application/json") {
        var str = this.response.text().then( 
          function (text) {
            var message = text;
            var errid = headers["x-error-id"]; 
            try {
              var json  = JSON.parse(text);
              message = json["message"] != null ? json["message"] : message;
              errid   = json["errid"] != null ? json["errid"] : errid;
            } catch (e) {
            } 
            clearerrors();
            error(message + "\nКод на грешка: "+ errid);
          }
        );
      } else {
        var str = this.response.text().then( 
          function (text) {
            clearerrors();
            error(text.length < 3 * 1024 ? text : text.substring(0, 3*1024));
          }
        );
      }  
      return;
    }
    decryptAndStore(this.response, key);
  };
  http.setRequestHeader("Accept", "application/octet-stream");
  http.setRequestHeader("Authorization", "Bearer c2VuZC1lbmNyeXB0ZWQtYXV0aGVudGljYXRpb24ta2V5");
  http.send();

}

function decryptAndStore(blob, key) {
  var key = atob(key);
console.log("key : " + key);
console.log("key.length : " + key.length);
console.log("key.constructor: " + key.constructor);
var key1 = forge.random.getBytesSync(32);
console.log("key1: " + key1);
console.log("key1.constructor: " + key1.constructor);

  if (key.length != 64) {
    error("Нвялидна дължина " + key.length + " на криприращ/декриптиращ ключ.\nДължината трябва да е точно 64.");
    return;
  }
  var iv = key.substring(0, 32);
  var key = key.substring(32);

  var decipher = forge.cipher.createDecipher('AES-CBC', key);
  decipher.start({iv: iv});
  var reader = blob.stream().getReader();
  var stream = new ReadableStream({
      start(controller) {
        return pump();
        function pump() {
          return reader.read().then(({ done, value }) => {
            if (value != null) {
              decipher.update(forge.util.createBuffer(value));
              var str = decipher.output.getBytes();
              var bytes = new Uint8Array(str.length);
              for (var i =0; i < str.length; i++) {
                bytes[i] = str.charCodeAt(i) & 0x0FF;
              }
              controller.enqueue(bytes);
            }
            if (done) {
              decipher.finish();  
              var str = decipher.output.getBytes();
              var bytes = new Uint8Array(str.length);
              for (var i =0; i < str.length; i++) {
                bytes[i] = str.charCodeAt(i) & 0x0FF;
              }
              controller.enqueue(bytes);
              controller.close();
              return;
            }
            return pump();
          });
        }
      },
      pull(controller) {
        console.log("pull called.");
      },
      cancel(reason) {
        console.log("Cancelled, because: " + reason);
      } 
    },
    {
      highWaterMark: 10,
      size(chunk) {
        return chunk.length;
      }
    }
  );

  var response = new Response(stream);
  var promise = response.blob();
  promise.catch(function (ex) {console.log("Converting to blob failed with exception: " + ex + " : " + JSON.stringify(ex));});
  promise.then(
    function (blob) {
      console.log("blob: " + blob);
      var link = document.createElement("a");
      link.href = URL.createObjectURL(blob);
      link.click();
      URL.revokeObjectURL(link.href);
    },
    function (error) {
      console.log("Gettinf blob failed: " + JSON.stringify(error, null, 2));
      for (var i in error) {
        console.log("error." + i + " = " + error[i]);
      }
    }
  );
}


</script>
</head>
<body onload="onpageload();">
<!-- Fork Me On Github -->
<img style="position: absolute; top: 0; right: 0; border: 0;"
     src="<?php echo full_url(2);?>/images/fork-me-on-github.png"
     alt="Fork me on GitHub" usemap="#github">
  <map name="github">
    <area shape="poly" coords="12,0,148,138,148,74,74,0,12,0" href="https://github.com/ynedelchev/sendencrypted" alt="sendencrypted">
  </map>
<!-- Fork Me On Github End -->
  <div class="main">
    <div class="error" id="error">
     Вашият браузър не се поддържа. Моля пробвайта с друг браузър като например Мозила Файърфокс.
    <?php foreach($errors as $value): ?>
        <br/><?php echo $value; ?>
    <?php endforeach; ?>
    </div>
    <div class="error" style="display: <?php echo (count($errors) == 0? 'none' : 'inline-block');?>" id="errors">
    <?php foreach($errors as $value): ?>
        <?php echo $value; ?><br/>
    <?php endforeach; ?>
    <br/>
    Ако смятате, че тази грешка е въпрос на проблем, моля свържете се с администратора и му предайте следният референтен код на грешка: <?php echo $errid;?>.
    </div>
    <div class="error" style="display: none; text-align: left; align: left;" id="errors-log">
    </div>

    <h1 class="headerline" style="margin-bottom: 0px; padding-bottom: 0px;">Получаване на криптиран файл</h1> 
    <h2 class="headerline" style="margin-top: 0px;padding-top: 0px;">(<?php echo $id;?>)</h2>
    <div style="align: left; padding-top: 1em; margin-bottom: 2em;">
        <input type="button" id="send" name="send" class="crypt" value="Свали Файл" onclick="javascript:download();"/>
    </div>

    <div style="float: left;">0 байта &nbsp;</div>
    <div id="sizebox" class="progresscontainer">
      <div id="maxsize" class="progressbase"> &nbsp; </div>
      <div id="actsize" class="actprogress"> &nbsp; </div>
    </div>
    <div style="float: left;" id="maxsizelabel">&nbsp; <?php echo humanSize($filesize);?></div>
    <br style="clear: both;"/><div style="clear: both; margin-top: 2em;">
    <div style="align: left; padding-top: 1em; margin-bottom: 2em;">
        <input type="button" id="send" name="send" class="crypt" style="width: 20em;" value="Криптиране и изпращане на файл" onclick="javascript:send();"/>
    </div>

</body>
</html>
