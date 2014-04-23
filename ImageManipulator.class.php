<?php
/********************************************************************************************
* @script_type -  PHP ProcessWire Class ImageManipulator
* -------------------------------------------------------------------------
* @author      -  Horst Nogajski  --  info at nogajski . de
* -------------------------------------------------------------------------
* $Source: /WEB/pw2/htdocs/site/modules/PageImageManipulator/ImageManipulator.class.php,v $
* $Id: ImageManipulator.class.php,v 1.42 2014/04/23 15:18:59 horst Exp $
*********************************************************************************************/

###  $string = 'some';
###  sprintf($this->_('Text that can be translated including %s dynamic parts.'), $string)   ###

class ImageManipulator extends Wire {

    // must be identical with the module version
        protected $version = 13;

    // information of source imagefile

         /**
        * Filename ImageSourcefile
        */
        protected $filename;

        /**
        * Extension ImageSourcefile
        */
        protected $extension;

        /**
        * Type of image ( 1 = gif | 2 = jpg | 3 = png )
        */
        protected $imageType;

        /**
        * Information about the image (width/height) and more :-)
        */
        protected $image = array();

        /**
        * Was the given image modified?
        */
        protected $modified = false;


    // default options for manipulations

        /**
        * Image quality setting, 1..100
        */
        protected $quality = 90;

        /**
        * Allow images to be upscaled / enlarged?
        */
        protected $upscaling = true;

        /**
        * Allow images to be cropped to achieve necessary dimension? If so, what direction?
        *
        * Possible values: northwest, north, northeast, west, center, east, southwest, south, southeast
        *     or TRUE to crop to center, or FALSE to disable cropping.
        * Default is: TRUE
        */
        protected $cropping = true;

        /**
         * enable auto_rotation according to EXIF-Orientation-Flag
        */
        protected $autoRotation = true;

        /**
        * the default sharpening mode
        *
        * @var array with custom pattern or a string: 'soft' | 'medium' | 'strong' | 'multistep'
        */
        protected $sharpening = 'soft';

         /**
        * Filename ImageTargetfile
        */
        protected $targetFilename;

        /**
        * Extension / Format for resulting Imagefile (default is same as ImageSourcefile-Extension)
        */
        protected $outputFormat;

        /**
        * color array rgb (or optional rgba) for BG-Color (0-255) 0,0,0 = black | 255,255,255 = white | 255,0,0 = red
        */
        protected $bgcolor = array(255, 255, 255, 0);

        protected $thumbnailColorizeCustom = array(0, 0, 0);


    // other properties

        /**
        * Supported image types (@teppo)
        */
        protected $supportedImageTypes = array(
            'gif'  => IMAGETYPE_GIF,
            'jpg'  => IMAGETYPE_JPEG,
            'jpeg' => IMAGETYPE_JPEG,
            'png'  => IMAGETYPE_PNG
        );

        /**
        * valid options, identical to that 5 from (new) ImageSizer,
        * width 3 additions: targetFilename and outputFormat
        *
        *  - targetFilename is needed because we don't want to overwrite the original Imagefile
        *       there are two exceptions:
        *       1) if you have allready created a variation and have passed that to the ImageManipulator
        *          than a targetFilename is not needed.
        *          (we check reference '$image->original' to know about that)
        *       2) using the static function fileAutoRotation, because this is mainly implemented
        *          for correcting an original imagefile on upload! So a file gets uploaded only one
        *          time and only then we may apply rotation to the original image.
        *
        *  - outputFormat is optional and only should give ability to
        *       import for example a JPEG, do something with fancy masking and
        *       save it to PNG-Format with alpha transparency.
        *
        *  - bgcolor may be used when wrapping borders around an image or create Thumbnails like Slides
        *       (squares with landscapes or portraits in it)
        *
        */
        protected $optionNames = array(
            'autoRotation',
            'upscaling',
            'cropping',
            'quality',
            'sharpening',
            'bgcolor',
            'targetFilename',
            'outputFormat',
            'thumbnailColorizeCustom',
            'thumbnailCoordsPermanent'
        );


        protected $defaultOptions = array(
            'autoRotation' => true,
            'upscaling' => true,
            'cropping' => true,
            'quality' => 90,
            'sharpening' => 'soft',
            'bgcolor' => array(255, 255, 255, 0),
            'targetFilename' => null,
            'outputFormat' => null,
            'thumbnailColorizeCustom' => array(0, 0, 0),
            'thumbnailCoordsPermanent' => false
        );

        /**
         * Directions for positioning watermarks
         */
        protected $positioningValues = array(
            'nw' => 'northwest',
            'n'  => 'north',
            'ne' => 'northeast',
            'w'  => 'west',
            'e'  => 'east',
            'sw' => 'southwest',
            's'  => 'south',
            'se' => 'southeast',
        );

        protected $validIptcTags = array('005','007','010','012','015','020','022','025','030','035','037','038','040','045','047','050','055','060','062','063','065','070','075','080','085','090','092','095','100','101','103','105','110','115','116','118','120','121','122','130','131','135','150','199','209','210','211','212','213','214','215','216','217');

        protected $isOriginal;

        protected $thumbnailBoost;

        protected $thumbnailCoordsPermanent;

        private $propertyNames;

        private $entryItem;

        private $pageimage;

        private $bypassOperations = false;

        private $imDibDst = null;        // is the output for every intermediate im-method!




    // Construct & Destruct the ImageManipulator for a single image

        public function __construct($entryItem=null, $options=array(), $bypassOperations=false) {

            // version check module == class
            $m = wire('modules')->get('PageImageManipulator')->getModuleInfo();
            $m = preg_replace('/(\d)(?=\d)/', '$1.', str_pad("{$m['version']}", 3, "0", STR_PAD_LEFT));
            $c = preg_replace('/(\d)(?=\d)/', '$1.', str_pad("{$this->version}", 3, "0", STR_PAD_LEFT));
            if(!version_compare($m, $c, '=')) {
                throw new WireException("The versions of Module PageImageManipulator ($m) and it dependency classfile ImageManipulator ($c) are inconsistent!)");
                return;
            }

            $this->bypassOperations = true===$bypassOperations ? true : false;
            // validate PageImage, FileImage, MemoryImage
            if($entryItem instanceof Pageimage) {
                $this->pageimage = $entryItem;
                $this->entryItem = 'page';
                $this->filename = $entryItem->filename;
                $this->isOriginal = $entryItem->original===NULL ? true : false;
                if(!$this->isOriginal) {
                    // traversing up 'til reaching root reference
                    $this->originalImage = $entryItem->original;
                    while(null !== $this->originalImage->original) {
                        $reference = $this->originalImage->original;
                        $this->originalImage = $reference;
                    }
                }
            }
            elseif(is_string($entryItem) && is_file($entryItem)) {
                $this->entryItem = 'file';
                $this->filename = $entryItem;
                $this->isOriginal = false;
            }
            elseif($this->isResourceGd($entryItem)) {
                $this->entryItem = 'memory';
                $this->filename = null;
                $this->isOriginal = false;
            }
            else {
                $this->entryItem = 'null';
                $this->filename = null;
                $this->isOriginal = false;
            }

            // populate all Property-Names into array to make most
            // of them accessible as ReadOnly from outside the class :-)
            $tmp1 = array_keys(get_class_vars(get_class($this)));
            $tmp2 = array_merge(array_keys(get_class_vars(get_parent_class($this))), array('imDibDst','originalImage'));
            foreach($tmp1 as $v) {
                if(in_array($v, $tmp2)) { continue; }
                $this->propertyNames[] = $v;
            }
            $this->propertyNames[] = 'iptcRaw';
            $this->propertyNames[] = 'optionNames';
            sort($this->propertyNames);

            // check if we can be used to boost the thumbnail module, - is it installed?
            if(true === ($this->thumbnailBoost = (bool)wire('modules')->isInstalled('ProcessCropImage'))) {
                // now check that at least the minimum version number of ProcessCropImage is installed:
                $needed = '1.0.2';
                $a = wire('modules')->get('ProcessCropImage')->getModuleInfo();
                $actual = preg_replace('/(\d)(?=\d)/', '$1.', str_pad("{$a['version']}", 3, "0", STR_PAD_LEFT));
                $this->thumbnailBoost = version_compare($actual, $needed, '<') ? false : true;
            }

            // merging all options: classdefaults, global custom values from config.php and instance-options
            $this->optionNames = array_keys($this->defaultOptions);
            $this->configOptions1 = wire('config')->imageSizerOptions;
            if(!is_array($this->configOptions1)) $this->configOptions1 = array();
            $this->configOptions2 = wire('config')->imageManipulatorOptions;
            if(!is_array($this->configOptions2)) $this->configOptions2 = array();
            $options = is_array($options) ? $options : array();
            $tmp = array_merge($this->defaultOptions, $this->configOptions1, $this->configOptions2, $options);
            $options = array();
            foreach($tmp as $k=>$v) {
                if(in_array($k, $this->optionNames)) {
                    $options["$k"] = $v;
                }
            }
            $options['thumbnailCoordsPermanent'] = $this->thumbnailBoost ? $options['thumbnailCoordsPermanent'] : false;
            $this->setOptions($options);

            // init by entry type
            if('page'==$this->entryItem || 'file'==$this->entryItem) {
                $this->initFileImage();
            }
            if('memory'==$this->entryItem) {
                $this->initMemoryImage($entryItem);
            }
        }

        private function initFileImage() {
            // check read / write access of Imagefile
            if(!$this->checkDiskfile($this->filename, true) ) {
                throw new WireException($this->filename . "does not exist or is not readable!");
            }
            $p = pathinfo($this->filename);
            $basename = $p['basename'];
            $this->extension = strtolower($p['extension']);
            // do an early and fast check for imageType,
            // if exif_imageType function is not present, we will check later with getimagesize for the correct imageType,
            // we don't want rely on the file-extension because for falses with this Teppo once have added these check ;-)
            if(function_exists("exif_imageType")) {
                $this->imageType = exif_imageType($this->filename);
                if(!in_array($this->imageType, $this->supportedImageTypes)) { // @teppo
                    throw new WireException("$basename is an unsupported image type");
                }
            }
            // parse the imagefile and retrieve infos
            if(!$this->loadImageInfo()) {
                throw new WireException("$basename is not a recognized image");
            }
        }

