<?php
namespace wwerther;

class FileTools {

#
# Remove Empty Directory structures
#
# Walk through a given directory and remove all empty sub-directories up to the top level
#
public static function removeEmptyTree($dir,$keeptop=true) {

   # Just verify that we really operate on directories and not on files/links etc.
   # to avoid accidential deletion of useful content.
   if (is_null($dir)) return false;
   if ($dir=='') return false;
   if (is_link($dir)) return false;
   
   if (!is_dir($dir)) return false; 
   if (!file_exists($dir)) return false; 
   
   # Get all files + directories in the current directory
   $files = array_diff(scandir($dir), array('.','..'));
   
   # Walk through the complete content
   $empty=true;
   foreach ($files as $file) {
      # If the directory only contains (also empty subdirectories) remove them if possible
      if (is_dir("$dir/$file")) { 
	$empty=self::removeEmptyTree("$dir/$file",false);  
      } else {
	$empty=false; 
      }
   }
   

   # If the TOP-level directory should remain untouch we return here.
   if ($keeptop) return $empty;
   
   # Our directory is empty, so we could delete it
   if ($empty) $empty=rmdir($dir);
  
   return $empty;
   
}


# http://stackoverflow.com/questions/2637945/getting-relative-path-from-absolute-path-in-php
public static function getRelativePath($from, $to) {
    // some compatibility fixes for Windows paths
    $from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
    $to   = is_dir($to)   ? rtrim($to, '\/') . '/'   : $to;
    $from = str_replace('\\', '/', $from);
    $to   = str_replace('\\', '/', $to);

    $from     = explode('/', $from);
    $to       = explode('/', $to);
    $relPath  = $to;

    foreach($from as $depth => $dir) {
        // find first non-matching dir
        if($dir === $to[$depth]) {
            // ignore this directory
            array_shift($relPath);
        } else {
            // get number of remaining dirs to $from
            $remaining = count($from) - $depth;
            if($remaining > 1) {
                // add traversals up to first matching dir
                $padLength = (count($relPath) + $remaining - 1) * -1;
                $relPath = array_pad($relPath, $padLength, '..');
                break;
            } else {
                $relPath[0] = './' . $relPath[0];
            }
        }
    }
    return implode('/', $relPath);
}

  
}

?>