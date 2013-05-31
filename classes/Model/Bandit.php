<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Scrape
 *
 * @author Jiran Dowlati
 *
 **/
class Model_Bandit extends Model
{
    /**
     * Cleaner print out of arrays.
     *
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
        $config = array(
            'indent' => true,
            'output-xhtml' => true,
            'wrap' => 200
        );

        // Tidy
        $tidy = new tidy;
        $tidy->parseString($html, $config, 'utf8');
        $tidy->cleanRepair();

        return $tidy;
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
        if( !empty($matches[0]) || !empty($matches[1]) )
        {
            return $matches;
        }
        else
        {
            echo "Sorry it looks like your regex pattern might be off"; exit;
        }

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

    public function set_mugpath($imagepath)
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
    * mugStamp - Takes an image and adds space at the bottom for name and charges
    *
    * @todo
    * @return
    * @author Winter King
    */
    public function mug_stamp($imgpath, $fullname, $charge1, $charge2 = null)
    {
        $max_width = 380;
        $font = DOCROOT.'includes/arial.ttf';
        $font_18_dims = imagettfbbox( 18 , 0 , $font , $charge1);
        $font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
        $font_12_dims = imagettfbbox( 12 , 0 , $font , $charge1);
        $font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
        $cropped = FALSE;

        if($font_12_charge_width > $max_width)
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

            if($font_12_charge_width > $max_width)
            {
                unset($charge2);
            }
        }
        if (isset($charge1))
        {
            $font_18_dims = imagettfbbox( 18 , 0 , $font , $charge1);
            $font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
            $font_12_dims = imagettfbbox( 12 , 0 , $font , $charge1);
            $font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];

            if ( $font_12_charge_width > ($max_width * 2) )
                return FALSE;
        }

        $charge1 = trim($charge1);

        $image = Image::factory($imgpath);
        $image->resize(400, 480, Image::NONE)->save();

        // check for valid image
        if ( ! $check = getimagesize($imgpath) )
            return false;

        $orig = imagecreatefrompng($imgpath);

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
        $imgName = $fullname . ' ' . date('(m-d-Y)');

        // Save the file
        if ( imagepng($canvas, $imgpath) )
        {
            echo $imgpath."<br>\r\n";
            chmod($imgpath, 0777);
            return true;
        }
        else
        {
            return false;
        }
    }

}