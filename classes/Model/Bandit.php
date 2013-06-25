<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Bandit Model
 *
 * provides core scraping functionality to other models
 *
 * @package   Bandit
 * @author    Clay McIlrath
 */
class Model_Bandit extends Model
{
    public function raise($message, $severity)
    {
        switch($severity)
        {
            case 'report' :
                echo $message."<br />\r\n";
                break;
            default :
                echo $message."<br />\r\n";
        }
    }
    
    /**
     * Get rid of this later
     *
     * usedin:    Multnomah
     */
    public function mugStamp($imgpath, $fullname, $charge1, $charge2 = null)
    {
        $max_width = 380;
        $font = DOCROOT.'public/includes/arial.ttf';
        $font_18_dims = imagettfbbox( 18 , 0 , $font , $charge1);
        $font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
        $font_12_dims = imagettfbbox( 12 , 0 , $font , $charge1);
        $font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
        $cropped = false;
        if($font_12_charge_width > $max_width)
        {
            unset($charge2);
            $cropped_charge = $this->charge_cropper($charge1, $max_width);
            if ($cropped_charge === false)
            {
                return false;
            }
            $cropped = true;
            $charge1 = $cropped_charge[0];
            $charge2 = @$cropped_charge[1];
        }
        if (isset($charge2))
        {
            $font = DOCROOT.'public/includes/arial.ttf';
            $font_18_dims = imagettfbbox( 18 , 0 , $font , $charge2);
            $font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
            $font_12_dims = imagettfbbox( 12 , 0 , $font , $charge2);
            $font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
            if($font_12_charge_width > $max_width)
            {
                unset($charge2);
            }
        }
        if (isset($charge1))
        {
            $font = DOCROOT.'public/includes/arial.ttf';
            $font_18_dims = imagettfbbox( 18 , 0 , $font , $charge1);
            $font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
            $font_12_dims = imagettfbbox( 12 , 0 , $font , $charge1);
            $font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
            if($font_12_charge_width > ($max_width * 2) )
            {
                return false;
            }
        }
        # todo: check to make sure the $imgpath is an image, if not then return string 'not an image'
        $charge1 = trim($charge1);
        //header('Content-Type: image/png');
        //$imgpath = DOCROOT.'public/images/scrape/ohio/summit/test.png';
        # resize image to 400x480 and save it
        $image = Image::factory($imgpath);
        $image->resize(400, 480, Image::NONE)->save();
        # open original image with GD
        // check for valid image
        $check = getimagesize($imgpath);
        if ($check === false)
        {
            return false;
        }
        $orig = imagecreatefrompng($imgpath);
        # create a blank 400x600 canvas
        $canvas = imagecreatetruecolor(400, 600);
        # allocate white
        $white = imagecolorallocate($canvas, 255, 255, 255);
        # draw a filled rectangle on it
        imagefilledrectangle($canvas, 0, 0, 400, 600, $white);
        # copy original onto white painted canvas
        imagecopy($canvas, $orig, 0, 0, 0, 0, 400, 480);

        # start text stamp
        # create a new text canvas box @ 400x120
        $txtCanvas = imagecreatetruecolor(400, 120);
        # allocate white
        $white = imagecolorallocate($txtCanvas, 255, 255, 255);
        # draw a filled rectangle on it
        imagefilledrectangle($txtCanvas, 0, 0, 400, 120, $white);
        # set font file
        $font = DOCROOT.'public/includes/arial.ttf';

        # fullname
        # find dimentions of the text box for fullname

        $dims = imagettfbbox(18 , 0 , $font , $fullname );
        # set width
        $width = $dims[2] - $dims[0];
        # check to see if the name fits
        if ($width < 390)
        {
            $fontsize = 18;
            # find center
            $center = ceil((400 - $width)/2);
            # write text
            imagettftext($txtCanvas, $fontsize, 0, $center, 35, 5, $font, $fullname);
        }
        # if it doesn't fit cut it down to size 12
        else
        {
            $fontsize = 12;
            $dims = imagettfbbox(12 , 0 , $font , $fullname );
            # set width
            $width = $dims[2] - $dims[0];
            # find center
            $center = ceil((400 - $width)/2);
            # write text
            imagettftext($txtCanvas, $fontsize, 0, $center, 35, 5, $font, $fullname);
        }
        //@todo: make a check for text that is too long for the box and cut out middle name if so

        # charge1
        # find dimentions of the text box for charge1
        $dims = imagettfbbox(18 , 0 , $font , $charge1 );
        # set width
        $width = $dims[2] - $dims[0];
        # check to see if charge1 description fits
        if ($width < 390)
        {
            $cfont = 18;
            # find center
            $center = ceil((400 - $width)/2);
            # write text
            imagettftext($txtCanvas, $cfont, 0, $center, 65, 5, $font, $charge1);
        }
        # if it doesn't fit cut it down to size 12
        else
        {
            $cfont = 12;
            $dims = imagettfbbox(12 , 0 , $font , $charge1 );
            # set width
            $width = $dims[2] - $dims[0];
            # find center
            $center = ceil((400 - $width)/2);
            # write text
            imagettftext($txtCanvas, $cfont, 0, $center, 65, 5, $font, $charge1);
        }

        # check for a 2nd charge
        if (isset($charge2))
        {
            if ($cropped === true)
            {
                $dims = imagettfbbox($cfont , 0 , $font , $charge2 );
                # set width
                $width = $dims[2] - $dims[0];
                # find center
                $center = ceil((400 - $width)/2);
                # write text
                imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
            }
            else
            {
                # charge2
                # find dimentions of the text box for charge2
                $dims = imagettfbbox(18 , 0 , $font , $charge2 );
                # set width
                $width = $dims[2] - $dims[0];
                # check to see if charge1 description fits
                if ($width < 390 && $cfont == 18)
                {
                    # find center
                    $center = ceil((400 - $width)/2);
                    # write text
                    imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
                }
                # if it doesn't fit cut it down to size 12
                else
                {
                    $cfont = 12;
                    $dims = imagettfbbox(12 , 0 , $font , $charge2 );
                    # set width
                    $width = $dims[2] - $dims[0];
                    # find center
                    $center = ceil((400 - $width)/2);
                    # write text
                    imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
                }
            }
        }
        #doesn't exist for some reason
        //imageantialias($txtCanvas);
        # copy text canvas onto the image
        imagecopy($canvas, $txtCanvas, 0, 480, 0, 0, 400, 120);
        $imgName = $fullname . ' ' . date('(m-d-Y)');
        $mugStamp = $imgpath;
        # save file
        $check = imagepng($canvas, $mugStamp);
        chmod($mugStamp, 0777); //not working for some reason
        if ($check) {return true;} else {return false;}
    }

