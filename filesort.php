<?php
// vim: set filetype=php expandtab tabstop=2 shiftwidth=2 autoindent smartindent:
// kate: replace-tabs on; remove-trailing-space on; tab-width 2

use \wwerther\ExifToolBatch;
use \wwerther\scanDir;
use \wwerther\FileTools;
use \wwerther\FileTemplate;
use \wwerther\FileMeta;

require_once 'vendor/autoload.php';

$exiftool = ExifToolBatch::getInstance('/usr/bin/exiftool');

$filetemplates=new FileTemplate(dirname(__FILE__)."/file_patterns.php");
#print_r ($filetemplates->templates);

define("SRCDIR","./testset/__INBOX");
define("DESTDIR","./testset/__OUTBOX");
 #$searchforextensions=array('mts');#'mts','avi','jpg');
$searchforextensions=array('jpg');#'mts','avi','jpg');

#define("DESTDIR","/sources/bydate");
define("CHECKSUMFILE","checksum.idx");
define("AKAFILE","akafile.idx");

#define("MOVEMETHOD","vmove"); # symlink rename copy move vmove vcopy
#define("VERIFYDESTINATON",false); # true or false

require_once("file_tools.php");

print "Scanning Source-Directory '".SRCDIR."' for files with extensions '".join(',',$searchforextensions)."'\n";
$filelist=scanDir::scan(SRCDIR,$searchforextensions,true);


foreach ($filelist as $filename) {

 print "file '".$filename."'\n";

 $file=new FileMeta($filename);

 #print_r($pathinfo);
 $file->associate_template($filetemplates);

 $foundtemplate=$file->fillmetadata();

 list($newfilename,$destdir)=$file->get_newfilename();

 if ($file->collides()) list($newfilename,$destdir)=$file->findnextfile();
# $newfilename=findnextfile($newfilename);

 print "  --> newname: $newfilename     $destdir".$file->checksum()."\n";

 $file->linkfile();


}

?>
