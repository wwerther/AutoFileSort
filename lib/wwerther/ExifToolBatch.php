<?php
# https://github.com/tsmgeek/ExifTool_PHP_Stayopen
namespace wwerther;
/**
 * @brief Class for using ExifTool in Batch-Mode
 * 
 */

# Taken from https://github.com/tsmgeek/ExifTool_PHP_Stayopen
class ExifToolBatch{

    const BUFF_SIZE = 4096;

    const SUMMARY_DIRECTORIES_SCANNED = "directories scanned";
    const SUMMARY_DIRECTORIES_CREATED = "directories created";
    const SUMMARY_FILES_FAILED_CONDITION = "files failed condition";
    const SUMMARY_IMAGE_FILES_CREATED = "image files created";
    const SUMMARY_IMAGE_FILES_UPDATED = "image files updated";
    const SUMMARY_IMAGE_FILES_UNCHANGED = "image files unchanged";
    const SUMMARY_IMAGE_FILES_MOVED = "image files moved";
    const SUMMARY_IMAGE_FILES_COPIED = "image files copied";
    const SUMMARY_FILE_UPDATE_ERRORS = "files weren't updated due to errors";
    const SUMMARY_FILE_CREATE_ERRORS = "files weren't created due to errors";
    const SUMMARY_IMAGE_FILES_READ = "image files read";
    const SUMMARY_IMAGE_FILE_ERRORS = "files could not be read";
    const SUMMARY_OUTPUT_FILES_CREATED = "output files created";
    const SUMMARY_OUTPUT_FILES_APPENDED = "output files appended";

    private $_exiftool = null;

    // Start the exiftool process with this argument
    private $_defexecargs = array('-use MWG');

    // Default Arguments per executed command
        // -g => Groups are unpacked -> "Composite":{<field>=Value, ...}, Exif:{<field>=Value, <field2>=Value, ...}
        // -G => groups stay folded -> "Composite:<field>=Value, Exif:<field>=Value", Exif:<field2>=Value"
        // -j => JSON-Outputformat
        // -struct => Output structured XMP information instead of flattening the tags
        // -coordFormat %.6f  -> Print geoocordinates with 6 decimal numbers
    private $_defargs = array('-g','-j','-struct','-coordFormat','%.6f');

    private $_quietmode = false;
    private $_process=null;
    private $_pipes=null;
    private $_stack=array();
    private $_lastdata=array();
    private $_lasterr=array();
    private $_seq=0;
    private $_socket_get_mode = "fgets";
    private $_socket_fgets_blocking = true;
    private $_debug=0;
    private $_sigalrm=false;
    private $_exiftool_minver=9.15;


    # Based on
    # File:         exif2xmp.args
    # Notes:        The first argument copies most of the EXIF and GPS tags
    #               to XMP-exif/XMP-tiff, and the remaining arguments handle
    #               tags which have different names and/or formats in EXIF and
    #               XMP.
    #
    # References:   http://www.metadataworkinggroup.org/specs/
    PUBLIC static $exif2xmpargs = array (
                '-XMP:all < EXIF:all',
                '-XMP-dc:Description < EXIF:ImageDescription',
                # overwrite date/time values to include sub-second information if available
                '-XMP-photoshop:DateCreated < EXIF:DateTimeOriginal',
                '-XMP-photoshop:DateCreated < Composite:SubSecDateTimeOriginal',
                #-XMP-xmp:CreateDate < EXIF:CreateDate (handled by the mass copy)
                '-XMP-xmp:CreateDate < Composite:SubSecCreateDate',
                #-XMP-xmp:ModifyDate < EXIF:ModifyDate (handled by the mass copy)
                '-XMP-xmp:ModifyDate < Composite:SubSecModifyDate',
                '-XMP-dc:Rights < EXIF:Copyright',
                '-XMP-dc:Creator < EXIF:Artist',
                # overwrite GPS tags which have different formats in XMP
                '-XMP:GPSLatitude < Composite:GPSLatitude',
                '-XMP:GPSLongitude < Composite:GPSLongitude',
                '-XMP:GPSDateTime < Composite:GPSDateTime'
                # end
    );