    /**
     * Cleaner print out of arrays.
     */
    public function pretty_print($val)
    {
        echo '<pre>';
        print_r($val);
        echo '</pre>';
    }

    public function load_url(array $options)
    {
        return Bandit_Web::factory()->http($options);
    }

    /**
     * Mscrape Model  - sets up a model for the DB
     *
     * @params
     *  $scrape      Name of the scrape
     *  $state       What state we are scraping
     *
     */
    public function scrape_model($name, $state, $county = NULL)
    {
        $scrape = Brass::factory('Brass_Scrape', ['name' => $name, 'state' => $state])->load();

        if ( ! $scrape->loaded() )
        {
            $scrape = Brass::factory('Brass_Scrape', [
                'name'   => $name,
                'state'  => $state,
                'county' => $county
            ])->create();
        }
    }

    // attempt to load the offender by booking_id
    public function load_offender($id)
    {
        $offender = Brass::factory('offender', array(
            'booking_id' => $id
        ))->load();

        // if they are not loaded then continue with extraction, otherwise skip this offender
        if ($offender->loaded()) {
            echo "Sorry this offender already exists"; exit;
        }
    }

    /**
     * Clean HTML  - Uses Tidy to clean up the html from site we are scraping. A lot of sites have a lot of invalid html.
     *  Makes for an easier scrape when tidying it all up.
     *
     * @params
     *  $html      The site we are cleaning up.
     *
     * @returns
     *  $tidy      The cleaned up html
     *
     */
    public function clean_html($html)
    {
       // Specify configuration
        $config = [
            'clean' => TRUE,
            'indent' => TRUE,
            'drop-font-tags' => TRUE,
            'output-xhtml' => TRUE,
            'wrap' => 200
        ];

        // Tidy
        $tidy = new tidy;
        $tidy->parseString($html, $config, 'utf8');
        $tidy->cleanRepair();

        return $tidy;
    }

