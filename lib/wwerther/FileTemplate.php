<?php
namespace wwerther;

class FileTemplate {

  public static $source=null;
  public static $templates=null;

  public function __construct($source) {
    $this->source=$source;
    
    include $source;
    $this->templates=$nametemplates;
  }
  
  
  function findbesttemplate($pathinfo) {
    $foundtemplate=null;
    foreach ($this->templates as $template) {
      if (!array_key_exists('rank',$template)) { $template['rank']=0; }
	switch ($template['matchtype']) {
	  case 'extension':
	    if (strcasecmp($pathinfo['extension'],$template['pattern'])==0) {
	      if (is_null ($foundtemplate)) {
		$foundtemplate=$template;
	      } else {
		if ($foundtemplate['rank']<$template['rank']) {
		  $foundtemplate=$template;
		}
	     }
	    }
	    break;
	  case 'regex':
	    $doesmatch=$true;
	    if (is_array($template['pattern'])) {
	      if ($doesmatch and array_key_exists('dirname',$template['pattern'])) {
		$doesmatch=preg_match($template['pattern']['dirname'],$pathinfo['dirname'] )>0 ? true : false;
	      }
	      if ($doesmatch and array_key_exists('basename',$template['pattern'])) {
		$doesmatch=preg_match($template['pattern']['basename'],$pathinfo['basename'] )>0 ? true : false;
	      }
	      if ($doesmatch and array_key_exists('filename',$template['pattern'])) {
		$doesmatch=preg_match($template['pattern']['filename'],$pathinfo['filename'] )>0 ? true : false;
	      }
	      if ($doesmatch and array_key_exists('extension',$template['pattern'])) {
		$doesmatch=preg_match($template['pattern']['extension'],$pathinfo['extension'] )>0 ? true : false;
	      }
	    } else {
	      $doesmatch=preg_match($template['pattern'],$pathinfo['dirname'].DIRECTORY_SEPARATOR.$pathinfo['basename'],$matches  )>0 ? true : false;
	    }
	    if ($doesmatch) {
	      if (is_null ($foundtemplate)) {
		$foundtemplate=$template;
	      } else {
		if ($foundtemplate['rank']<$template['rank']) {
		  $foundtemplate=$template;
		}
	      }      
	    }
	    break;
	  default: print "Non supported matchtype ".$template['matchtype']."\n";
	}
     }
    return $foundtemplate;
  }  

}


?>