    # Based on
    # File:         iptc2xmp.args
    # Notes:     1) Also updates Photoshop:IPTCDigest as per MWG recommendation.
    #
    #            2) These arguments will not delete XMP tags which are missing
    #               from the IPTC.  The XMP tags should be deleted beforehand if
    #               required.
    PUBLIC static $iptc2xmpargs = array (
              '-XMP-dc:Creator < IPTC:By-line',
            '-XMP-dc:Description < IPTC:Caption-Abstract',
'-XMP-dc:Rights < IPTC:CopyrightNotice',
'-XMP-dc:Subject < IPTC:Keywords',
'-XMP-dc:Title < IPTC:ObjectName',
'-XMP-photoshop:AuthorsPosition < IPTC:By-lineTitle',
'-XMP-photoshop:CaptionWriter < IPTC:Writer-Editor',
'-XMP-photoshop:Category < IPTC:Category',
'-XMP-photoshop:City < IPTC:City',
'-XMP-photoshop:Country < IPTC:Country-PrimaryLocationName',
'-XMP-photoshop:Credit < IPTC:Credit',
'-XMP-photoshop:DateCreated < IPTC:DateCreated',
# overwrite XMP DateCreated to include date and time if available
'-XMP-photoshop:DateCreated < Composite:DateTimeCreated',
'-XMP-photoshop:Headline < IPTC:Headline',
'-XMP-photoshop:Instructions < IPTC:SpecialInstructions',
'-XMP-photoshop:Source < IPTC:Source',
'-XMP-photoshop:State < IPTC:Province-State',
'-XMP-photoshop:SupplementalCategories < IPTC:SupplementalCategories',
'-XMP-photoshop:TransmissionReference < IPTC:OriginalTransmissionReference',
'-XMP-photoshop:Urgency < IPTC:Urgency',
'-XMP-iptcCore:CountryCode < IPTC:Country-PrimaryLocationCode',
'-XMP-iptcCore:Location < IPTC:Sub-location',
'-XMP-xmp:CreateDate < IPTC:DigitalCreationDate',
'-XMP-xmp:CreateDate < Composite:DigitalCreationDateTime',
# these tags are not free-form text, and may need special handling:
#-XMP-iptcCore:IntellectualGenre < IPTC:ObjectAttributeReference
#-XMP-iptcCore:SubjectCode < IPTC:SubjectReference
'-Photoshop:IPTCDigest=new'
# end

    );



    public static function getInstance($path=null, $args=null){
        static $inst = null;
        if($inst == null){
            $inst = new ExifToolBatch($path, $args);
        }
        return $inst;
    }

    public function __construct($path=null,$args=null){
        if(isset($path)){
            $this->setExifToolPath($path);
        }
        if(isset($args)){
            $this->setDefaultExecArgs($args);
        }
        return $this;
    }

    public function __destruct(){
        if(isset($this->_process))
            $this->close();
    }
  public function setExifToolPath($path){
        if(!file_exists($path)){
            throw new Exception('Exiftool path does not exist');
        }
        $this->_exiftool=$path;
        return $this;
    }

    public function getExifToolPath(){
        return $this->_exiftool;
    }

    public function setDefaultExecArgs($args){
        if(!is_array($args)) $args=array($args);
        $this->_defexecargs=$args;
        return $this;
    }

    public function getDefaultExecArgs(){
        return $this->_defexecargs;
    }

    public function setDefaultArgs($args){
        if(!is_array($args)) $args=array($args);
        $this->_defargs=$args;
        return $this;
    }

    public function getDefaultArgs(){
        return $this->_defargs;
    }

    public function setQuietMode($mode=false){
        if(!is_bool($mode)) return false;
        $this->_quietmode=$mode;
        return $this;
    }

    public function sigterm(){
        $this->close();
        exit;
    }

    public function sigalrm(){
        $this->_sigalrm=true;
        echo "!";
    }