    function array_filter_recursive($input)
    {
        foreach ($input as &$value)
        {
            if ( is_array($value) )
            {
                $value = $this->array_filter_recursive($value);
            }
        }

        return array_filter($input);
    }


    // true to remove extra white space
    public function clean_string_utf8($string_to_clean, $bool = false)
    {
        if (!is_string($string_to_clean))
            return false;
        $clean_string = strtoupper(trim(preg_replace('/[\x7f-\xff]/', '', $string_to_clean)));
        $clean_string = str_replace('"', '', $clean_string);
        if ($bool == true)
            $clean_string = preg_replace('/\s\s+/', ' ', $clean_string); // replace all extra spaces
        return htmlspecialchars_decode(trim($clean_string), ENT_QUOTES);
    }

    public function parse($pattern, $site)
    {
        preg_match_all($pattern, $site, $matches);
        
        if ( count($matches) )
            return $matches;
        else
            return false;
    }

    /**
     * Extract Raw Data
     *
     * This function takes a source directory and scans it for zip files and extracts the contents
     * to the destination directory
     *
     * @param string $source_dir  the full path to the source directory that can be scanned for zip files
     * @param string $destination_dir  the full path to the destination directory where the contents should be extracted
     *
     * @return array a count of success or failed extractions
     */
    public static function extract_raw_data($source_dir, $destination_dir)
    {
        if ( ! file_exists($source_dir) )
            throw new Peruse_Exception(
                $this->errors."the source directory {$source_dir} does not exist",
                "severe"
            );

        if ( ! file_exists($destination_dir) )
            throw new Peruse_Exception(
                $this->errors."the source directory {$destination_dir} does not exist",
                "severe"
            );

        $files = glob($source_dir.'/*.zip');
        $zip = new ZipArchive;
        $success = $failed = 0;

        foreach ( $files as $file )
        {
            if ( $zip->open($file) )
                $success++;
            else
                $failed++;

            $zip->extractTo($destination_dir);
        }

        return array('success' => $success, 'failed' => $failed);
    }

    /**
     * Mug Info
     *
     * Get raw paths and image name for the offender
     *
     * @param  array  $offender information about the offender
     * @return array  offender mug path information
     */
    public function mug_info( array $offender)
    {
        $prod_path = '/mugs/'.$offender['state'].'/'.$offender['county'].'/'.
            date('Y', $offender['booking_date']).'/week_'.
            $this->find_week($offender['booking_date']).'/';

        $raw_path = '/original/'.$offender['state'].'/'.$offender['county'].'/'.
            date('Y', $offender['booking_date']).'/week_'.
            $this->find_week($offender['booking_date']).'/';

        $name = date('(m-d-Y)', $offender['booking_date']).'_'.$offender['lastname'].'_'.
            $offender['firstname'].'_'.$offender['booking_id'].'.png';

        return [
            'raw'  => $raw_path,
            'prod' => $prod_path,
            'name' => $name
        ];
    }

    public function create_path($imagepath)
    {
        if ( ! $check = preg_match('/\/(mugs|raw|original)\/.*\//Uis', $imagepath, $match) )
            return FALSE;

        if ( ! is_dir($match[0]) )
        {
            $oldumask = umask(0);
            mkdir($match[0], 0777);
            umask($oldumask);
        }

        preg_match('/\/(mugs|raw|original)\/.*\/.*\//Uis', $imagepath, $match);

        if ( ! is_dir($match[0]) )
        {
            $oldumask = umask(0);
            mkdir($match[0], 0777);
            umask($oldumask);
        }

        $yearpath = preg_replace('/\/week.*/', '', $imagepath);

        // check if year path exists
        if ( ! is_dir($yearpath) )
        {
            $oldumask = umask(0);
            mkdir($yearpath, 0777);
            umask($oldumask);
        }

        // check if full image path exists now
        preg_match('/\/(mugs|raw|original)\/.*\//', $imagepath, $match);

        if ( ! is_dir($match[0]) )
        {
            $oldumask = umask(0);
            mkdir($match[0], 0777);
            umask($oldumask);
        }

        return $imagepath;
    }

    public function find_week($timestamp)
    {
        $week = date('W', $timestamp) + 1;

        return $week;
    }

