<?php
namespace wwerther;

class FileMeta {

  public $start_pathinfo=array();
  public $besttemplate=array();

  public function __construct($filename) {
    $this->filename=$filename;
    $this->start_pathinfo=pathinfo($filename);
  }
  
  public function associate_template($templates) {
    $this->besttemplate=$templates->findbesttemplate($this->start_pathinfo);
    return $this->besttemplate;
  }

  
  function fillmetadata (){
    $filename=$this->filename;
    $template=$this->besttemplate;
    
    global $exiftool;

    $template['meta']=array();
    $template['meta']['fileinfo']=pathinfo($filename);

    if (array_key_exists('metaprovider',$template)) {
      switch ($template['metaprovider']) {
	case 'exiftool':
	  $exiftool->add(array('-g','-struct',$filename));
	  $result=$exiftool->fetchDecoded();
	  # print_r($result);
	  $metamap=$template['metamap'];
	  foreach ($metamap as $meta=>$map) {
	    if (!array_key_exists('operation',$map)) {$map['operation']='copy';}
	    $in=$result[0];
	    foreach(explode(',',$map['path']) as $dive) {
	      $in=$in->$dive;
	    }
	    switch ($map['operation']) {
	      case 'dparse':
		$template['meta'][$meta]=date_parse($in);
	      break;
	      case 'copy':
		$template['meta'][$meta]=$in;
	      break;
	      default: print "Unknown operation ".$map['operation']."\n";
	    }
	}
	break;
	default: print "Non supported metaprovider ".$template['metaprovider']."\n";
      }
    }
    $this->besttemplate=$template;
    return $template;
  }

  
  public function get_newfilename($destbasedir=null) {
    $filename=$this->filename;
    $template=$this->besttemplate;
    if (is_null($destbasedir)) {
      if (array_key_exists('destdir',$template)) {
	$template['meta']['fileinfo']['destbasedir']=$template['destdir'];
	$destbasedir=$template['destdir'];
      } elseif (defined('DESTDIR')) {
	$template['meta']['fileinfo']['destbasedir']=DESTDIR;
	$destbasedir=DESTDIR;
      } else {
	throw new Exception('Destination-Base is not set. Cannot calculate new filename');
      }
    } else {
      $template['meta']['fileinfo']['destbasedir']=$destbasedir;
    }
    $newfilename=preg_replace_callback('/%\{(\d+)?(.+?)(?:\[(.+?)\])?\}/',
    function($treffer) use ($template) {
      switch ($treffer[2]) {
        case 's':return DIRECTORY_SEPARATOR;
        case 'dto': {
          $fill=$treffer[1];
          return sprintf("%$fill".'d',$template['meta']['dto'][$treffer[3]]);
        };
        case 'file': {
          return $template['meta']['fileinfo'][$treffer[3]];
        };
        case 'ext': {
          return strtolower($template['meta']['fileinfo']['extension']);
        };
        default:
          if (array_key_exists($treffer[2],$template['meta'])) {
            return $template['meta'][$treffer[2]];
          }
          return '{'.$treffer[2].'}';
      }
    },
    $template['template']);
    
    $this->newfilename=$newfilename;
    $this->newfilebase=$destbasedir;
    $this->newfilepathinfo=pathinfo($newfilename);
    return array($newfilename,$destbasedir);
  }
  
  public function collides() {
    return file_exists($this->newfilename);
  }
  
  public function linkfile() {
#    print $
    if (!is_dir(dirname($this->newfilename))) mkdir(dirname($this->newfilename),0777,true);
    symlink(realpath($this->filename),$this->newfilename);
  }

#
# Take's a filename and returns the next available free filename in case
# the given filename already exists
#
# Therefore -00 with increasing numbers will be appended before the suffix
# In case the filename already contains a -0000 or similar this part will be used
# for further calculation of the next free filename
#
# The function returns NULL in case no filename could be calculated
#
function findnextfile() {
  $pathinfo=pathinfo($this->newfilename);
  if (file_exists($this->newfilename)) {
      $fname=$pathinfo['filename'];

      if (preg_match('/(.+)-(\d+)$/',$fname,$matches)) {
        $len=strlen($matches[2]);
        $bname=$matches[1];
      } else {
        $len=2;
        $bname=$fname;
      }

      $count=-1;

      do {
        $count++;
        $testname=$pathinfo['dirname'].DIRECTORY_SEPARATOR.sprintf("%s-%0${len}d.",$bname,$count).$pathinfo['extension'];
        # print "Test: $testname\n";
      } while ( (strlen($count)<$len+1) and file_exists($testname));

      if (strlen($count)>$len) {
        print "Could not calculate new filename with appropriate length\n";
        return null;
      }
      $this->newfilename=$testname;
      $this->newfilepathinfo=pathinfo($this->newfilename);
      return array($testname,$this->newfilebase);
  }
    $this->newfilename=$newfilename;
    $this->newfilepathinfo=pathinfo($newfilename);
    return array($newfilename,$this->newfilebase);
}

  public function checksum($type='md5',$mode='full') {
    $this->checksum=self::calc_checksum($this->filename,$type,$mode);
    return $this->checksum;
  }
  
  #
  # Calculate the checksum of a file
  #
  # quick only reads the first MB of a file.
  #
  public static function calc_checksum($filename,$type='md5',$mode='full') {
    if ($mode=='quick') {
      $handle = fopen($filename, "rb");
      $ctx=hash_init($type);
      $count=0;
      while (($count<1024) and ! feof($handle)) {
	hash_update($ctx,fread($handle,1024));
	$count++;
      }
      fclose($handle);
      return "lzy:$type:".hash_final($ctx);
    } 
    return hash_file($type,$filename);
  }

}

?>