        private function initMemoryImage($im) {
            $this->imDibDst = @imagecreatetruecolor(imagesx($im), imagesy($im));
            imagecopy($this->imDibDst, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
            if(true === ($this->dibIsLoaded = $this->isResourceGd($this->imDibDst))) {
                $info = array(imagesx($im),imagesy($im));
                $this->image = array(
                    'width'        => $info[0],
                    'height'    => $info[1],
                    'landscape' => (bool)($info[0]>$info[1]),
                    'ratio'     => floatval(($info[0]>=$info[1] ? $info[0]/$info[1] : $info[1]/$info[0]))
                );
            }
        }

        protected function loadImageInfo() {
            if('memory'==$this->entryItem) {
                return false;
            }

            $additional_info = array();
            $info = @getimagesize($this->filename,$additional_info);
            if( $info===false || ! isset($info[2]) ) {
                return false;
            }

            // imagetype (gif jpeg png == 1 2 3)
            $this->imageType = $info[2];

            // width, height, and more infos
            $types = array(1=>'gif',2=>'jpg',3=>'png');
            $this->image = array(
                'type'        => $types[$info[2]],
                'imageType'   => $info[2],
                'mimetype'    => isset($info['mime']) ? $info['mime'] : @image_type_to_mime_type($info[2]),
                'width'       => $info[0],
                'height'      => $info[1],
                'landscape'   => (bool)($info[0]>$info[1]),
                'ratio'       => floatval(($info[0]>=$info[1] ? $info[0]/$info[1] : $info[1]/$info[0]))
            );

            // try to read EXIF-Orientation-Flag
            $correction = ImageManipulator::fileGetExifOrientation($this->filename,true);
            if(is_array($correction)) {
                $this->image['orientationCorrection'] = $correction;
            }

            // read metadata if present and if its the first call of the method
            if( isset($additional_info['APP13']) && ! isset($this->iptcRaw) ) {
                $iptc = iptcparse($additional_info["APP13"]);
                if(is_array($iptc)) {
                    $this->iptcRaw = $iptc;
                }
            }
            if($this->imageType==1) {
                $this->image['bits'] = isset($info['bits']) ? $info['bits'] : 8;
            }
            if($this->imageType==2) {
                $this->image['bits'] = isset($info['bits']) ? $info['bits'] : 8;
                $this->image['channels'] = isset($info['channels']) ? $info['channels'] : 3;
                if($this->image['channels']==3) $this->image['colspace'] = 'DeviceRGB';
                elseif($this->image['channels']==4) $this->image['colspace'] = 'DeviceCMYK';
                else $this->image['colspace'] = 'DeviceGray';
            }
            if($this->imageType==3) {
                $this->extendedInfoPng($this->image);
            }
            return true;
        }

        /**
        * horst: this one I use since 2005, - I think I have found it somewhere in the FPDF-Project (GPL) !
        */
        private function extendedInfoPng(&$a) {
            $f=@fopen($this->filename,'rb');
            if($f===FALSE)
                return;
            //Check signature
            if(@fread($f,8) != chr(137).'PNG'.chr(13).chr(10).chr(26).chr(10))
            {
                @fclose($f);
                return;
            }
            //Read header chunk
            @fread($f,4);
            if(@fread($f,4) != 'IHDR')
            {
                @fclose($f);
                return;
            }

            $errors = array();
            $a['width']        = $this->freadint($f);
            $a['height']    = $this->freadint($f);
            $a['bits']         = ord(@fread($f,1));

            $ct = ord(@fread($f,1));
            if($ct==0) {
                $a['channels'] = 1;
                $a['colspace'] = 'DeviceGray';
            }
            elseif($ct==2) {
                $a['channels'] = 3;
                $a['colspace'] = 'DeviceRGB';
            }
            elseif($ct==3) {
                $a['channels'] = 1;
                $a['colspace'] = 'Indexed';
            }
            else{
                $a['channels'] = $ct;
                $a['colspace'] = 'DeviceRGB';
                $a['alpha']    = true;
            }

            if(ord(@fread($f,1))!=0)
                $errors[] = 'Unknown compression method!';
            if(ord(@fread($f,1))!=0)
                $errors[] = 'Unknown filter method!';
            if(ord(@fread($f,1))!=0)
                $errors[] = 'Interlacing not supported!';

            //Scan chunks looking for palette, transparency and image data
            @fread($f,4);
            $pal='';
            $trns='';
            $data='';
            do {
                $n = $this->freadint($f);
                $type = @fread($f,4);
                if($type=='PLTE')
                {
                    //Read palette
                    $pal = @fread($f,$n);
                    @fread($f,4);
                }
                elseif($type=='tRNS')
                {
                    //Read transparency info
                    $t = @fread($f,$n);
                    if($ct==0)
                    {
                        $trns = array(ord(substr($t,1,1)));
                    }
                    elseif($ct==2)
                    {
                        $trns = array(ord(substr($t,1,1)),ord(substr($t,3,1)),ord(substr($t,5,1)));
                    }
                    else
                    {
                        $pos = strpos($t,chr(0));
                        if(is_int($pos))
                        {
                            $trns = array($pos);
                        }
                    }
                    @fread($f,4);
                }
                elseif($type=='IEND')
                {
                    break;
                }
                else
                {
                    fread($f,$n+4);
                }
            } while($n);

            @fclose($f);
            if($a['colspace']=='Indexed' and empty($pal)) $errors[] = 'Missing palette!';
            if(count($errors)>0) $a['errors'] = $errors;
        }

        public function __destruct() {
            $this->release();
        }



    // Methods to set and get Properties

        /**
         * Here you can specify multiple options as Array, whereas with the
         * single set* functions you can specify single options
         *
         * @param array $options May contain key-value pairs for any valid Options-Propertyname
         * @return this
         */
        public function setOptions(array $options) {
            if(count($options)==0 || isset($options[0])) { // we only use associative arrays!
                // passed an empty array
                return $this;
            }
            $check = array('outputFormat'=>ImageManipulator::getOutputFormat(), 'targetFilename'=>ImageManipulator::getTargetFilename());
            foreach($options as $key => $value) {
                if(!in_array($key, $this->optionNames)) {
                    // TODO 2 -c errorhandling : create ErrorLog-Entry
                    continue;
                }
                if(isset($ret)) unset($ret);
                switch($key) {
                    case 'autoRotation':
                    case 'upscaling':
                    case 'cropping':
                    case 'extendedImageinfo':
                    case 'thumbnailCoordsPermanent':
                        if(is_bool($value)) {
                            $ret = $value===true ? true : false;
                        }
                        elseif(is_int($value)) {
                            $ret = $value===1 ? true : false;
                        }
                        elseif(is_string($value) && in_array(strtolower($value), array('1','on','true','yes','y'))) {
                            $ret = true;
                        }
                        elseif(is_string($value) && in_array(strtolower($value), array('0','off','false','no','n'))) {
                            $ret = false;
                        }
                        else {
                            // TODO 2 -c errorhandling : create ErrorLog-Entry
                            $ret = $this->getDefaultOption($key);
                            if(!is_bool($ret)) {
                                continue;
                            }
                        }
                        break;

                    case 'quality':
                        $ret = intval($value);
                        $ret = $ret<1 || $ret>100 ? $this->getDefaultOption($key) : $ret;
                        $ret = $ret>0 && $ret<101 ? $ret : 90;
                        break;

                    case 'sharpening':
                        if(is_array($value) && count($value)===3) {
                            $ret = $value;
                        }
                        elseif(is_string($value) && in_array(strtolower($value), array('none','soft','medium','strong','multistep'))) {
                            $ret = strtolower($value);
                        }
                        else {
                            // TODO 2 -c errorhandling : create ErrorLog-Entry
                            $ret = in_array($this->getDefaultOption($key), array('none','soft','medium','strong','multistep')) ? $this->getDefaultOption($key) : 'soft';
                        }
                        break;

                    case 'outputFormat':
                        if(is_int($value) && in_array($value,$this->supportedImageTypes)) {
                            $a = array_flip($this->supportedImageTypes);
                            $ret = $a[$value];
                        }
                        elseif(is_string($value) && in_array(strtolower($value),array_keys($this->supportedImageTypes))) {
                            $ret = strtolower($value)=='jpeg' ? 'jpg' : strtolower($value);
                        }
                        else {
                            $ret = strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
                            if(!in_array($ret, array_keys($this->supportedImageTypes))){
                                // TODO 2 -c errorhandling : create ErrorLog-Entry
                                $ret = 'jpg';
                            }
                        }
                        break;

                    case 'targetFilename':
                        $ret = strval($value);
                        break;

                    case 'bgcolor':
                        $color = $this->sanitizeColor($value);
                        $ret = empty($color) ? array(127,127,127) : $color;
                        break;

                    case 'thumbnailColorizeCustom':
                        $color = $this->sanitizeColor($value, false, true);
                        $ret = empty($color) ? array(127,127,127) : $color;
                        break;
                }
                if(isset($ret)) {
                    $this->$key = $ret;
                }
            }

            if($check['outputFormat']!=$this->getOutputFormat() || $check['targetFilename']!=$this->getTargetFilename()) {
                $this->adjustOutputFormat();
            }

            return $this;
        }
        public function setQuality($value)                   { return $this->setOptions( array('quality'=>$value) ); }
        public function setUpscaling($value)                 { return $this->setOptions( array('upscaling'=>$value) ); }
        public function setCropping($value)                  { return $this->setOptions( array('cropping'=>$value) ); }
        public function setAutoRotation($value)              { return $this->setOptions( array('autoRotation'=>$value) ); }
        public function setSharpening($value)                { return $this->setOptions( array('sharpening'=>$value) ); }
        public function setTargetFilename($value)            { return $this->setOptions( array('targetFilename'=>$value) ); }
        public function setOutputFormat($value)              { return $this->setOptions( array('outputFormat'=>$value) ); }
        public function setBgcolor($value)                   { return $this->setOptions( array('bgcolor'=>$value) ); }
        public function setThumbnailColorizeCustom($value)   { return $this->setOptions( array('thumbnailColorizeCustom'=>$value) ); }

        /**
         * Return an array of the current options
         *
         * @return array
         */
        public function getOptions($property='all') {
            $a = array();
            foreach($this->optionNames as $key) {
                if($property==$key) {
                    return $this->$key;
                }
                $a[$key] = $this->$key;
            }
            if('all'==$property) return $a;
        }
        public function getQuality()                         { return $this->getOptions( 'quality' ); }
        public function getUpscaling()                       { return $this->getOptions( 'upscaling' ); }
        public function getCropping()                        { return $this->getOptions( 'cropping' ); }
        public function getAutoRotation()                    { return $this->getOptions( 'autoRotation' ); }
        public function getSharpening()                      { return $this->getOptions( 'sharpening' ); }
        public function getTargetFilename()                  { return $this->getOptions( 'targetFilename' ); }
        public function getOutputFormat()                    { return $this->getOptions( 'outputFormat' ); }
        public function getBgcolor()                         { return $this->getOptions( 'bgcolor' ); }
        public function getThumbnailColorizeCustom()         { return $this->getOptions( 'thumbnailColorizeCustom' ); }


        public function getImageInfo() {
            return $this->image;
        }



        /**
        * returns true if $var is a valid resource of type GD-Image
        *
        * @param resource $var
        */
        public static function isResourceGd(&$var) {
            return is_resource($var) && strtoupper(substr(get_resource_type($var),0,2))=='GD' ? true : false;
        }


        public function pimSave($outputFormat=null) {
            if('memory'==$this->entryItem) {
                return $this->getMemoryImage();
            }
            // optionally get & set outputFormat
            $outputFormat = null===$outputFormat ? $this->outputFormat : $outputFormat;
            if(is_int($outputFormat) && in_array($outputFormat,$this->supportedImageTypes)) {
                $a = array_flip($this->supportedImageTypes);
                $outputFormat = $a[$outputFormat];
            }
            elseif(is_string($outputFormat) && in_array(strtolower($outputFormat),array_keys($this->supportedImageTypes))) {
                $outputFormat = strtolower($outputFormat)=='jpeg' ? 'jpg' : strtolower($outputFormat);
            }
            else {
                $outputFormat = null;
            }
            if(null!==$outputFormat) {
                $this->setOutputFormat($outputFormat);
            }
            // get TargetFilename
            $targetFilename = $this->targetFilename;
            if(empty($targetFilename) && !$this->isOriginal) {
                // this is the case if PIM was called with an Imagefile instead of a PageImage
                $targetFilename = $this->filename;
            }
            if(empty($targetFilename)) {
                // oh, that's bad! There's no targetFilename and overwriting original Image is not allowed!
                throw new WireException("Error when trying to save the MemoryImage: we have no Targetfilename!");
                return false;
            }

            // if there was no other operation until now, we need to load the Image, e.g. for imagetype conversion
            if(!isset($this->dibIsLoaded)) {
                $this->imLoad(true);
            }

            if(!$this->bypassOperations) {
                // just to play save
                $dest = $targetFilename.'.tmp';

                if(!empty($this->gammaLinearized)) {
                    // Correct gamma from linearized 1.0 back to 2.0
//                    imagegammacorrect($this->imDibDst, 1.0, 2.0);
                }

                // write to file
                $result = false;
                switch($this->supportedImageTypes[$outputFormat]) {
                    case IMAGETYPE_GIF:
                        $result = imagegif($this->imDibDst, $dest);
                        break;
                    case IMAGETYPE_PNG:
                        imagealphablending($this->imDibDst, false);
                        imagesavealpha($this->imDibDst, true);
                        // convert 1-100 (worst-best) scale to 0-9 (best-worst) scale for PNG
                        $quality = round(abs(($this->quality - 100) / 11.111111));
                        $result = imagepng($this->imDibDst, $dest, $quality);
                        break;
                    case IMAGETYPE_JPEG:
                        imagealphablending($this->imDibDst, false);
                        $result = imagejpeg($this->imDibDst, $dest, $this->quality);
                        break;
                }
                // free memory
                $this->release();

                if($result === false) {
                    if(is_file($dest)) @unlink($dest);
                    return false;
                }

                @unlink($targetFilename); // if it exists
                if(!rename($dest, $targetFilename)) {
                    return false;
                }

                // if we've retrieved IPTC-Metadata from sourcefile, we write it back now
                if(isset($this->iptcRaw)) {
                    $content = iptcembed($this->iptcPrepareData(), $targetFilename);
                    if($content!==false) {
                        $dest = $targetFilename.'.tmp';
                        if(strlen($content) == @file_put_contents($dest, $content, LOCK_EX)) {
                            // on success we replace the file
                            @unlink($targetFilename);
                            if(!rename($dest, $targetFilename)) {
                                return false;
                            }
                        }
                        else {
                            // it was created a temp diskfile but not with all data in it
                            if(file_exists($dest)) @unlink($dest);
                        }
                    }
                }
            }

//            $this->loadImageInfo();  // this is from ImageResizer, don't know if we can use this here
//            $this->modified = true;  // this is from ImageResizer, don't know if we can use this here

            if('page'==$this->entryItem) {
                $pageimage = clone $this->pageimage;
                $pageimage->setFilename($targetFilename);
                $pageimage->setOriginal($this->pageimage);
                return $pageimage;
            }

            if('file'==$this->entryItem) {
                return $targetFilename;
            }

            return true;
        }
        public function save() {
            return $this->pimSave(null);
        }


        public function release() {
            if($this->isResourceGd($this->imDibDst)) {
                @imagedestroy($this->imDibDst);
            }
            if(isset($this->dibIsLoaded)) {
                unset($this->dibIsLoaded);
            }
        }


        public function removePimVariations() {
            if('page'!==$this->entryItem) {
                return $this;
            }
            $basename = $this->pageimage->basename();
            $directory = str_replace($basename, '', $this->pageimage->filename());
            $p = pathinfo($directory . $basename);
            $basename = $p['filename'];
            $re = '/^pim_.*?' . $basename . '.*?' . '\.(gif|jpg|png)' . '$/';
            // iterate through directory and check all files beginning with 'pim_'
            // and include $basename, regardless which filetype: gif, jpg, png
            $dir = new DirectoryIterator($directory);
            foreach($dir as $file) {
                if($file->isDir() || $file->isDot()) continue;
                if(preg_match($re, $file->getFilename())) {
                    @unlink($directory . $file->getFilename());
                }
            }
        }


        public function getPimVariations() {
            if('page' != $this->entryItem) {
                throw new WireException("This PageImageManipulator-Instance is not of type pageimage! ({$this->entryItem})");
                return;
            }
            if(!is_null($this->variations)) return $this->variations;

            $variations = new Pageimages($this->pageimage->pagefiles->page);

            $basename = $this->pageimage->basename();
            $directory = str_replace($basename, '', $this->pageimage->filename());
            $p = pathinfo($directory . $basename);
            $basename = $p['filename'];
            $re = '/^pim_.*?' . $basename . '.*?' . '\.(gif|jpg|png)' . '$/';
            // iterate through directory and check all files beginning with 'pim_'
            // and include $basename, regardless which filetype: gif, jpg, png
            $dir = new DirectoryIterator($directory);
            foreach($dir as $file) {
                if($file->isDir() || $file->isDot()) continue;
                if(preg_match($re, $file->getFilename())!==1) {
                    continue;
                }
                $pageimage = clone $this->pageimage;
                $pathname = $file->getPathname();
                if(DIRECTORY_SEPARATOR != '/') $pathname = str_replace(DIRECTORY_SEPARATOR, '/', $pathname);
                $pageimage->setFilename($pathname);
                $pageimage->setOriginal($this->pageimage);
                $variations->add($pageimage);
            }

            $this->variations = $variations;
            return $variations;
        }



        /**
        * at first call, it loads the image into memory, and after that everytime create and provide a working copy
        */
        private function imLoad($onlyLoad=false) {
            if($this->bypassOperations) return;
            if(!isset($this->dibIsLoaded) || $this->dibIsLoaded!==true) {
                $this->imDibDst = @imagecreatefromstring( file_get_contents($this->filename) );
                $this->dibIsLoaded = $this->isResourceGd($this->imDibDst);
                if($this->dibIsLoaded!==true) {
                    return false;
                }
                // if we have a GIF, we store transparentColorIndex
                if(IMAGETYPE_GIF == $this->imageType) {
                    // @mrx GIF transparency
                    $transparentIndex = imagecolortransparent($this->imDibDst);
                    $this->GifTransparentColor = $transparentIndex != -1 ? imagecolorsforindex($this->imDibDst, $transparentIndex) : 0;
                }
                if(IMAGETYPE_PNG != $this->imageType) {
                    // linearize gamma to 1.0
//                    imagegammacorrect($this->imDibDst, 2.0, 1.0);
//                    $this->gammaLinearized = true;
                }
                if(true===$onlyLoad) return true;
            }
            // return a working copy of the current state
            $im = $this->createTruecolor(imagesx($this->imDibDst), imagesy($this->imDibDst));
            if(imagecopy($im, $this->imDibDst, 0, 0, 0, 0, imagesx($this->imDibDst), imagesy($this->imDibDst))) {
                return $im;
            }
            return false;
        }

        /**
        *  write back the working copy to the dst-dib
        */
        private function imWrite($im) {
            if($this->bypassOperations) return;
            if(!isset($this->dibIsLoaded) || !$this->isResourceGd($im)) {
                return false;
            }
            $this->imDibDst = $this->createTruecolor(imagesx($im), imagesy($im));
            $res = imagecopy($this->imDibDst, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
            @imagedestroy($im);
            if(!$res) {
                throw new WireException("Error when trying to write back MemoryImage.");
            }
            return $res;
        }

        /**
        * we use this instead of imagecreatetruecolor because
        * we keep track of Alphablending and TransparentColor
        *
        * @param mixed $w
        * @param mixed $h
        * @return resource
        */
        private function createTruecolor($w, $h) { //}, $bg=null) {
            if($this->bypassOperations) {
                return;
            }
            $im = imagecreatetruecolor($w, $h);
            if($this->imageType == IMAGETYPE_PNG) {
                // @adamkiss PNG transparency
                imagealphablending($im, false);
                imagesavealpha($im, true);
            }
            elseif($this->imageType == IMAGETYPE_GIF && !empty($this->GifTransparentColor)) {
                // @mrx GIF transparency
                $transparentNew = imagecolorallocate($im, $this->GifTransparentColor['red'], $this->GifTransparentColor['green'], $this->GifTransparentColor['blue']);
                $transparentNewIndex = imagecolortransparent($im, $transparentNew);
                imagefill($im, 0, 0, $transparentNewIndex);
            }
            else {
                imagealphablending($im, false);
//                if(is_array($bg) && count($bg)==4) {
//                    $bg = array_values($this->sanitizeColor($bg, true));
//                    $bgcolor = imagecolorallocatealpha($im, $bg[0], $bg[1], $bg[2], $bg[3]);
//                }
//                elseif(is_array($bg) && count($bg)==3) {
//                    $bg = array_values($this->sanitizeColor($bg));
//                    $bgcolor = imagecolorallocate($im, $bg[0], $bg[1], $bg[2]);
//                }
//                else {
//                    // we take the global bgcolor
//                    $bgcolor = count($this->bgcolor)==4 ? imagecolorallocatealpha($im, $this->bgcolor[0], $this->bgcolor[1], $this->bgcolor[2], $this->bgcolor[3]) : imagecolorallocate($im, $this->bgcolor[0], $this->bgcolor[1], $this->bgcolor[2]);
//                }
//                imagefilledrectangle($im, 0, 0, $w, $h, $bgcolor);
//                imagealphablending($im, true);
            }
            return $im;
        }



        public function getMemoryImage() {
            return $this->imLoad();
        }

        public function setMemoryImage($im) {
            if($this->bypassOperations) {
                return $this;
            }
            if(! $this->isResourceGd($im)) {
                return false;
            }
            if(!$this->imWrite($im)) {
                return false;
            }
            return $this;
        }



        public function flip($vertical=false) {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $sx  = imagesx($im);
            $sy  = imagesy($im);
            $im2 = $this->createTruecolor($sx, $sy);
            if(true===$vertical) {
                $res = @imagecopyresampled($im2, $im, 0, 0, 0, ($sy-1), $sx, $sy, $sx, 0-$sy);
            }
            else {
                $res = @imagecopyresampled($im2, $im, 0, 0, ($sx-1), 0, $sx, $sy, 0-$sx, $sy);
            }
            @imagedestroy($im);
            if(!$res || !$this->isResourceGd($im2)) {
                throw new WireException("Error when trying to flip MemoryImage.");
                return false; // TODO 5 -c errorhandling: Throw WireExeption
            }
            return $this->imWrite($im2) ? $this : false;
        }


        public function rotate($degree, $bgcolor=array()) {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $bg = is_array($bgcolor) && count($bgcolor)>=3 ? $this->sanitizeColor($bgcolor,true) : $this->bgcolor;
            $bgcolor = count($bg)==4 ? imagecolorallocatealpha($im, $bg[0], $bg[1], $bg[2], $bg[3]) : imagecolorallocate($im, $bg[0], $bg[1], $bg[2]);
            $degree = (is_float($degree) || is_int($degree)) && $degree > -361 && $degree < 361 ? $degree : false;
            if($degree===false) {
                throw new WireException("Error with rotate MemoryImage: wrong param for degree");
                return false;
            }
            if(in_array($degree, array(-360,0,360))) {
                return $this;
            }
            #imagealphablending($im, false);
            #imagesavealpha($im, true);
            $im2 = imagerotate($im, $degree, $bgcolor, 0);
            #imagealphablending($im2, false);
            #imagesavealpha($im2, true);
            imagedestroy($im);
            if(!$this->isResourceGd($im2)) {
                throw new WireException("Error when trying to rotate MemoryImage.");
                return false;
            }
            return $this->imWrite($im2) ? $this : false;
        }


        public function crop($pos_x, $pos_y, $width, $height) {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            if($pos_x + $width > imagesx($im) || $pos_y + $height > imagesy($im)) {
                throw new WireException("Error with crop MemoryImage: invalid params given: $pos_x, $pos_y, $width, $height");
                return false;
            }
            $im2 = $this->createTruecolor($width, $height);
            if(!@imagecopyresized($im2, $im, 0, 0, $pos_x, $pos_y, $width, $height, $width, $height)) {  //if( ! @imagecopyresampled($im2, $im, 0, 0, $pos_x, $pos_y, $width, $height, $width, $height) )
                throw new WireException("Error when trying to crop MemoryImage.");
                return false;
            }
            @imagedestroy($im);
            if(!$this->isResourceGd($im2)) {
                throw new WireException("Error when trying to crop MemoryImage.");
                return false;
            }
            return $this->imWrite($im2) ? $this : false;
        }


        public function resize($dst_width=0, $dst_height=0, $sharpen_mode=null) {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $sharpen_mode = is_string($sharpen_mode) && in_array($sharpen_mode, array('none', 'soft', 'medium', 'strong', 'multistep')) ? $sharpen_mode : $this->sharpening;
             $dst_height = $dst_height===0 ? ceil( ($dst_width / imagesx($im)) * imagesy($im) ) : $dst_height;
             $dst_width = $dst_width===0 ? ceil( ($dst_height / imagesy($im)) * imagesx($im) ) : $dst_width;
            $im2 = $this->createTruecolor($dst_width, $dst_height);
            if(!@imagecopyresampled($im2, $im, 0, 0, 0, 0, $dst_width, $dst_height, imagesx($im), imagesy($im)) ) {
                throw new WireException("Error when trying to resize MemoryImage.");
                return false;
            }
            @imagedestroy($im);
            if(!$this->isResourceGd($im2)) {
                throw new WireException("Error when trying to crop MemoryImage.");
                return false;
            }
            $res = $this->imWrite($im2) ? $this : false;
            if('none'==$sharpen_mode) {
                return $res;
            }
            return false===$res ? false : $this->sharpen($sharpen_mode);
        }


        public function width($dst_width, $sharpen_mode=null) {
            return $this->resize($dst_width, 0, $sharpen_mode);
        }


        public function height($dst_height, $sharpen_mode=null) {
            return $this->resize(0, $dst_height, $sharpen_mode);
        }


        public function sharpen($mode=null) {
            if(null===$mode) {
                $mode = $this->sharpening;
            }
            if($this->bypassOperations || 'none'==$mode) {
                return $this;
            }

            // due to a bug in PHP's bundled GD-Lib with the function imageconvolution in some PHP versions
            // we have to bypass this for those who have to run on this PHP versions
            // see: https://bugs.php.net/bug.php?id=66714
            // and here under GD: http://php.net/ChangeLog-5.php#5.5.11
            $buggyPHP = (version_compare(phpversion(), '5.5.8', '>') && version_compare(phpversion(), '5.5.11', '<')) ? true : false;
            if($buggyPHP) {
                if(is_array($mode) && count($mode)===3) {
                    $mode = 'soft';
                }
                switch($mode) {
                    case 'multistep':
                        $amount=120;
                        $radius=0.5;
                        $threshold=5;
                        break;

                    case 'strong':
                        $amount=160;
                        $radius=1.0;
                        $threshold=7;
                        break;

                    case 'medium':
                        $amount=130;
                        $radius=0.75;
                        $threshold=7;
                        break;


                    case 'soft':
                    default:
                        $amount=100;
                        $radius=0.5;
                        $threshold=3;
                        break;
                }

                return $this->unsharpMask($amount, $radius, $threshold);
            }

            if(is_array($mode) && count($mode)===3) {
                $sharpenMatrix = $mode;
                $mode = 'custom';
            }
            else {
                if(!in_array($mode,array('none','soft','medium','strong'))) {
                    throw new WireException("Wrong param for sharpen: (". htmlentities($mode) .")");
                    return false;
                }
                switch($mode) {
                    case 'multistep':
                        $sharpenMatrix = array(
                            array( -1.2, -1, -1.2 ),
                            array( -1,   20, -1 ),
                            array( -1.2, -1, -1.2 )
                            );
                        break;

                    case 'strong':
                        $sharpenMatrix = array(
                            array( -1.2, -1, -1.2 ),
                            array( -1,   16, -1   ),
                            array( -1.2, -1, -1.2 )
                            );
                        break;

                    case 'medium':
                        $sharpenMatrix = array(
                            array( -1.1, -1, -1.1 ),
                            array( -1,   20, -1 ),
                            array( -1.1, -1, -1.1 )
                            );
                        break;

                    case 'soft':
                    default:
                        $sharpenMatrix = array(
                            array( -1, -1, -1 ),
                            array( -1, 24, -1 ),
                            array( -1, -1, -1 )
                            );
                        break;
                }
            }
            // calculate the sharpen divisor
            $divisor = array_sum(array_map('array_sum', $sharpenMatrix));
            $offset = 0;
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            if(!@imageconvolution($im, $sharpenMatrix, $divisor, $offset)) {
                throw new WireException("Error when trying to sharpen MemoryImage.");
                return false; // TODO 5 -c errorhandling: Throw WireExeption
            }
            if(!$this->isResourceGd($im)) {
                throw new WireException("Error when trying to sharpen MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }


        public function stepResize($dst_width=0, $dst_height=0) {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $src_width = imagesx($im);
            $src_height = imagesy($im);
            $dst_height = $dst_height===0 ? ceil( ($dst_width / $src_width) * $src_height ) : $dst_height;
            $dst_width = $dst_width===0 ? ceil( ($dst_height / $src_height) * $src_width ) : $dst_width;
            while( $dst_width < ($width = ceil( $src_width / 2 )) ) {
                 $height = ceil(($width / $src_width) * $src_height);
                if(false === $this->resize($width, $height, true, 'soft')) {
                    throw new WireException("Error when trying to multistepResize MemoryImage.");
                    return false;
                }
                else {
                    if(false === ($im = $this->imLoad())) {
                        throw new WireException("Cannot load the MemoryImage!");
                        return false;
                    }
                }
                $src_width = imagesx($im);
                $src_height = imagesy($im);
            }
            return $this->resize($dst_width, $dst_height);
        }


        /**
         * Unsharp Mask for PHP - version 2.1.1
         *
         * Unsharp mask algorithm by Torstein Hønsi 2003-07.
         * thoensi_at_netcom_dot_no.
         * Please leave this notice.
         *
         * http://vikjavev.no/computing/ump.php
         *
         */
        public function unsharpMask($amount=100, $radius=0.5, $threshold=3) {

            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($img = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }

            // Attempt to calibrate the parameters to Photoshop:
            if($amount > 500) $amount = 500;
            $amount = $amount * 0.016;
            if($radius > 50) $radius = 50;
            $radius = $radius * 2;
            if($threshold > 255) $threshold = 255;

            $radius = abs(round($radius));     // Only integers make sense.
            if($radius == 0) {
                return $img;
            }
            $w = imagesx($img);
            $h = imagesy($img);
            $imgCanvas = imagecreatetruecolor($w, $h);
            $imgBlur = imagecreatetruecolor($w, $h);

            // due to a bug in PHP's bundled GD-Lib with the function imageconvolution in some PHP versions
            // we have to bypass this for those who have to run on this PHP versions
            // see: https://bugs.php.net/bug.php?id=66714
            // and here under GD: http://php.net/ChangeLog-5.php#5.5.11
            $buggyPHP = (version_compare(phpversion(), '5.5.8', '>') && version_compare(phpversion(), '5.5.11', '<')) ? true : false;

            // Gaussian blur matrix:
            //
            //    1    2    1
            //    2    4    2
            //    1    2    1
            //
            //////////////////////////////////////////////////
            if(function_exists('imageconvolution') && !$buggyPHP) {
                $matrix = array(
                    array( 1, 2, 1 ),
                    array( 2, 4, 2 ),
                    array( 1, 2, 1 )
                );
                imagecopy ($imgBlur, $img, 0, 0, 0, 0, $w, $h);
                imageconvolution($imgBlur, $matrix, 16, 0);
            } else {
                // Move copies of the image around one pixel at the time and merge them with weight
                // according to the matrix. The same matrix is simply repeated for higher radii.
                for ($i = 0; $i < $radius; $i++)    {
                    imagecopy ($imgBlur, $img, 0, 0, 1, 0, $w - 1, $h); // left
                    imagecopymerge ($imgBlur, $img, 1, 0, 0, 0, $w, $h, 50); // right
                    imagecopymerge ($imgBlur, $img, 0, 0, 0, 0, $w, $h, 50); // center
                    imagecopy ($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h);

                    imagecopymerge ($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 33.33333 ); // up
                    imagecopymerge ($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 25); // down
                }
            }

            if($threshold>0) {
                // Calculate the difference between the blurred pixels and the original
                // and set the pixels
                for($x = 0; $x < $w-1; $x++) { // each row
                    for($y = 0; $y < $h; $y++) { // each pixel

                        $rgbOrig = ImageColorAt($img, $x, $y);
                        $rOrig = (($rgbOrig >> 16) & 0xFF);
                        $gOrig = (($rgbOrig >> 8) & 0xFF);
                        $bOrig = ($rgbOrig & 0xFF);

                        $rgbBlur = ImageColorAt($imgBlur, $x, $y);

                        $rBlur = (($rgbBlur >> 16) & 0xFF);
                        $gBlur = (($rgbBlur >> 8) & 0xFF);
                        $bBlur = ($rgbBlur & 0xFF);

                        // When the masked pixels differ less from the original
                        // than the threshold specifies, they are set to their original value.
                        $rNew = (abs($rOrig - $rBlur) >= $threshold)
                            ? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig))
                            : $rOrig;
                        $gNew = (abs($gOrig - $gBlur) >= $threshold)
                            ? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig))
                            : $gOrig;
                        $bNew = (abs($bOrig - $bBlur) >= $threshold)
                            ? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig))
                            : $bOrig;

                        if(($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) {
                            $pixCol = ImageColorAllocate($img, $rNew, $gNew, $bNew);
                            ImageSetPixel($img, $x, $y, $pixCol);
                        }
                    }
                }
            } else {
                for($x = 0; $x < $w; $x++) { // each row
                    for($y = 0; $y < $h; $y++) { // each pixel
                        $rgbOrig = ImageColorAt($img, $x, $y);
                        $rOrig = (($rgbOrig >> 16) & 0xFF);
                        $gOrig = (($rgbOrig >> 8) & 0xFF);
                        $bOrig = ($rgbOrig & 0xFF);

                        $rgbBlur = ImageColorAt($imgBlur, $x, $y);

                        $rBlur = (($rgbBlur >> 16) & 0xFF);
                        $gBlur = (($rgbBlur >> 8) & 0xFF);
                        $bBlur = ($rgbBlur & 0xFF);

                        $rNew = ($amount * ($rOrig - $rBlur)) + $rOrig;
                        if($rNew>255) {
                            $rNew=255;
                        } else if($rNew<0) {
                            $rNew=0;
                        }
                        $gNew = ($amount * ($gOrig - $gBlur)) + $gOrig;
                        if($gNew>255) {
                            $gNew=255;
                        }
                        else if($gNew<0) {
                            $gNew=0;
                        }
                        $bNew = ($amount * ($bOrig - $bBlur)) + $bOrig;
                        if($bNew>255) {
                            $bNew=255;
                        }
                        else if($bNew<0) {
                            $bNew=0;
                        }
                        $rgbNew = ($rNew << 16) + ($gNew <<8) + $bNew;
                        ImageSetPixel($img, $x, $y, $rgbNew);
                    }
                }
            }
            imagedestroy($imgCanvas);
            imagedestroy($imgBlur);

            if(!$this->isResourceGd($img)) {
                throw new WireException("Error when applying filter grayscale to  MemoryImage.");
                return false;
            }

            return $this->imWrite($img) ? $this : false;
        }