    /**
     * convertImage - converts any image to a PNG
     *
     * Lets see if we can find another built in PHP function to replace this
     */
    public function convert_image($image)
    {
        // check for valid image
        $check = @getimagesize($image);
        if ($check === false)
        {
            return false;
        }
        $info = @GetImageSize($image);
        $mime = $info['mime'];
        // What sort of image?
        $type = substr(strrchr($mime, '/'), 1);
        switch ($type)
        {
            case 'jpeg':
                $image_s = imagecreatefromjpeg($image);
                break;
            case 'png':
                $image_s = imagecreatefrompng($image);
                break;
            case 'bmp':
                $image_s = imagecreatefromwbmp($image);
                break;
            case 'gif':
                $image_s = imagecreatefromgif($image);
                break;
            case 'xbm':
                $image_s = imagecreatefromxbm($image);
                break;
            default:
                $image_s = imagecreatefromjpeg($image);
        }
        # ok so now I have $image_s set as the sourceImage and open as
        # now change the image extension
        $ext = '.png';
        $replace = preg_replace('/\.[a-zA-Z]*/', $ext, $image);
        # save the image with the same name but new extension
        $pngimg = imagepng($image_s, $replace);
        # if successful delete orginal source image
        if ($pngimg)
        {
            chmod($replace, 0777);
            //chown($replace, 'mugs');
            @unlink($image);
            return $pngimg;
        }
        else
        {
            return false;
        }
    }

    function charge_cropper($charge, $max_width, $large_string_length = 30)
    {
        // if the string contains no spaces then return false
        if ( ! preg_match('/\s/', $charge) )
            return FALSE;

        if ( strlen($charge) > $large_string_length )
            return FALSE;


        $font = DOCROOT.'includes/arial.ttf';

        $font_18_dims = imagettfbbox( 18 , 0 , $font , $charge);
        $font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
        $font_12_dims = imagettfbbox( 12 , 0 , $font , $charge);
        $font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];

        if ( ($font_18_charge_width / 2) < $max_width )
        {
            $fontsize = 18;
        }
        elseif(($font_12_charge_width / 2) < $max_width)
        {
            $fontsize = 12;
        }
        else
        {
            return false;
        }

        $charges = $words = [];

        $words = explode(' ', $charge);
        $word_count = count($words) - 1;
        $word_width = array();
        $total_width = array();
        $total_width[0] = 0;
        $total_width[1] = 0;
        $charges[0] = '';
        $charges[1] = '';
        $i = 0;

        foreach ( $words as $word )
        {
            $dims = imagettfbbox($fontsize , 0 , $font , $word);
            $word_width[$i] = $dims[2] - $dims[0];
            $i++;
        }

        $c1 = 0;

