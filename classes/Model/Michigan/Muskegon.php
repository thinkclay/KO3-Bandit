<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Muskegon Scrape
 *
 * This scrape is using JS and a popup to try and validate that you're a human being. To get around this,
 * we are going to hit the ajax urls directly
 *
 *  https://mcd911.net/p2c/jqHandler.ashx?op=s
 *
 * @package   Bandit
 * @author    Clay McIlrath
 * @see       http://www.mcd911.net/p2c/jailinmates.aspx
 */
class Model_Michigan_Muskegon extends Model_Bandit
{
    protected $name     = 'muskegon';   // name of scrape goes here
    protected $county   = 'muskegon';    // if it is a single county, put it here, otherwise remove this property
    protected $state    = 'michigan';  //  state goes here
    private $cookie     = '/tmp/muskegon_cookie.txt';

    protected $urls = [
        'main'      => 'https://mcd911.net/p2c/jailinmates.aspx',
        'referrer'  => 'https://mcd911.net/p2c/jailinmates.aspx',
        'list'      => 'https://mcd911.net/p2c/jqHandler.ashx?op=s',
        'detail'    => 'https://mcd911.net/p2c/jailinmates.aspx',
        'mug'       => 'https://mcd911.net/p2c/Mug.aspx'
    ];

    protected $errors = [];
    protected $offender_data = NULL;


    /**
     * Construct
     *
     * For now sets a timelimit, deletes cookie if they exist and creates mscrape model in DB.
     */
    public function __construct()
    {
        if ( file_exists($this->cookie) )
            unlink($this->cookie);   // Unlink only works for files, use rmdir for Directories.

        // Creates a mscrape model in Mongo DB.
        $this->scrape_model($this->name, $this->state, $this->county);
    }

    /**
     * Scrape
     *
     * main scrape function makes the curl calls and sends details to the extraction function
     *
     * @return true - on completed scrape
     * @return false - on failed scrape
     */
    public function scrape()
    {           
        $post = $this->get_post_data();

        $list = $this->load_url([
            'target'    => $this->urls['list'],
            'referrer'  => $this->urls['main'],
            'method'    => 'POST',
            'cookie'    => $this->cookie,
            'data'      => [
                't'         => 'ii',
                '_search'   => 'false',
                'nd'        => '1369948241492',
                'rows'      => '10000',
                'page'      => '1',
                'sidx'      => 'disp_name',
                'sord'      => 'asc'
            ]
        ]);

        $rows = json_decode($list['result'])->rows;
        $list_count = (int) json_decode($list['result'])->records;

        for ( $i = 0; $i < $list_count; $i++ )
        {
            $row = $rows[$i];

            $offender = Brass::factory('Brass_Offender', [
                'booking_id' => $this->name.'_'.$this->state.'_'.$row->book_id
            ])->load();

            $this->offender_data = [
                'booking_id'    => $this->name.'_'.$this->state.'_'.$row->book_id,
                'updated'       => time(),
                'firstname'     => $row->firstname,
                'lastname'      => $row->lastname,
                'booking_date'  => strtotime($row->date_arr),           // 5/17/2013 12:00:00 AM'
                'charges'       => explode(' / ', $row->disp_charge),   // 'OBSTRUCTING JUSTICE / FAILURE TO APPEAR'
                'age'           => (int) $row->age,
                'gender'        => strtoupper($row->sex),
                'race'          => strtoupper($row->race),
                'dob'           => strtotime($row->dob),
                'state'         => $this->state,
                'county'        => $this->county,
                'scrape'        => $this->name
            ];

            try
            {
                if ( ! $offender->loaded() )
                {
                    $offender->values($this->offender_data);
    
                    if ( $offender->check() )
                    {
                        $offender->create();
                    }
                }
            }
            catch ( Brass_Validation_Exception $e )
            {
                foreach ( $e->array->errors('brass') as $k => $v )
                {
                    $this->errors[] = [
                        'key' => $k,
                        'issue' => $v,
                        'offender' => $row->disp_name.': '.$this->name.'_'.$this->state.'_'.$row->book_id
                    ];
                }
            }

            // Set this to the iterator just like the site does to retrieve the detail view
            $post['ctl00$MasterPage$mainContent$CenterColumnContent$hfRecordIndex'] = $i;

            $this->get_mug($post);
            // sleep(rand(5,100));
        }

        if ( isset($errors) )
        {
            $data['scrape'] = ucfirst($this->county).' county, '.ucfirst($this->state);
            $data['errors'] = new ArrayIterator( $errors );
            Model_Annex_Email::factory()->send('mail.exception.generic', 'system', $data);
        }
    }

    /**
     * Get Post Data
     *
     * Returns post data containing validations, sessions, and other bits that are needed to make
     * other pages load properly
     *
     * @return  array   $post   Post Data
     */
    public function get_post_data()
    {
        $home = $this->load_url([
            'target'    => $this->urls['main'],
            'referrer'  => $this->urls['main'],
            'method'    => 'GET',
            'cookie'    => $this->cookie
        ]);

        if ( $home['error'] )
            throw new Peruse_Exception('could not load the home page', 'severe');

        $home = $this->clean_html($home['result']);
        $dom = new DOMDocument();

        if ( ! $dom->loadHTML($home) )
            throw new Peruse_Exception('could not parse html as DOMDocument', 'severe');

        $forms = $dom->getElementsByTagName('form');

        foreach ( $forms as $form )
        {
            $inputs = $form->getElementsByTagName('input');

            foreach ( $inputs as $input )
            {
                if ( $input->getAttribute('name') == 'ctl00$MasterPage$mainContent$CenterColumnContent$hfRecordIndex')
                    $post[$input->getAttribute('name')] = 0;
                else
                    $post[$input->getAttribute('name')] = $input->getAttribute('value');
            }
        }

        return $post;
    }

    public function get_mug($post)
    {
        // We need to get the url we've been redirected too, plus update the cookie which it uses to validate
        // and spit out the right image. Not much more we can do to simplify here
        $referrer = $this->load_url([
            'target'    => $this->urls['main'],
            'referrer'  => $this->urls['main'],
            'method'    => 'POST',
            'cookie'    => $this->cookie,
            'data'      => $post
        ])['status']['url'];

        $raw = $this->load_url([
            'target'    => $this->urls['mug'],
            'referrer'  => $referrer,
            'cookie'    => $this->cookie
        ])['result'];

        $mug = $this->mug_info($this->offender_data);
        $mug_raw = $this->set_mugpath($mug['raw'].$mug['name']);
        $mug_prod = $this->set_mugpath($mug['prod'].$mug['name']);

        try
        {
            // Write the raw file and then copy to prod directory
            if ( ! file_exists($mug_prod) )
            {
                $f = fopen($mug_raw, 'wb');
                fwrite($f, $raw);
                fclose($f);
                copy($mug_raw, $mug_prod);
            }

            $this->mug_stamp(
                $mug_prod,
                $this->offender_data['firstname'].' '.$this->offender_data['lastname'],
                $this->offender_data['charges'][0],
                @$this->offender_data['charges'][1]
            );
        }
        catch ( Exception $e )
        {
            var_dump($e);
        }
    }
}