    // Filter Methods

        /*-----------------------------------------------------------------------------------------------------
         The PHP documentation misses the exact meaning and valid ranges of the arguments for ImageFilter().
         According to the 5.2.0 sources the arguments are:

         IMG_FILTER_BRIGHTNESS
         -255 = min brightness, 0 = no change, +255 = max brightness

         IMG_FILTER_CONTRAST
         -100 = max contrast, 0 = no change, +100 = min contrast (note the direction!)

         IMG_FILTER_COLORIZE
         Adds (subtracts) specified RGB values to each pixel. The valid range for each color is -255...+255, not 0...255. The correct order is red, green, blue.
         -255 = min, 0 = no change, +255 = max
         This has not much to do with IMG_FILTER_GRAYSCALE.

         IMG_FILTER_SMOOTH
         Applies a 9-cell convolution matrix where center pixel has the weight arg1 and others weight of 1.0. The result is normalized by dividing the sum with arg1 + 8.0 (sum of the matrix).
         any float is accepted, large value (in practice: 2048 or more) = no change

         ImageFilter seem to return false if the argument(s) are out of range for the chosen filter.
        -----------------------------------------------------------------------------------------------------*/

        public function grayscale() {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $res = @imagefilter($im, IMG_FILTER_GRAYSCALE);
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when applying filter grayscale to  MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }


        public function sepia($rgb = array(27,12,-12)) {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $rgb = is_array($rgb) && count($rgb)==3 ? $rgb : array(27,12,-12);
            $res = @imagefilter($im, IMG_FILTER_GRAYSCALE);
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when applying filter sepia to  MemoryImage.");
                return false;
            }
            $res = @imagefilter($im, IMG_FILTER_COLORIZE, $rgb[0], $rgb[1], $rgb[2]);
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when applying filter sepia to  MemoryImage.");
                return false;
            }
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when applying filter sepia to  MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }


        public function colorize($anyColor) {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $color = $this->sanitizeColor($anyColor, true, true);
            $alpha = count($color)==4 ? intval(array_pop($color)) : 0;
            if(!is_array($anyColor)) {
                // we have got a hexcolor or a w3c name or a string like 'rgb(n,n,n)'
                $rgb = array();
                foreach($color as $c) {
                    $rgb[] = intval(($c * 2) - 255);
                }
            }
            else {
                $rgb = $color;
            }
            if(version_compare(phpversion(), '5.2.5', '>=')) {
                $res = imagefilter($im, IMG_FILTER_COLORIZE, $rgb[0], $rgb[1], $rgb[2], $alpha);
            }
            else {
                $res = imagefilter($im, IMG_FILTER_COLORIZE, $rgb[0], $rgb[1], $rgb[2]);
            }
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when trying to apply filter colorize to MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }


        public function pixelate($blockSize=3, $useAdvancedPixelation=true) {
            if($this->bypassOperations) {
                return $this;
            }
            if(version_compare(phpversion(), '5.3.0', '<')) {
                return $this;  // Pixelation support was added with PHP 5.3.0
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $useAdvancedPixelation = true===$useAdvancedPixelation ? true : false;
            $blockSize = is_int($blockSize) && $blockSize>0 ? $blockSize : 3;
            $res = @imagefilter($im, IMG_FILTER_PIXELATE, $blockSize, $useAdvancedPixelation);
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when trying to apply filter pixelate to MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }


        public function negate() {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $res = @imagefilter($im, IMG_FILTER_NEGATE);
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when trying to apply filter negate to MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }

        // level 0 +255
        public function smooth($level=127) {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $level = intval($level)>=0 && intval($level)<=255 ? intval($level) : 127;
            $level = (intval($level / 10) - 25) * -1;
            $level = $level<1 ? 1 : $level;
            $res = @imagefilter($im, IMG_FILTER_SMOOTH, $level);
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when trying to apply filter smooth to MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }

        // level -255 0 +255
        public function brightness($level) {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $level = intval($level)>=-255 && intval($level)<=255 ? intval($level) : 0;
            $res = @imagefilter($im, IMG_FILTER_BRIGHTNESS, $level);
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when trying to apply filter brightness to MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }

        // level -255 0 +255
        public function contrast($level) {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $level = intval($level)>=-255 && intval($level)<=255 ? intval($level) : 0;
            $level = intval($level * -2 / 5);
            $res = @imagefilter($im, IMG_FILTER_CONTRAST, $level);
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when trying to apply filter contrast to MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }


        public function blur() {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $res = @imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when trying to apply filter blur to MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }


        public function emboss() {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $res = @imagefilter($im, IMG_FILTER_EMBOSS);
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when trying to apply filter emboss to MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }


        public function edgedetect() {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            $res = @imagefilter($im, IMG_FILTER_EDGEDETECT);
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when trying to apply filter edgedetect to MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }



        /**
        * this method shrinks a big watermarkLogo to fit into the sourcefile
        * small Logos can be positioned:
        *
        *     NW - N - NE
        *     |    |    |
        *     W  - C -  E
        *     |    |    |
        *     SW - S - SE
        *
        * @param mixed $pngAlphaImage  can be a Filename or a PageImage
        * @param mixed $position       is one out of: N, E, S, W, C, NE, SE, SW, NW, - or: north, east, south, west, center, northeast, southeast, southwest, northwest
        * @param mixed $padding        is padding to the borders in percent! default is 5 (%)
        */
        public function watermarkLogo($pngAlphaImage, $position='center', $padding=2) {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            if($pngAlphaImage instanceof Pageimage) {
                $watermarkfile = $pngAlphaImage->filename;
            }
            else {
                $watermarkfile = $pngAlphaImage;
            }
            if(!$this->checkDiskfile($watermarkfile, true)) {
                throw new WireException("Cannot read the pngAlphaImageFile!");
                return false;
            }
            $wm = @imagecreatefrompng($watermarkfile);
            if(!$this->isResourceGd($wm)) {
                throw new WireException("Cannot load the pngAlphaImage into memory!");
                return false;
            }
            @imagealphablending($im, true);
            $sourcefile_width=imagesx($im);
            $sourcefile_height=imagesy($im);
            $padding = !is_int($padding) || $padding<0 || $padding>25 ? 2 : $padding;
            $padding = $sourcefile_width > $sourcefile_height ? intval($sourcefile_height / 100 * $padding) : intval($sourcefile_width / 100 * $padding);

            $watermarkfile_width=imagesx($wm);
            $watermarkfile_height=imagesy($wm);
            $watermarkfile_width_new=imagesx($wm);
            $watermarkfile_height_new=imagesy($wm);
            $calc = new hn_SizeCalc();
            $calc->down($watermarkfile_width_new, $watermarkfile_height_new, $sourcefile_width-$padding, $sourcefile_height-$padding);
            unset($calc);
            $wm2 = $this->createTruecolor($watermarkfile_width_new, $watermarkfile_height_new);
            @imagealphablending($wm2, false);
            if(!@imagecopyresampled($wm2, $wm, 0, 0, 0, 0, $watermarkfile_width_new, $watermarkfile_height_new, $watermarkfile_width, $watermarkfile_height)) {
                throw new WireException("Error when trying to resize watermarkLogo to fit to the MemoryImage.");
                return false;
            }
            @imagedestroy($wm);

            // now with positioning of small watermarks
            $position = !is_string($position) || (!in_array(strtolower($position), $this->positioningValues) && !in_array(strtolower($position), array_keys($this->positioningValues))) ? 'center' : strtolower($position);
            $posX = intval(($sourcefile_width - $watermarkfile_width_new) / 2);
            $posY = intval(($sourcefile_height - $watermarkfile_height_new) / 2);
            if('center'!=$position && 'c'!=$position) {
                switch($position) {
                    case 'n':
                    case 'north':
                        $posX = intval(($sourcefile_width - $watermarkfile_width_new) / 2);
                        $posY = $padding;
                        break;
                    case 's':
                    case 'south':
                        $posX = intval(($sourcefile_width - $watermarkfile_width_new) / 2);
                        $posY = intval(($sourcefile_height - $watermarkfile_height_new) - $padding);
                        break;

                    case 'w':
                    case 'west':
                        $posX = $padding;
                        $posY = intval(($sourcefile_height - $watermarkfile_height_new) / 2);
                        break;
                    case 'e':
                    case 'east':
                        $posX = intval($sourcefile_width - $watermarkfile_width_new - $padding);
                        $posY = intval(($sourcefile_height - $watermarkfile_height_new) / 2);
                        break;

                    case 'nw':
                    case 'northwest':
                        $posX = $padding;
                        $posY = $padding;
                        break;
                    case 'ne':
                    case 'northeast':
                        $posX = intval($sourcefile_width - $watermarkfile_width_new - $padding);
                        $posY = $padding;
                        break;

                    case 'sw':
                    case 'southwest':
                        $posX = $padding;
                        $posY = intval(($sourcefile_height - $watermarkfile_height_new) - $padding);
                        break;
                    case 'se':
                    case 'southeast':
                        $posX = intval($sourcefile_width - $watermarkfile_width_new - $padding);
                        $posY = intval(($sourcefile_height - $watermarkfile_height_new) - $padding);
                        break;
                }
            }
            $res = imagecopy($im, $wm2, $posX, $posY, 0, 0, $watermarkfile_width_new, $watermarkfile_height_new);
            @imagedestroy($wm2);
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when trying to apply a watermarkLogo to the MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }


        public function watermarkLogoTiled($pngAlphaImage) {
            if($this->bypassOperations) {
                return $this;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            if($pngAlphaImage instanceof Pageimage) {
                $watermarkfile = $pngAlphaImage->filename;
            }
            else {
                $watermarkfile = $pngAlphaImage;
            }
            if(!$this->checkDiskfile($watermarkfile, true)) {
                throw new WireException("Cannot read the pngAlphaImageFile!");
                return false;
            }
            $wm = @imagecreatefrompng($watermarkfile);
            if(!$this->isResourceGd($wm)) {
                throw new WireException("Cannot load the pngAlphaImage into memory!");
                return false;
            }
            imagealphablending($wm, true);
            imagesavealpha($wm, true);
            imagealphablending($im,true);
            imagesettile($im, $wm);
            $res = @imagefilledrectangle($im, 0, 0, imagesx($im)-1, imagesy($im)-1, IMG_COLOR_TILED);
            if(!$res || !$this->isResourceGd($im)) {
                throw new WireException("Error when trying to apply watermarkLogoTiled to MemoryImage.");
                return false;
            }
            return $this->imWrite($im) ? $this : false;
        }


        public function watermarkText($text, $size=10, $position='center', $padding=2, $opacity=50, $trueTypeFont=null) {
            if($this->bypassOperations) {
                return $this;
            }
            if(!is_string($text) || strlen($text)<1) {
                throw new WireException("There is no text defined for watermarkText!");
                return false;
            }
            $font = empty($trueTypeFont) ? dirname(__FILE__).'/freesansbold.ttf' : $trueTypeFont;
            if(!$this->checkDiskfile($font, true)) {
                throw new WireException("Cannot read the TrueTypeFile needed for watermarkText!");
                return false;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }

            $lx = imagesx($im);
            $ly = imagesy($im);
            $padding = !is_int($padding) || $padding<0 || $padding>25 ? 2 : $padding;
            $padding = $lx > $ly ? intval($ly / 100 * $padding) : intval($lx / 100 * $padding);
            $size = !is_int($size) || $size<1 || $size>300 ? 10 : $size;
            $position = !is_string($position) || (!in_array(strtolower($position), $this->positioningValues) && !in_array(strtolower($position), array_keys($this->positioningValues))) ? 'center' : strtolower($position);
            $opacity = !is_int($opacity) || $opacity<1 || $opacity>100 ? 70 : $opacity;

            // calculate textbox
            $box = @imagettfbbox($size, 0, $font, $text);
            $width = abs($box[4] - $box[0]);
            $height = abs($box[5] - $box[1]);
            while($height + (2 * $padding) >= $ly || $width + (2 * $padding) >= $lx) {
                if($size==1) {
                    throw new WireException("There is to much text for the selected output image dimensions in watermarkText!");
                    return false;
                }
                $size -= 1;
                $box = @imagettfbbox($size, 0, $font, $text);
                $width = abs($box[4] - $box[0]);
                $height = abs($box[5] - $box[1]);
            }
            // now we should have text dimensions that fit into image dimensions or we have already returned false

            // calculate coordinates for position
            switch($position) {
                case 'nw':
                case 'northwest':
                    $posX = $padding;
                    $posY = $padding + $height;
                    break;

                case 'n':
                case 'north':
                    $posX = intval(($lx - $width) / 2);
                    $posY = $padding + $height;
                    break;

                case 'ne':
                case 'northeast':
                    $posX = intval($lx - $width - $padding);
                    $posY = $padding + $height;
                    break;


                case 'w':
                case 'west':
                    $posX = $padding;
                    $posY = intval($ly / 2);
                    break;

                case 'e':
                case 'east':
                    $posX = intval($lx - $width - $padding);
                    $posY = intval($ly / 2);
                    break;


                case 'sw':
                case 'southwest':
                    $posX = $padding;
                    $posY = $ly - $padding;
                    break;

                case 's':
                case 'south':
                    $posX = intval(($lx - $width) / 2);
                    $posY = $ly - $padding;
                    break;

                case 'se':
                case 'southeast':
                    $posX = intval($lx - $width - $padding);
                    $posY = $ly - $padding;
                    break;


                default:
                    $posX = intval(($lx - $width) / 2);
                    $posY = intval($ly / 2);
            }

            // analyse original image
            $histo = $this->getHistogramData($im);
            // 0 - 84 | 85 - 170 | 171 - 255
            $ranking = array(
                'b'=>array_sum(array_slice($histo,0,85,true)),
                'g'=>array_sum(array_slice($histo,85,86,true)),
                'w'=>array_sum(array_slice($histo,171,85,true))
            );
            arsort($ranking, SORT_NUMERIC);
            $flipped  = array_flip($ranking);
            $histoRes = array_shift($flipped);

            // create overlay mask
            $im1 = $this->createTruecolor($lx, $ly);
            switch($histoRes) {
                case 'b': // we are on dark image
                    $color_bg = @imagecolorallocate($im1,   0,   0,   0);
                    $color_fg = @imagecolorallocate($im1, 255, 255, 255);
                    break;
                case 'g': // we are on medium image
                    $color_bg = @imagecolorallocate($im1, 127, 127, 127);
                    $color_fg = @imagecolorallocate($im1, 255, 255, 255);
                    break;
                case 'w': // we are on light image
                    $color_bg = @imagecolorallocate($im1, 255, 255, 255);
                    $color_fg = @imagecolorallocate($im1, 127, 127, 127);
                    break;
            }

            // put in the text
            @imagefilledrectangle($im1, 0, 0, $lx, $ly, $color_bg);
            @imageantialias($im1, FALSE);
            @imagecolortransparent($im1, $color_bg);
            @imagettftext($im1, $size, 0, $posX, $posY, $color_fg, $font, $text);


            // im1 ist weiss auf schwarz
            $im4 = $this->createTruecolor($lx, $ly);
            @imagecopy($im4, $im1, 0, 0, 0, 0, $lx, $ly);
            switch($histoRes) {
                case 'b': // we are on dark image
                    $color_bg2 = @imagecolorallocate($im4, 255, 255, 255);
                    break;
                case 'g': // we are on medium image
                    $color_bg2 = @imagecolorallocate($im4, 255, 255, 255);
                    break;
                case 'w': // we are on light image
                    $color_bg2 = @imagecolorallocate($im4, 127, 127, 127);
                    break;
            }
            @imagecolortransparent($im4, $color_bg2);

            // im2 wird grayscale BG
            $im2 = $this->createTruecolor($lx, $ly);
            @imagecopy($im2, $im, 0, 0, 0, 0, $lx, $ly);
            @imagefilter($im2, IMG_FILTER_GRAYSCALE);
            @imagefilter($im2, IMG_FILTER_GAUSSIAN_BLUR);
            //@imagefilter($im2, IMG_FILTER_SMOOTH, 1);

            // im2 is blurred gray now

            // we merge it with text at 50% transparency
            @imagecolortransparent($im1, $color_bg);
            @imagecopymerge($im2, $im1, 0, 0, 0, 0, $lx, $ly, 50);

            // we negate the transpareny color of the text image
            @imagecolortransparent($im1, $color_fg);
            @imagefilter($im1, IMG_FILTER_SMOOTH, 50);

            // we merge it with $im2 at 100%
            @imagecopymerge($im2, $im1, 0,0,0,0, $lx, $ly, 100);


            // now we should have a black BG with something like 50% glass-effect that we merge into the original
            @imagefilter($im2, IMG_FILTER_SMOOTH, 10);
            @imagecopy($im1, $im2, 0, 0, 0, 0, $lx, $ly);
            @imagecolortransparent($im1, $color_bg);

            // we get our original image
            $im2 = $this->createTruecolor($lx, $ly);
            @imagecopy($im2, $im, 0, 0, 0, 0, $lx, $ly);

            // and merge it together
            @imagecopymerge($im2, $im1, 0,0,0,0, $lx, $ly, $opacity);

            // release memory images
            @imagedestroy($im);
            @imagedestroy($im1);
            @imagedestroy($im4);

            // write back resulting image
            return $this->imWrite($im2) ? $this : false;
        }


        public function canvas($width, $height=null, $bgcolor=null, $position=null, $padding=null) {
            if($this->bypassOperations) {
                return $this;
            }
            if(is_array($width) && null===$height) {
                // we have an options array as param
                $options = $width;
                $valid = array('width', 'height', 'bgcolor', 'position', 'padding');
                foreach($options as $k=>$v) {
                    if(!in_array($k, $valid)) {
                        continue;
                    }
                    $$k = $v;
                }
            }
            if(null===$bgcolor) {
                $bgcolor = $this->bgcolor;
            }
            $bgcolor = $this->sanitizeColor($bgcolor, true);
            $position = !is_string($position) || (!in_array(strtolower($position), $this->positioningValues) && !in_array(strtolower($position), array_keys($this->positioningValues))) ? 'center' : strtolower($position);
            if(!is_int($width) || $width<8 || !is_int($height) || $height<8) {
                throw new WireException("Wrong params width or height with method canvas!");
                return false;
            }
            if(false === ($im = $this->imLoad())) {
                throw new WireException("Cannot load the MemoryImage!");
                return false;
            }
            imagealphablending($im, false);
            $imWidth = imagesx($im);
            $imHeight = imagesy($im);
            $padding = !is_int($padding) || $padding<0 || $padding>25 ? 0 : $padding;
            $padding = $imWidth > $imHeight ? intval($imHeight / 100 * $padding) : intval($imWidth / 100 * $padding);
            $imWidthNew = imagesx($im);
            $imHeightNew = imagesy($im);
            $calc = new hn_SizeCalc();
            $calc->down($imWidthNew, $imHeightNew, $width - $padding, $height - $padding);
            unset($calc);
            if($imWidthNew!=$imWidth || $imHeightNew!=$imHeight) {
                $im2 = imagecreatetruecolor($imWidthNew, $imHeightNew);
                imagealphablending($im2, false);
                if(!imagecopyresampled($im2, $im, 0, 0, 0, 0, $imWidthNew, $imHeightNew, $imWidth, $imHeight)) {
                    throw new WireException("Error when trying to resize watermarkLogo to fit to the MemoryImage.");
                    return false;
                }
                imagealphablending($im2, false);
                $im = $this->createTruecolor($imWidthNew, $imHeightNew);
                imagealphablending($im, false);
                imagecopy($im, $im2, 0, 0, 0, 0, $imWidthNew, $imHeightNew);
                imagedestroy($im2);
            }
            imagealphablending($im, false);
            $canvas = imagecreatetruecolor($width, $height);
            imagealphablending($canvas, false);
            #imagesavealpha($canvas, true);
            $bga = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $bga);
            imagealphablending($canvas, true);
            #imagesavealpha($canvas, true);
            $bg = count($bgcolor)==4 ? imagecolorallocatealpha($canvas, $bgcolor[0], $bgcolor[1], $bgcolor[2], $bgcolor[3]) : imagecolorallocate($canvas, $bgcolor[0], $bgcolor[1], $bgcolor[2]);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $bg);
            imagealphablending($canvas, true);
            #imagesavealpha($canvas, true);
            switch($position) {
                case 'n':
                case 'north':
                    $posX = intval(($width - $imWidthNew) / 2);
                    $posY = $padding;
                    break;
                case 's':
                case 'south':
                    $posX = intval(($width - $imWidthNew) / 2);
                    $posY = intval(($height - $imHeightNew) - $padding);
                    break;
                case 'w':
                case 'west':
                    $posX = $padding;
                    $posY = intval(($height - $imHeightNew) / 2);
                    break;
                case 'e':
                case 'east':
                    $posX = intval($width - $imWidthNew - $padding);
                    $posY = intval(($height - $imHeightNew) / 2);
                    break;
                case 'nw':
                case 'northwest':
                    $posX = $padding;
                    $posY = $padding;
                    break;
                case 'ne':
                case 'northeast':
                    $posX = intval($width - $imWidthNew - $padding);
                    $posY = $padding;
                    break;
                case 'sw':
                case 'southwest':
                    $posX = $padding;
                    $posY = intval(($height - $imHeightNew) - $padding);
                    break;
                case 'se':
                case 'southeast':
                    $posX = intval($width - $imWidthNew - $padding);
                    $posY = intval(($height - $imHeightNew) - $padding);
                    break;
                default:
                    $posX = intval(($width - $imWidthNew) / 2);
                    $posY = intval(($height - $imHeightNew) / 2);
            }
            $res = @imagecopy($canvas, $im, $posX, $posY, 0, 0, $imWidthNew, $imHeightNew);
            imagedestroy($im);
            imagealphablending($canvas, true);
            #imagesavealpha($canvas, false);
            if(!$res || !$this->isResourceGd($canvas)) {
                throw new WireException("Error when trying to apply canvas to the MemoryImage.");
                return false;
            }
            return $this->imWrite($canvas) ? $this : false;
        }



    // static oneLiner Methods that can be called with only a filename passed to them

        public static function fileGetExifOrientation($filename, $return_correctionArray=false) {
            // first value is rotation-degree and second value is flip-mode: 0=NONE | 1=HORIZONTAL | 2=VERTICAL
            $corrections = array(
                '1' => array(  0, 0),
                '2' => array(  0, 1),
                '3' => array(180, 0),
                '4' => array(  0, 2),
                '5' => array(270, 1),
                '6' => array(270, 0),
                '7' => array( 90, 1),
                '8' => array( 90, 0)
            );
            if(!function_exists('exif_read_data')) {
                return false;
            }
            $exif = @exif_read_data($filename, 'IFD0');
            if(!is_array($exif) || !isset($exif['Orientation']) || !in_array(strval($exif['Orientation']), array_keys($corrections))) {
                return false;
            }
            if($return_correctionArray !== true) {
                return intval($exif['Orientation']);
            }
            return $corrections[strval($exif['Orientation'])];
        }


        public static function fileAutoRotation($filename, $quality=100) {
            if(!is_file($filename) || !is_readable($filename)) {
                return false;
            }
            // check orientation
            $cor = ImageManipulator::fileGetExifOrientation($filename,true);
            if(!is_array($cor)) {
                return false; // could not check, wrong filetype?, no exif-info?
            }
            if($cor[0]===0 && $cor[1]===0) {
                return true; // checked, no correction needed
            }

            // correction is needed
            if(!is_writable($filename)) {
                return false;
            }
            // sanitize params
            $quality = is_int($quality) && $quality>0 && $quality<=100 ? $quality : 100;

            // start Manipulator with filename passed over
            $fim = new ImageManipulator($filename);
            $fim->setQuality($quality);

            if(false===$fim->rotate($cor[0])) {
                $fim->release();
                return false;
            }
            if(false===$fim->flip(($cor[1]===2 ? true : false))) {
                $fim->release();
                return false;
            }
            $res = $fim->save($filename);
            return $res===$filename;
        }


    // the next three methods are used to boost apeisas ThumbnailModule

        public static function fileThumbnailModule(Pageimage $sourcePageimage, $targetPath, $prefix, $cropX, $cropY, $cropW, $cropH, $targetWidth, $targetHeight, $quality=90, $sharpening='soft', $colorize='none') {
            $options = array('quality'=>$quality, 'targetFilename'=>$targetPath); //, 'thumbnailCoordsPermanent'=>true);
            $pim = new ImageManipulator($sourcePageimage, $options);

            if($cropW==0 || $cropH==0) {
                // user has clicked on button Crop&Go, but has not drawn the rectangle with cropping values!!
                $w = $pim->image['width'];
                $h = $pim->image['height'];
                $cropW = $targetWidth;
                $cropH = $targetHeight;
                $calc = new hn_SizeCalc();
                $calc->up($cropW, $cropH, $w, $h);
                unset($calc);
                $cropX = intval(($w - $cropW) / 2);
                $cropY = intval(($h - $cropH) / 2);
            }

            if(false===$pim->crop($cropX, $cropY, $cropW, $cropH)) {
                throw new WireException("Error when trying to crop ThumbnailFile.");
                return false;
            }
            if('multistep'==$sharpening) {
                if(false===$pim->stepResize($targetWidth, $targetHeight)) {
                    throw new WireException("Error when trying stepResize ThumbnailFile.");
                    return false;
                }
            }
            else {
                if(!in_array($sharpening,array('none','soft','medium','strong'))) {
                    $sharpening = 'soft';
                }
                if(false===$pim->resize($targetWidth, $targetHeight, $sharpening)) {
                    throw new WireException("Error when trying to resize ThumbnailFile.");
                    return false;
                }
            }
            switch($colorize) {
                case 'grayscale':
                    $pim->grayscale();
                    break;
                case 'sepia':
                    $pim->sepia();
                    break;
                case 'cyan':
                    $pim->sepia(array(-8,10,14));
                    break;
                case 'custom':
                    // get the custom color
                    if(!empty($pim->thumbnailColorizeCustom)) {
                        $color = $pim->sanitizeColor($pim->thumbnailColorizeCustom, false, true);
                    }
                    else {
                        $color = array(0,0,0);
                    }
                    $pim->sepia($color);
                    break;
            }
            if(false===$pim->save()) {
                throw new WireException("Error when trying to save ThumbnailFile.");
                return false;
            }
            if($pim->thumbnailCoordsPermanent) {
                // for permanent storage: write coords into IPTC customs field
                $pim->fileThumbnailModuleCoordsWrite($prefix, $cropX, $cropY, $cropW, $cropH, $quality, $sharpening, $colorize);
            }
            unset($pim);
            return true;
        }

        public static function fileThumbnailModuleCoordsRead(Pageimage $sourcePageimage, &$x1, &$y1, &$w, &$h, &$params, $prefix=null) {
            $options = array(); //'thumbnailCoordsPermanent'=>true);
            $pim = new ImageManipulator($sourcePageimage, $options);
            if(!$pim->thumbnailCoordsPermanent) {
                unset($pim);
                return false;
            }
            $a = $pim->getIPTCraw();
            unset($pim);
            if(!isset($a['2#215'])) {
                return false;
            }
            // if $prefix === null we return the complete array with all subsets
            if(null === $prefix) {
                return unserialize($a['2#215'][0]);
            }
            // if is requested a specific prefix, we extract it if available and return true | false for success
            $a = unserialize($a['2#215'][0]);
            if(!is_array($a) || !isset($a[$prefix])) {
                return false;
            }
            $x1 = $a[$prefix][0];
            $y1 = $a[$prefix][1];
            $w = $a[$prefix][2];
            $h = $a[$prefix][3];
            foreach(array('quality','sharpening','colorize') as $k=>$v) {
                if(isset($a[$prefix][$k+4])) {
                    $params[$v] = $a[$prefix][$k+4];
                }
            }
            return true;
        }

        private function fileThumbnailModuleCoordsWrite($prefix, $x1=null, $y1=null, $w=null, $h=null, $quality=null, $sharpening=null, $colorize=null) {
            if(0==$w || 0==$h) {
                return false;
            }
            // new coords
            $permanent = array($prefix=>array($x1,$y1,$w,$h,$quality,$sharpening,$colorize));
            // get old coords
            $a = $this->getIPTCraw();
            $a = isset($a['2#215']) && isset($a['2#215'][0]) ? unserialize($a['2#215'][0]) : array();
            if(!is_array($a)) {
                $a = array();
            }
            // write back merged data
            $data = array(serialize(array_merge($a, $permanent)));
            $this->setIPTCraw(array('2#215'=>$data));

            // now we write this to the original image
            $targetFilename = $this->isOriginal ? $this->filename : $this->originalImage->filename;
            if(!is_file($targetFilename) || !is_writeable($targetFilename)) {
                return false;
            }
            $content = iptcembed($this->iptcPrepareData(), $targetFilename);
            if(false!==$content) {
                $dest = $targetFilename.'.tmp';
                if(strlen($content) == @file_put_contents($dest, $content, LOCK_EX)) {
                    // on success we replace the file
                    @rename($targetFilename, $targetFilename.'bak');
                    if(!rename($dest, $targetFilename)) {
                        @rename($targetFilename.'bak', $targetFilename);
                        return false;
                    }
                    @unlink($targetFilename.'bak');
                }
                else {
                    // it was created a temp diskfile but not with all data in it
                    if(file_exists($dest)) {
                        @unlink($dest);
                    }
                    return false;
                }
            }
            return true;
        }



    // Helpers

        private function adjustOutputFormat() {
            if('page'!=$this->entryItem && 'file'!=$this->entryItem) {
                return;
            }
            $outputFormat = $this->getOutputFormat();
            $targetFilename = $this->getTargetFilename();
            $targetFilename = empty($targetFilename) ? $this->filename : $targetFilename;
            $p = pathinfo($targetFilename);
            if($p['extension']!=$outputFormat) {
                $a = array_keys($this->supportedImageTypes);
                if(in_array($outputFormat, $a)) {
                    // we adjust the filename according to the outputFormat what is already set
                    $targetFilename = $p['dirname'] .'/'. $p['filename'] .'.'. $outputFormat;
                }
                elseif(in_array(strtolower($p['extension']), $a)) {
                    // there is no valid outputFormat set, so use the fileextension from targetFilename
                    $outputFormat = strtolower($p['extension']);
                }
            }
            $this->outputFormat = $outputFormat;
            $this->targetFilename = $targetFilename;
        }

        /**
         * Generate histogram data.
         *
         * GD2 Imaging (part of Lotos Framework)
         *
         * Copyright (c) 2005-2011 Artur Graniszewski (aargoth@boo.pl)
         * All rights reserved.
         *
         * @category   Library
         * @package    Lotos
         * @subpackage Imaging
         * @copyright  Copyright (c) 2005-2011 Artur Graniszewski (aargoth@boo.pl)
         * @license    GNU LESSER GENERAL PUBLIC LICENSE Version 3, 29 June 2007
         * @version    1.7.1
         *
         *
         * @param int $channels Channels to draw, possibilities: Channel::RGB (default), Channel::RED, Channel::GREEN, Channel::BLUE
         * @param int $colorMode Color mode for saturation (use Color::HSV, Color::HSI or Color::HSL as the value), default is Color::HSL
         * @return int[] Histogram data
         */
        private function getHistogramData($im) {
            $data = array_fill(0, 256, 0);
            for($y = 0; $y < imagesy($im); ++$y) {
                for($x = 0; $x < imagesx($im); ++$x) {
                    $rgb = imagecolorat($im, $x, $y);
                    $r = ($rgb >> 16) & 0xff;
                    $g = ($rgb >> 8) & 0xff;
                    $b = $rgb & 0xff;
                    $num = ($r + $r + $r + $b + $g + $g + $g + $g) >> 3;
                    ++$data[$num];
                }
            }
            return $data;
        }

        public function sanitizeColor($value, $forGdColorallocate=false, $forAdjustments=false) {
            if( is_array($value) && (count($value)==4 || count($value)==3)) {
                $color = $value;
            }
            elseif(is_int($value)) {
                $color = array($value, $value, $value);
            }
            else {
                static $common_colors = array('antiquewhite'=>'#FAEBD7','aqua'=>'#00FFFF','aquamarine'=>'#7FFFD4','beige'=>'#F5F5DC','black'=>'#000000','blue'=>'#0000FF','brown'=>'#A52A2A','cadetblue'=>'#5F9EA0','chocolate'=>'#D2691E','cornflowerblue'=>'#6495ED','crimson'=>'#DC143C','darkblue'=>'#00008B','darkgoldenrod'=>'#B8860B','darkgreen'=>'#006400','darkmagenta'=>'#8B008B','darkorange'=>'#FF8C00','darkred'=>'#8B0000','darkseagreen'=>'#8FBC8F','darkslategray'=>'#2F4F4F','darkviolet'=>'#9400D3','deepskyblue'=>'#00BFFF','dodgerblue'=>'#1E90FF','firebrick'=>'#B22222','forestgreen'=>'#228B22','fuchsia'=>'#FF00FF','gainsboro'=>'#DCDCDC','gold'=>'#FFD700','gray'=>'#808080','green'=>'#008000','greenyellow'=>'#ADFF2F','hotpink'=>'#FF69B4','indigo'=>'#4B0082','khaki'=>'#F0E68C','lavenderblush'=>'#FFF0F5','lemonchiffon'=>'#FFFACD','lightcoral'=>'#F08080','lightgoldenrodyellow'=>'#FAFAD2','lightgreen'=>'#90EE90','lightsalmon'=>'#FFA07A','lightskyblue'=>'#87CEFA','lightslategray'=>'#778899','lightyellow'=>'#FFFFE0','lime'=>'#00FF00','limegreen'=>'#32CD32','magenta'=>'#FF00FF','maroon'=>'#800000','mediumaquamarine'=>'#66CDAA','mediumorchid'=>'#BA55D3','mediumseagreen'=>'#3CB371','mediumspringgreen'=>'#00FA9A','mediumvioletred'=>'#C71585','mintcream'=>'#F5FFFA','moccasin'=>'#FFE4B5','navy'=>'#000080','olive'=>'#808000','orange'=>'#FFA500','orchid'=>'#DA70D6','palegreen'=>'#98FB98','palevioletred'=>'#D87093','peachpuff'=>'#FFDAB9','pink'=>'#FFC0CB','powderblue'=>'#B0E0E6','purple'=>'#800080','red'=>'#FF0000','royalblue'=>'#4169E1','salmon'=>'#FA8072','seagreen'=>'#2E8B57','sienna'=>'#A0522D','silver'=>'#C0C0C0','skyblue'=>'#87CEEB','slategray'=>'#708090','springgreen'=>'#00FF7F','tan'=>'#D2B48C','teal'=>'#008080','thistle'=>'#D8BFD8','turquoise'=>'#40E0D0','violetred'=>'#D02090','white'=>'#FFFFFF','yellow'=>'#FFFF00');
                if(isset($common_colors[strtolower($value)])) {
                    $value = $common_colors[strtolower($value)];
                }
                if($value{0} == '#') { //case of #nnnnnn or #nnn
                    $c = strtoupper($value);
                    if(strlen($c) == 4) { // Turn #RGB into #RRGGBB
                        $c = "#" . $c{1} . $c{1} . $c{2} . $c{2} . $c{3} . $c{3};
                    }
                    $color = array();
                    $color[0]=hexdec(substr($c, 1, 2));
                    $color[1]=hexdec(substr($c, 3, 2));
                    $color[2]=hexdec(substr($c, 5, 2));
                }
                else { //case of RGB(r,g,b) or rgba(r,g,b,a)
                    $value = str_replace(array('rgb', 'RGB', 'rgba', 'RGBA', '(', ')'), '', $value);
                    $c     = explode(',', $value);
                    $color = array();
                    $color[0] = $c[0];
                    $color[1] = $c[1];
                    $color[2] = $c[2];
                    if(isset($c[3])) $color[3] = trim($c[3]);
                }
            }
            $min = $forAdjustments ? -255 : 0;
            $max = 255;
            $default = $forAdjustments ? 0 : 127;
            foreach(array(0,1,2) as $c) {
                $i = intval(trim($color[$c]));
                $color[$c] = $i>=$min && $i<=$max ? $i : $default;
            }
            if(isset($color[3])) {  // rgba, value for the alpha channel
                // we have a float like with css rgba, float 0 - 1 where 0 is transparent and 1 is opaque
                $color[3] = $color[3]>=0 && $color[3]<=1 ? $color[3] : 0.5;
                if($forGdColorallocate) {
                    // convert css rgba alpha setting float 0-1 (transparent-opaque) scale to GDs ImagecolorAllocateAlpha 0-127 (opaque-transparent) scale
                    $color[3] = intval((($color[3] * 127) - 127) * -1);
                }
            }
            return $color;
        }

        /**
        * helper, reads a 4-byte integer from file, (also from FPDF-Project)
        */
        private function freadint(&$f) {
            $i=ord(@fread($f,1))<<24;
            $i+=ord(@fread($f,1))<<16;
            $i+=ord(@fread($f,1))<<8;
            $i+=ord(@fread($f,1));
            return $i;
        }

        /**
        * check file exists and read / write access
        *
        * @param string $filename
        * @param boolean $readonly
        * @return boolean
        */
        private function checkDiskfile($filename, $readonly=false) {
            $filename = realpath($filename);
            if($filename===false || ! (file_exists($filename) && is_file($filename)) || ! is_readable($filename) || (! $readonly && ! is_writable($filename))) {
                return false;
            }
            return true;
        }


        protected function iptcPrepareData() {
            $iptcNew = '';
            foreach(array_keys($this->iptcRaw) as $s) {
                $tag = substr($s,2);
                if(substr($s,0,1)=='2' && in_array($tag, $this->validIptcTags) && is_array($this->iptcRaw[$s])) {
                    foreach($this->iptcRaw[$s] as $row) {
                        $iptcNew .= $this->iptcMakeTag(2, $tag, $row);
                    }
                }
            }
            return $iptcNew;
        }

        protected function iptcMakeTag($rec,$dat,$val) {
            $len = strlen($val);
            if($len < 0x8000) {
                return chr(0x1c).chr($rec).chr($dat).
                chr($len >> 8).
                chr($len & 0xff).
                $val;
            } else {
                return chr(0x1c).chr($rec).chr($dat).
                chr(0x80).chr(0x04).
                chr(($len >> 24) & 0xff).
                chr(($len >> 16) & 0xff).
                chr(($len >> 8 ) & 0xff).
                chr(($len ) & 0xff).
                $val;
            }
        }

        public function getIPTCraw() {
            if(isset($this->iptcRaw) && is_array($this->iptcRaw)) {
                return $this->iptcRaw;
            }
            return array();
        }

        public function setIPTCraw($data) {
            if(!is_array($data)) {
                return false;
            }
            $this->iptcRaw = array_merge($this->getIPTCraw(), $data);
            return is_array($this->iptcRaw);
        }


        private function getDefaultOption($key) {
            $a = array_merge($this->defaultOptions, $this->configOptions1, $this->configOptions2);
            if(!isset($a[$key])) {
                return null;
            }
            return $a[$key];
        }

        /**
        * makes protected and private class-properties accessible in ReadOnly mode
        *
        * example:   $x = $class->propertyname;
        *
        * @param mixed $property_name
        */
        public function __get($propertyName) {
            if(in_array($propertyName, $this->propertyNames) && ! in_array($propertyName, array('imDibDst'))) {
                return $this->$propertyName;
            }
            return null;
        }

}