        for ( $i = 0; $i <= $word_count; $i++ )
        {
            if ( ($total_width[0] + $word_width[$i] <= $max_width) AND $c1 == 0 )
            {
                $total_width[0] = $total_width[0] + $word_width[$i];
                $charges[0] = $charges[0] . ' ' . $words[$i];
            }
            else
            {
                $c1 = 1;

                if ( $total_width[1] + $word_width[$i] <= $max_width )
                {
                    $total_width[1] = $total_width[1] + $word_width[$i];
                    $charges[1] = $charges[1] . ' ' . $words[$i];
                }
                else
                {
                    break;
                }
            }
        }
        return $charges;
    }


    /**
    * Mug Stamp
    *
    * Takes an image and adds space at the bottom for name and charges
    */
    public function mug_stamp($raw_path, $prod_path, $fullname, $charge1, $charge2 = null)
    {
        // Copy a fresh image from the raw path
        if ( ! file_exists($prod_path) )
            return 'mug already exists';
        else
            copy($raw_path, $prod_path);

        $max_width = 380;
        $font = DOCROOT.'includes/arial.ttf';
        $font_18_dims = imagettfbbox(18, 0, $font, $charge1);
        $font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
        $font_12_dims = imagettfbbox(12, 0, $font, $charge1);
        $font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
        $cropped = FALSE;

        if ( $font_12_charge_width > $max_width )
        {
            unset($charge2);
            $cropped_charge = $this->charge_cropper($charge1, $max_width);

            if ( $cropped_charge === FALSE )
                return FALSE;

            $cropped = TRUE;
            $charge1 = $cropped_charge[0];
            $charge2 = @$cropped_charge[1];
        }

        if ( isset($charge2) )
        {
            $font_18_dims = imagettfbbox( 18 , 0 , $font , $charge2);
            $font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
            $font_12_dims = imagettfbbox( 12 , 0 , $font , $charge2);
            $font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];

            if ( $font_12_charge_width > $max_width )
            {
                unset($charge2);
            }
        }

        if ( isset($charge1) )
        {
            $font_18_dims = imagettfbbox( 18 , 0 , $font , $charge1);
            $font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
            $font_12_dims = imagettfbbox( 12 , 0 , $font , $charge1);
            $font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];

            if ( $font_12_charge_width > ($max_width * 2) )
                return FALSE;
        }

        $charge1 = trim($charge1);

        $image = Image::factory($prod_path);
        $image->resize(400, 480, Image::NONE)->save();

        // check for valid image
        if ( ! $check = getimagesize($prod_path) )
            throw new Bandit_Exception('could not validate image size Bandit Model: 438', 'severe');

        $orig = imagecreatefrompng($prod_path);

        // create a blank 400x600 canvas, create a white box, and slap the image on it
        $canvas = imagecreatetruecolor(400, 600);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, 400, 600, $white);
        imagecopy($canvas, $orig, 0, 0, 0, 0, 400, 480);

        // start text stampcreate a new text canvas box @ 400x120 this one will overlay instead
        $txtCanvas = imagecreatetruecolor(400, 120);
        $white = imagecolorallocate($txtCanvas, 255, 255, 255);
        imagefilledrectangle($txtCanvas, 0, 0, 400, 120, $white);

        // put the name on the box by finding dimensions of the text box and making it fit
        $dims = imagettfbbox(18, 0, $font, $fullname);
        $width = $dims[2] - $dims[0];

        if ( $width < 390 )
        {
            $fontsize = 18;
            $center = ceil((400 - $width)/2);
            imagettftext($txtCanvas, $fontsize, 0, $center, 35, 5, $font, $fullname);
        }
        // if it doesn't fit cut it down to size 12
        else
        {
            $fontsize = 12;
            $dims = imagettfbbox(12 , 0 , $font , $fullname );
            $width = $dims[2] - $dims[0];
            $center = ceil((400 - $width)/2);
            imagettftext($txtCanvas, $fontsize, 0, $center, 35, 5, $font, $fullname);
        }

        // do the same as we did with the name but for charges
        $dims = imagettfbbox(18 , 0 , $font , $charge1 );
        $width = $dims[2] - $dims[0];

        if ( $width < 390 )
        {
            $cfont = 18;
            $center = ceil((400 - $width)/2);
            imagettftext($txtCanvas, $cfont, 0, $center, 65, 5, $font, $charge1);
        }
        else
        {
            $cfont = 12;
            $dims = imagettfbbox(12 , 0 , $font , $charge1 );
            $width = $dims[2] - $dims[0];
            $center = ceil((400 - $width)/2);
            imagettftext($txtCanvas, $cfont, 0, $center, 65, 5, $font, $charge1);
        }

        if ( isset($charge2) )
        {
            if ( $cropped === true )
            {
                $dims = imagettfbbox($cfont , 0 , $font , $charge2 );
                $width = $dims[2] - $dims[0];
                $center = ceil((400 - $width)/2);
                imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
            }
            else
            {
                $dims = imagettfbbox(18 , 0 , $font , $charge2 );
                $width = $dims[2] - $dims[0];

                if ( $width < 390 && $cfont == 18 )
                {
                    $center = ceil((400 - $width)/2);
                    imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
                }
                else
                {
                    $cfont = 12;
                    $dims = imagettfbbox(12 , 0 , $font , $charge2 );
                    $width = $dims[2] - $dims[0];
                    $center = ceil((400 - $width)/2);
                    imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
                }
            }
        }

        // Put the text on the image
        imagecopy($canvas, $txtCanvas, 0, 480, 0, 0, 400, 120);

        // Save the file
        if ( imagepng($canvas, $prod_path) )
        {
            echo $prod_path."<br>\r\n";
            return 'mug created';
        }
        else
        {
            return FALSE;
        }
    }

}