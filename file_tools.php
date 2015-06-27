<?php

function grepinfile ($filename,$pattern,$onlyfirstmatch=true) {
#  print "Searching in '$filename' for Pattern $pattern\n";
  if (! file_exists($filename)) return null;
  $handle = @fopen($filename, "r");
  $allmatches=null;
  if ($handle) {
    while (($buffer = fgets($handle, 4096)) !== false) {
      if (preg_match_all ($pattern,$buffer,$matching)) {
        if ($onlyfirstmatch) {
          fclose ($handle);
          return $matching;
        } else {
         if (is_null($allmatches)) $allmatches=array();
         $allmatches[]=$matching;
        }
      };
    }
    if (!feof($handle)) {
        echo "Fehler: unerwarteter fgets() Fehlschlag\n";
    }
    fclose($handle);
    return $allmatches;
  }
  return null;
}

?>