    public function start(){
        $env = null;
        $cwd = ".";
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
            2 => array("pipe", "w")   // stderr is a pipe that the child will write to
        );

        if(is_null($this->_exiftool)){
            throw new Exception('Exiftool path was not set');
        }

        pcntl_signal(SIGTERM,array(&$this,'sigterm'));
        pcntl_signal(SIGALRM,array(&$this,'sigalrm'));

        $this->_process = proc_open($this->_exiftool.' '.implode(' ',$this->_defexecargs).' -stay_open True -@ -', $descriptorspec, $this->_pipes, $cwd, $env);

        if(substr($this->_socket_get_mode,0,6)=="stream"){
            stream_set_blocking ($this->_pipes[1],0);
            stream_set_blocking ($this->_pipes[2],0);
        }else{
            stream_set_blocking ($this->_pipes[1],$this->_socket_fgets_blocking);
            stream_set_blocking ($this->_pipes[2],$this->_socket_fgets_blocking);
        }

        if($this->test()){
            return $this->_process;
        }else{
            throw new Exception('Exiftool did not start');
        }
    }

    public function close(){
        fwrite($this->_pipes[0], "-stay_open\nFalse\n");
        fclose($this->_pipes[0]);
        fclose($this->_pipes[1]);
        fclose($this->_pipes[2]);
        proc_terminate($this->_process);
        unset($this->_pipes);
        unset($this->_process);
        return true;
    }
   private function clearLast(){
        $this->_lastdata=false;
        $this->_lasterr=false;
    }

    private function run(){
        $this->_seq = $this->_seq + 1;
        $seq=$this->_seq;

        $this->clearLast();

        if($this->_quietmode===true){
            fwrite($this->_pipes[0], "-q\n");
            // force echo {ready} to STDOUT if in quiet mode
            fwrite($this->_pipes[0], "-echo3\n");
            fwrite($this->_pipes[0], "{ready".$seq."}\n");
        }

        // force echo {ready} to STDERR
        fwrite($this->_pipes[0], "-echo4\n");
        fwrite($this->_pipes[0], "{ready".$seq."}\n");

        fwrite($this->_pipes[0], "-execute".$seq."\n");

        $output=array();
        $output['STDOUT']=$this->getStreamData(1);
        $output['STDERR']=$this->getStreamData(2);

        return $output;
    }

    public function test(){
        fwrite($this->_pipes[0], "-ver\n");
        $output = $this->run();
        $output=floatval($output['STDOUT']);

        if($output>=$this->_exiftool_minver){
            return true;
        }else{
            throw new Exception('Exiftool version ('.sprintf('%.02f',$output).') is lower than required ('.sprintf('%.02f',$this->_exiftool_minver).')');
        }
    }
    private function checkRunning(){
        if(is_null($this->_process)){
            return $this->start();
        }else{
            $status=proc_get_status($this->_process);
            if($status['running']===false){
                $this->close();
                $this->start();
            }
        }
    }

    private function getStreamData($pipe){
        $endstr="{ready".$this->_seq."}\n";
        $endstr_len=0-strlen($endstr);
        $timeoutStart = time();
        $timeoutStarted = false;
        $timeout=5;

        //get output data
        $output=false;
        $endstr_found=null;
        switch($this->_socket_get_mode){
            case "stream": // fast, high cpu
                do{
                    $str=stream_get_line($this->_pipes[$pipe],self::BUFF_SIZE);
                    $output=$output.$str;
                    usleep(1000);
                }while(strpos($output,$endstr)===false);
                $endstr_found=substr($output,$endstr_len);
                $output=substr($output,0,$endstr_len);
                break;
            case "fgets": // fast, low cpu (blocking), med cpu (non-blocking)
                if($this->_socket_fgets_blocking===true){
                    do{
                        $str=fgets($this->_pipes[$pipe], self::BUFF_SIZE);
                        $output=$output.$str;
                    }while(strpos($str,$endstr)===false);
                }else{
                    $timeoutStart = time();
                    while(1){
                        $str=fgets($this->_pipes[$pipe], self::BUFF_SIZE);
                        $output=$output.$str;
                        if(substr($output,$endstr_len)==$endstr) break;
                        if(time() > $timeout + $timeoutStart) {
                            throw new Exception('Reached timeout getting data');
                        }
                        usleep(1000);
                    }
                }

                $endstr_found=substr($output,$endstr_len);
                $output=substr($output,0,$endstr_len);
                break;
        }

        if($endstr_found!=$endstr){
            throw new Exception('ExifTool out of sequence');
        }

        return $output;
    }

    public function execute($args){
        // merge default args with supplied args
        $argsmerged=array_merge($this->_defargs,$args);
        return $this->execute_args($argsmerged);
    }

    private function execute_args($args){
        $this->checkRunning();

        foreach($args as $arg){
            if(!is_string($arg)) continue;
            fwrite($this->_pipes[0], $arg."\n");
        }

        // get all of the output
        return $this->run();
    }
   public function decode($data){
        if(is_array($data)){
            $dataArr=array();
            foreach($data as $data2){
                if($data3 = json_decode($data2)){
                    if(is_array($data3)){
                        foreach($data3 as $x){
                            $dataArr[]=$x;
                        }
                    }
                }
            }
            return $dataArr;
        }else{
            if($data=json_decode($data)){
                return $data;
            }else{
                return false;
            }
        }
    }

    public function getErrorStr($id=null){
        if(is_array($this->_lasterr) && is_null($id)) return false;
        if(is_string($this->_lasterr)){
            return $this->_lasterr;
        }elseif(isset($this->_lasterr[$id]) && is_array($this->_lasterr[$id])){
            return $this->_lasterr[$id];
        }

        return false;
    }

    public function getError($id=null){
        $lasterr = $this->getErrorStr($id);
        return ($lasterr && $lasterr[0] != " ")?$lasterr:false;
    }
   public function getSummary($msg,$id=null){
        $lasterr = $this->getErrorStr($id);
        if(!$lasterr || empty($lasterr)) return false;
        $pt = $opt = strpos($lasterr,$msg);
        $msgl = strlen($msg);
        $ept = $pt + $msgl;

        if(!$pt || $pt < 2) return false;

        $pt -= 2;

        while($lasterr[$pt] != " ") --$pt;

        $val = substr($lasterr,$pt+1,$opt-$pt-2);
        $val = intval($val);
        return $val;
    }

    public function fetchDecoded(){
        if(!$this->fetch()) return false;
        if($data=json_decode($this->_lastdata)){
            return $data;
        }else{
            return false;
        }
    }

    public function fetch(){
        if(count($this->_stack)){
            $result = $this->execute(array_shift($this->_stack));
            $this->_lastdata = $result['STDOUT'];
            $this->_lasterr = $result['STDERR'];
            return $this->_lastdata;
        }else{
            unset($this->_lastdata);
            return false;
        }
    }

   public function fetchAllDecoded(){
        if(!$this->fetchAll()) return false;
        $dataArr=array();
        foreach($this->_lastdata as $lastdata){
            if($data = json_decode($lastdata)){
                if(is_array($data)){
                    foreach($data as $x){
                        $dataArr[]=$x;
                    }
                }else{
                    $dataArr[]=$data[0];
                }
            }
        }
        return $dataArr;
    }

    public function fetchAll(){
        $data=array();
        $dataErr=array();
        while($args=array_shift($this->_stack)){
            $result = $this->execute($args);
            $data[]=$result['STDOUT'];
            $dataErr[]=$result['STDERR'];
        }
        $this->_lastdata=$data;
        $this->_lasterr=$dataErr;
        return $data;
    }

    public function add($args){
        if(is_array($args)){
            $this->_stack[]=$args;
        }elseif(is_string($args)){
            $this->_stack[]=array($args);
        }else{
            return false;
        }
        return $this;
    }

    public function clear(){
        $this->_stack=array();
        return $this;
    }

}

?>