if(!class_exists('hn_SizeCalc')) {
    /**
    * @shortdesc This class holds some methods to calculate Picture dimensions
    * @public
    * @author Horst Nogajski
    * @version 0.3
    * @date 2004-Jun-21
    **/
    class hn_SizeCalc {

        /** @private **/
        var $a = 0;
        /** @private **/
        var $b = 0;
        /** @private **/
        var $landscape = NULL;

        /** Mit der Methode down werden Werte (falls sie groesser sind als die MaxWerte) verkleinert.
          * Werte die kleiner sind als die MaxWerte bleiben unveraendert.
          *
          * @shortdesc Use this for downsizing only. Means: if the source is smaller then the max-sizes, it will not changed.
          * @public
          **/
        function down(&$a,&$b,$a_max,$b_max) {
            $this->a = 0;
            $this->b = 0;
            $this->landscape = $a >= $b ? TRUE : FALSE;
            $check = 1;
            if($a > $a_max) $check += 3;
            if($b > $b_max) $check += 2;

            switch ($check) {
                case 1:
                    // Bild ist kleiner als max Groesse fuer a und b
                    $this->b = ceil($b);
                    $this->a = ceil($a);
                    break;
                case 3:
                    // Seite b ist groesser als max Groesse,
                    // Bild wird unter beruecksichtigung des Seitenverhaeltnisses
                    // auf Groesse fuer b gerechnet
                    $this->b = ceil($b_max);
                    $this->a = ceil($a / ($b / $this->b));
                    break;
                case 4:
                    // Seite a ist groesser als max Groesse,
                    // Bild wird unter beruecksichtigung des Seitenverhaeltnisses
                    // auf Groesse fuer a gerechnet
                    $this->a = ceil($a_max);
                    $this->b = ceil($b / ($a / $this->a));
                    break;
                case 6:
                    // BEIDE Seiten sind groesser als max Groesse,
                    // Bild wird unter beruecksichtigung des Seitenverhaeltnisses
                    // zuerst auf Groesse fuer a gerechnet, und wenns dann noch
                    // nicht passt, nochmal fuer b. Danach passt's! ;)
                    $this->a = ceil($a_max);
                    $this->b = ceil($b / ($a / $this->a));
                    if($this->b > $b_max) {
                        $this->b = ceil($b_max);
                        $this->a = ceil($a / ($b / $this->b));
                    }
                    break;
            }

            // RUECKGABE DER WERTE ERFOLGT PER REFERENCE
            $a = $this->a;
            $b = $this->b;
        }


        /** Mit der Methode up werden Werte (falls sie groesser sind als die MaxWerte) verkleinert
          * und falls sie kleiner sind als die MaxWerte werden sie vergroessert!
          *
          * @shortdesc Use this for up- and downsizing. Means: if the source is graeter then the max-sizes,
          * they become downsized, and if they are smaller then the max-sizes, they become upsized!
          * @public
          **/
        function up(&$a,&$b,$a_max,$b_max) {
            // falls das Bild zu gross ist wird es jetzt kleiner gerechnet, so das es max_a und max_b nicht ueberschreitet
            $this->down($a,$b,$a_max,$b_max);

            // reset
            $this->a = 0; // width
            $this->b = 0; // height

            //$this->landscape = $a >= $b ? TRUE : FALSE;

            // wenn jetzt a und b kleiner sind dann muss es vergroessert werden
            if($a < $a_max && $b < $b_max) {
                // ermitteln der prozentualen differenz vom Sollwert
                $pa = $this->_diffProzent($a,$a_max);
                $pb = $this->_diffProzent($b,$b_max);
                if($pa >= $pb) {
                    // b auf b_max setzen
                    $this->a = ceil($a * ($b_max / $b));
                    $this->b = $b_max;
                }
                else {
                    // a auf a_max setzen
                    $this->b = ceil($b * ($a_max / $a));
                    $this->a = $a_max;
                }
                // RUECKGABE DER WERTE ERFOLGT PER REFERENCE
                $a = $this->a;
                $b = $this->b;
                $this->down($a,$b,$a_max,$b_max);
            }
        }



        /** @public
          * conversion pixel -> millimeter in 72 dpi
          **/
        function px2mm($px) {
            return $px*25.4/72;
        }


        /** @private **/
        function _diffProzent($ist,$soll) {
            return (int)(($ist * 100) / $soll);
        }

    }
} // END CLASS hn_SizeCalc


