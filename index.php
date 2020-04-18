<?php require_once("maxsize.php");header('X-Max-Size-Supported: '.$maxsizesupported);?><!DOCTYPE html>
<html>
<!--
Sources:
https://www.html5rocks.com/en/tutorials/file/dndfiles/
https://www.html5rocks.com/en/tutorials/file/filesystem/
https://github.com/mozilla/send/tree/master/app
http://gildas-lormeau.github.io/zip.js/
-->
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
  <title>Изпращане на криптирани файлове</title>
<script type="text/javascript" src="zip.js"></script>
<script type="text/javascript" src="zip-ext.js"></script>
<script type="text/javascript" src="forge.all.js"></script>
<script type="text/javascript">

function onpageload() {
  var error = document.getElementById("error");
  if (error == null) { return; }
  if (window.File == null ||  window.FileReader == null || window.FileList == null || window.Blob == null) {
    error.style.display = "inline-block";
  } else {
    error.style.display = "none";
  }
} 

var escapearea = document.createElement('textarea');
function escapeHtml(html) {
    escapearea.textContent = html;
    return escapearea.innerHTML;
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

function arrayConcat(arr1, arr2) {
  var arr = [];
  var i = 0;
  var index = 0;
  if (arr1 != null) {
    for (i = 0; i < arr1.length; i++) {
      arr[index++] = arr1[i]; 
    }
  }
  if (arr2 != null) {
    for (i = 0; i < arr2.length; i++) {
      arr[index++] = arr2[i]; 
    }
  }
  return arr;
}

function ul()       { return document.createElement("ul");        }
function li()       { return document.createElement("li");        }
function strong()   { return document.createElement("strong");    }
function txt(value) { return document.createTextNode("" + value); }
var selectedBttn = [];
var selectedDrop = [];
function showFiles(files) {
  var lis = [];
  var l,b,str, f, total = 0;
  for (var i = 0; (f = files[i]) != null; i++) {
    l = li(); b = strong();
    b.appendChild(txt(escapeHtml(f.name)))
    str = "(" + (f.type || "неизвестен тип") + ") - " 
         + humanSize(f.size) +
         (f.lastModifiedDate ? "; последно променен: " + humanDate(f.lastModifiedDate) : "");
    l.appendChild(b);
    l.appendChild(txt(str));
    lis.push(l);
    total += f.size;
  } 
  return { elements: lis, size: total };
} 

function showSelectFiles() {
  var u = ul();
  var total = 0;
  var lis = [];
  var result;

  result = showFiles(selectedBttn);
  total += result.size;
  lis = arrayConcat(lis, result.elements);

  result = showFiles(selectedDrop);
  total += result.size;
  lis = arrayConcat(lis, result.elements);

  for(var i = 0; i < lis.length; i++) {
    u.appendChild(lis[i]);
  }

  var list = document.getElementById("list");
  var child = null;
  while (child = list.firstChild) {
    child.remove();
  }
  list.appendChild(u);
  var maxsize = 1024 * 1024 * 1024;
  var actsize = document.getElementById("actsize");
  var size = Math.floor((total * 400)/(maxsize));
  size = size > 400 ? 400 : size;
  size = size < 0   ? 0 : size;
  if (total != 0 && size == 0) {
    size = 2;
  }
  actsize.style.width = "" + size + "px"; 
  actsize.innerHTML = humanSize(total);
  if (total > maxsize) {
    document.getElementById("maxsizelabel").innerHTML = "&nbsp; прекален размер";
  } else {
    document.getElementById("maxsizelabel").innerHTML = "&nbsp; 1 ГБ";
  }
}

function handleFilesSelected(evt) {
  var files = evt.target.files; // FileList oject
  selectedBttn = files;
  showSelectFiles()
}

function handleFilesDragged(evt) {
  evt.stopPropagation();
  evt.preventDefault();
  evt.dataTransfer.dropEffect = "copy"; // Icon showing copy, not move.
}

function handleFilesDropped(evt) {
  evt.stopPropagation();
  evt.preventDefault();
  var files = evt.dataTransfer.files; // FileList object
  selectedDrop = files;
  showSelectFiles();
}

function zipfiles(writer,files, index, endfunction) {
  var overall  = document.getElementById("actoverallprogress");
  var progress = document.getElementById("actfileprogress");
  progress.innerHTML = "" + files[index].name;
  progress.style.width = "0px";
  writer.add(files[index].name, new zip.BlobReader(files[index]), 
    function() {
      overall.style.width = Math.floor((index * 400)/files.length) + "px";
      // onsuccess callback
      // close the zip writer
      if (index == files.length-1) {
        writer.close(
          function(blob) {
            // blob contains the zip file as a Blob object
            overall.style.width = "400px";
            progress.style.width = "400px";
            progress.innerHTML = "";
            endfunction(blob);
          }
        );
      } else {
        zipfiles(writer, files, index+1, endfunction);
      }
    }, 
    function(currentIndex, totalIndex) {
      // onprogress callback
      progress.style.width = Math.floor((currentIndex * 400)/totalIndex) + "px";
    }
  );
}

function zip(files, endfunction) {
  // use a BlobWriter to store the zip into a Blob object
  zip.createWriter(
    new zip.BlobWriter(), 
    function(writer) {
      var overall  = document.getElementById("actoverallprogress");
      overall.innerHTML = "цялосно развитие"; overall.style.width = "0px"; 
      zipfiles(writer, files, 0, endfunction);
    }, 
    function(error) {
      // onerror callback
      var error = document.getElementById("error");
      error.innerHTML = "Error Creating ZIP: " + JSON.stringify(error) + ". Please try with another browser.";
      error.style.display = "inline-block";
    }
  );
}

function process() {
  var files = arrayConcat(selectedBttn, selectedDrop);
  if (files.lenght > 1) {
     zip(files, function (blob) { crypt(blob) });
  } else {    
     crypt(files[0]);
  } 
}

function crypt(blob) {
  var key = forge.random.getBytesSync(32);
  var iv = forge.random.getBytesSync(32);
  var cipher = forge.cipher.createCipher('AES-CBC', key);
  cipher.start({iv: iv});

  var reader = blob.stream().getReader();
  var stream = new ReadableStream({
      start(controller) {
        return pump();
        function pump() {
          return readr.read().then(({ done, value }) => {
            if (value != null) {
              decipher.update(value);
              var bytes = cipher.output.getBytes();
console.log("Encrypted Bytes Current: " + JSON.stringify(bytes));
              controller.enqueue(bytes);
            }
            if (done) {
                cipher.finish();
                var bytes = cipher.output.getBytes();
console.log("Encrypted Bytes Last: " + JSON.stringify(bytes));
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
      concel(reason) {
        console.log("Cancelled, because: " + reason);
      }
    },
    {
      highWaterMark: 10,
      size(chunk) {
console.log("Chunk: " + chunk);
        return chunk.length;
      }
    }
  );
  var response = new Response(stream);
  promise.catch(function (ex) {
    console.log("Converting to blob failed with exception: " + ex + " : " + JSON.stringify(ex));
  });
  promise.then(
    function (blob) {
      console.log("blob: " + blob);
      uploadBlob(blob, btoa(iv + "" + key));
    },
    function (error) {
      console.log("Gettinf blob failed: " + JSON.stringify(error, null, 2));
    }
  );
}

function dirname(path) {
  var dir = path.match( ".*/");
  dir = dir == null ? "" : dir[0];
  if (dir.endsWith("/")) {
    dir = dir.substring(0, dir.length-1);
  }
  return dir;
}

function uploadBlob(blob, secret) {
  var url = new URL(window.location.href);
  url = url.protocol + "//" + url.hostname + ":" + url.port + dirname(url.pathname) + "/files/";
  var http = new XMLHttpRequest();
  http.open("POST", url, true);
  http.onreadystatechange = 
    function () {
      var state = "";
      switch (this.readyState) {
        case XMLHttpRequest.UNSENT:           state = "Неустановена връзка";     break;  
        case XMLHttpRequest.OPENED:           state = "Връзката е отворена";     break;
        case XMLHttpRequest.HEADERS_RECEIVED: state = "Получени заглавни части"; break;  
        case XMLHttpRequest.LOADING:          state = "Зареждане";               break;
        case XMLHttpRequest.DONE:             state = "Готово";                  break;
        default:                              state = "Неизвестно състояние: " + state;
      };
      console.log("Качване на файл: " + state + " ... ");
      if (this.readyState != XMLHttpRequest.DONE) {
        return;
      }           
  console.log("status:   " + this.readyState);
  console.log("status:   " + this.status);
      if (this.status < 200 || this.status >= 300) {
	// Error TODO:
	return;
      }
      var link = document.getElementById("link");
  console.log("link: " + link);
      var id = "неизвестнен идентификатор";
      try {
	var result = JSON.parse(this.responseText);
  console.log("result: " + JSON.stringify(result));
	id = result["id"];
  console.log("id: " + JSON.stringify(id));
      } catch (se) {
        console.log("Невъзможност за привеждане на отговора към джаваскрипт формат JSONC. Отговор: " + this.responseText + "; Име: " + se.name + "; Съобщение: " + se.message+ "; Стек: " + se.stack + "; " + JSON.stringify(se));
      }	
      link.innerHTML = url + "" + id + "#" + secret; 
      link.style.display = "inline-block";
      return;
    } 
  ; 
  http.setRequestHeader('Content-Size', "" + blob.size);
  http.setRequestHeader('Content-Type', 'application/octet-stream');
  http.setRequestHeader('Authorization', 'Bearer c2VuZC1lbmNyeXB0ZWQtYXV0aGVudGljYXRpb24ta2V5');
  http.send(blob);
}

function copyLink() {
  var link = document.getElementById("link");
  copyToClipboard(link.innerHTML);
  if (!link.innerHTML.startsWith("Копирано в клипборда: ")) {
    link.innerHTML = "Копирано в клипборда: " + link.innerHTML;
  }
}

function copyToClipboard(str) {
  const el = document.createElement('textarea');  // Create a <textarea> element
  el.value = str;                                 // Set its value to the string that you want copied
  el.setAttribute('readonly', '');                // Make it readonly to be tamper-proof
  el.style.position = 'absolute';                 
  el.style.left = '-9999px';                      // Move outside the screen to make it invisible
  document.body.appendChild(el);                  // Append the <textarea> element to the HTML document
  const selected =            
    document.getSelection().rangeCount > 0        // Check if there is any content selected previously
    ? document.getSelection().getRangeAt(0)     // Store selection if found
    : false;                                    // Mark as false to know no selection existed before
  el.select();                                    // Select the <textarea> content
  document.execCommand('copy');                   // Copy - only works as a result of a user action (e.g. click events)
  document.body.removeChild(el);                  // Remove the <textarea> element
  if (selected) {                                 // If a selection existed before copying
    document.getSelection().removeAllRanges();    // Unselect everything on the HTML document
    document.getSelection().addRange(selected);   // Restore the original selection
  }
}


function errorMessageFromResponse(http) {
  if (http == null || http.responseText == null || http.responseText == "") {
    return null;
  } 
  try {
    var response = JSON.parse(http.responseText);
    if (response == null) {
      return null;
    }  
    if (response.error != null) { 
      return response.error;
    }
    if (response.message != null) {
      return response.message;
    }
  } catch (e) {
    dmp("exception", e);
    return e;
  } 
  return null;
} 

function downloadBlob() {
  // https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest/Sending_and_Receiving_Binary_Data
  // https://fetch.spec.whatwg.org/#fetch-api
  // https://developer.mozilla.org/en-US/docs/Web/API/Streams_API/Using_readable_streams
  // Readable Streams: https://developer.mozilla.org/en-US/docs/Web/API/Streams_API/Using_readable_streams
  /*
  var oReq = new XMLHttpRequest();
  oReq.open("GET", "/myfile.png", true);
  oReq.responseType = "blob";

  oReq.onload = function(oEvent) {
    var blob = oReq.response;
    // ...
  };

  oReq.send();1
  */
}

function readStream(stream) {
  const reader = stream.getReader();
  let charsReceived = 0;
  var result = "";

  // read() returns a promise that resolves
  // when a value has been received
  reader.read().then(function processText({ done, value }) {
    // Result objects contain two properties:
    // done  - true if the stream has already given you all its data.
    // value - some data. Always undefined when done is true.
    if (done) {
      console.log("Stream complete");
      //para.textContent = result;
      console.log(result);
      return;
    }

    charsReceived += value.length;
    const chunk = value;
    console.log('Read ' + charsReceived + ' characters so far. Current chunk = ' + chunk);

    result += chunk;

    // Read some more, and call this function again
    return reader.read().then(processText);
  });
}

var link = document.createElement("a");
link.download = "hello.txt";

//var blob = new Blob(["Hello world"], { type: "text/plain"});
var stream = new ReadableStream(
 {
   start: function (controller) {
     controller.enqueue("alabla");
     /*
     this.interval = setTimeout(
        10000,
        function () {
          var str = "1234567890";
          controller.enqueue(str);
        }
     ); 
     */
     controller.enqueue("--sdf-sd-afsd-fa");
     controller.enqueue("--sdf-sd-afsd-fa-dfasdfadsfa");
     controller.close();
   }
/*
   pull: function (controller) {
      // We don't really need a pul in this example.
      console.log("pull. called.");
      controller.enqueue("dafadaf");
      controller.close();
   },
*/
   ,
   cancel: function (reason) {
     //clearInterval(this.interval);
     console.log("Cancelled with reason: " + reason);
   }
 }
//,
// { 
//    highWaterMark: 2,
//    size:  function (chunk) {
//      console.log("size of chunk: " + chunk);
//      return chunk.length;
//    }
// }
);

//readStream(stream);

var b = new Blob(["test", "-", "mest"], {type: "text/plain"});
var readr = b.stream().getReader();
var stre = new ReadableStream({
    start(controller) {
      return pump();
      function pump() {
        return readr.read().then(({ done, value }) => {
          // When no more data needs to be consumed, close the stream
          if (done) {
              controller.close();
              return;
          }
          // Enqueue the next data chunk into our target stream
          //controller.enqueue(value);
          console.log("value: " + value);
          console.log("value.constructor: " + value.constructor);
          console.log("value JSON       : " + JSON.stringify(value, null, 2));
          var arr = new Uint8Array(value.length + 1);
          for (var i =0; i < value.length; i++) {
            arr[i] = value[i] + 1;
          }
          arr[value.length] = "A".charCodeAt(0);
          console.log("arr: " + arr);
          console.log("arr.constructor: " + arr.constructor);
          console.log("arr JSON       : " + JSON.stringify(arr, null, 2));
    
          controller.enqueue(arr);
          return pump();
        });
      }
    }, 
    pull(controller) {
      console.log("pull called.");
    },
    concel(reason) {
      console.log("Cancelled, because: " + reason);
    }
  },
  {
    highWaterMark: 10,
    size(chunk) {
      console.log("Chunk: " + chunk);
      return chunk.length;
    }
  }
)


var response = new Response(stre);
console.log("response: " + response);
var promise = response.blob();
console.log("promise:  " + promise);
promise.catch(function (ex) {console.log("Converting to blob failed with exception: " + ex + " : " + JSON.stringify(ex));});
promise.then(
  function (blob) {
    //
    console.log("blob: " + blob);
    link.href = URL.createObjectURL(blob);
    //link.click();
    //URL.revokeObjectURL(link.href);
  },
  function (error) {
    console.log("Gettinf blob failed: " + JSON.stringify(error, null, 2));
    for (var i in error) {
      console.log("error." + i + " = " + error[i]);
    }
  }
);
/*

var reader = stream.getReader();
console.log("reader: " + reader);
var promise = null;
var stop = false;
promise = reader.read();
promise.then(function (result) { 
  console.log("resolved: " + JSON.stringify(result, null, 2));
  promise = reader.read();
  promise.then(
     function (result) {console.log("resolved once again:    " + JSON.stringify(result, null, 2));},
     function (result) {console.log("failed the scond time: "  + JSON.stringify(result, null, 2));}
  );
}, function (error) {
  console.log("error:    " + JSON.stringify(error, null, 2));
});
*/

//link.href = URL.createObjectURL(blob);
//console.log("blog.slice: " + blob.slice);
//console.log("link.href: " + link.href);
//link.click();
//URL.revokeObjectURL(link.href);

</script>
<link rel="stylesheet" type="text/css" href="general.css">
</head>
<body onload="onpageload();">
<!-- Fork Me On Github -->
<img style="position: absolute; top: 0; right: 0; border: 0;"
     src="images/fork-me-on-github.png"
     alt="Fork me on GitHub" usemap="#github">
  <map name="github">
    <area shape="poly" coords="12,0,148,138,148,74,74,0,12,0" 
          href="https://github.com/ynedelchev/sendencrypted" 
          alt="sendencrypted">
  </map>
<!-- Fork Me On Github End -->

  <div class="main">
    <div class="error" id="error">
     Вашият браузър не се поддържа. Моля пробвайт с друг браузър като например Мозила Файърфокс.
    </div>

    <h1 class="headerline">Изпращане на криптирани файлове</h1>

    <div style="float: left;">0 байта &nbsp;</div>
    <div id="sizebox" class="progresscontainer">
      <div id="maxsize" class="progressbase"> &nbsp; </div>
      <div id="actsize" class="actprogress"> &nbsp; </div>
    </div>
    <div style="float: left;" id="maxsizelabel">&nbsp; 1 GB</div>
    <br style="clear: both;"/><div style="clear: both; margin-top: 2em;">

    <div class="label">Изберете файл:</div>
    <input type="file" id="files" name="files" multiple="multiple" class="browse" onchange="javascript:handleFilesSelected(event);"/>
    <br style="clear: both;"/>или<br/>
    <div class="label">Изберете и завлачете файл:</div>
    <div id="drop" class="drag" ondragover="javascript:handleFilesDragged(event);" ondrop="javascript:handleFilesDropped(event);">Завлачете и пуснете файл тук</div>
    <br style="clear: both;"/>
    <div style="align: left; padding-top: 1em;">
        <input type="button" id="send" name="send" class="crypt" value="Криптирай" onclick="javascript:process();"/>
    </div>
    <a id="link" class="link" href="#" name="link" class="link" onclick="javascript:copyLink();"></a>
    <br style="clear: both;"/>
    <div id="overallprogress" class="progresscontainer">
      <div id="overallbase" class="progressbase"> &nbsp; </div>
      <div id="actoverallprogress" class="actprogress"> &nbsp; </div>
    </div>
    <div id="fileprogress" class="progresscontainer">
      <div id="filebase" class="progressbase"> &nbsp; </div>
      <div id="actfileprogress" class="actprogress"> &nbsp; </div>
    </div>
    <br style="clear: both;"/>
    <output id="list" style="align: left; text-align: left;"></output>
  </div>
</body>
</html>
