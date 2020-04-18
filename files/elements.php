<?php
require_once("common.php");

$type = getheader('Accept');
if (isset($type)) {
  $types = explode(',', $type);
  $sorted = array();
  $priorities = array();
  foreach ($types as $value) {
    $value = trim($value);
    $type_and_priority = explode(';', $value);
    $type = '';
    $priority = floatval(0);
    if (count($type_and_priority) <= 1) {
      $type = (isset($type_and_priority[0]) ? $type_and_priority[0] : $value);
      $type = trim($type);
      $priority = floatval(1);
    } else if (count($type_and_priority) >= 2) {
      $type = $type_and_priority[0];
      $priority = $type_and_priority[1];
      $priority = trim($priority[1]);
      if (substr($priority, 0, 2) == 'q=') {
        $priority = substr($priority, 2);
        $priority = trim($priority);
        $priority = floatval($priority);
      }
    }
    array_push($sorted, $type);
    array_push($priorities, $priority);  
  }
  $move = FALSE;
  for ($i = 0; $i < count($sorted); $i++) {
    $move = FALSE;
    for ($j = 0; $j < count($sorted)-$i-1; $j++) {
      if ($priorities[$j] < $priorities[$j+1]) {
        $move = TRUE;
        $tmp = $priorities[$j+1]; $priorities[$j+1] = $priorities[$j]; $priorities[$j] = $tmp;
        $tmp = $sorted[$j+1];     $sorted[$j+1] = $sorted[$j];         $sorted[$j] = $tmp;
      } 
    }
    if ($move == FALSE) {
      break;
    }
  } 
  $highest = array();
  $priority = isset($sorted[0]) ? $sorted[0] : 1;
  for ($i =0; $i < count($sorted) && isset($sorted[$i]) && isset($priorities[$i]) && $priorities[$i] >= $priority; $i++) {
    array_push($highest, $sorted[$i]);
  }
  if (in_array('application/octet-stream', $highest)) {
    include(__DIR__.'/download-api.php');
  } else {
    include(__DIR__.'/download-ui.php');
  }
} else {
  include(__DIR__.'/download-ui.php');
}

?>
