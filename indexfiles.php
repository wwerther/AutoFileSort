<?php
// vim: set filetype=php expandtab tabstop=2 shiftwidth=2 autoindent smartindent:
// kate: replace-tabs on; remove-trailing-space on; tab-width 2

function update_checksumfiles ($checksum,$filename,$filelist) {
  if (! is_array($filelist)) {
    $filelist=array($filelist);
  }

  foreach ($filelist as $checkfilename) {
    $cpath=pathinfo($checkfilename,PATHINFO_DIRNAME); # Path for checkfilename

    $rpath=getRelativePath($cpath,$filename);         # Calculate the "shortest path" to the content-filename starting at the checksum file

    print " --> update fileindex '$checkfilename' with '$checksum' and '$rpath'\n";
    add_unique_line_to_file($checkfilename,"$checksum  $rpath");
  }
}

#
# This function adds a line to a file in case the file does not already contain that line
#
function add_unique_line_to_file($filename,$line) {
    $quoted=preg_quote($line,'#');
    if (is_null(grepinfile($filename,"#$line#"))) {
      $pathinfo=pathinfo($filename);
      if (! file_exists($pathinfo['dirname'] )) {
        print "Creating path ".$pathinfo['dirname']."\n";
        mkdir ($pathinfo['dirname'],0777,true);
      }
      return file_put_contents($filename,"$line\n",FILE_APPEND);
    } else {
      return false;
    }
}

#
# This function allows to search a file for a certain line and replace it with another
# value or delete it (if replacement is not given)
#
function replace_line_in_file($filename,$search,$replacement=null) {
  $reading = fopen($filename, 'r');
  $writing = fopen($filename.'.tmp', 'w');

  $replaced = false;
  while (!feof($reading)) {
    $line = fgets($reading);
    if (stristr($line,$search)) {
      $line = $replacement;
      $replaced = true;
    }
    # Only write to file if not null
    if (! is_null ($line)) {
      fputs($writing, $line);
    }
  }
  fclose($reading);
  fclose($writing);

  // might as well not overwrite the file if we didn't replace anything
  if ($replaced) {
    rename($filename.'.tmp', $filename);
  } else {
    unlink($filename.'.tmp');
  }

}

?>
