<!-- Fork Me On Github -->
<?php 
  require_once(__DIR__."/files/common.php");
  if (!isset($uplevels)) {
    $uplevels = 0;
  } 
?>
<img style="position: absolute; top: 0; right: 0; border: 0;"
     src="<?php echo full_url($uplevels);?>/images/fork-me-on-github.png"
     alt="Fork me on GitHub" usemap="#github">
  <map name="github">
    <area shape="poly" coords="12,0,148,138,148,74,74,0,12,0"
          href="https://github.com/ynedelchev/sendencrypted"
          alt="sendencrypted" target="_blank" />
  </map>
<!-- Fork Me On Github End -->
