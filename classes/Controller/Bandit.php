<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Run Bandit Scrapes
 *
 * Creates a wrapper and route to test scrapes and run them with cron
 *
 * @todo        Validate user or CLI for security
 * @package     Bandit
 * @author      Clay McIlrath
 */
class Controller_Bandit extends Controller_Public
{
    /**
     * Scrape is what calls the specific model to scrape.
     */
    public function action_scrape()
    {
        $this->auto_render = FALSE;

        $name  = ucwords($this->request->param('name'));
        $sub = ucwords($this->request->param('sub'));

        $class = 'Model_'.$name.'_'.$sub;
        $scrape = new $class;

        $scrape->scrape();
    }
}
