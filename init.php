<?php defined('SYSPATH') or die('No direct access allowed.');


Route::set('bandit scrape', 'bandit(/<state>(/<county>))')
    ->defaults(array(
        'controller' => 'bandit',
        'action'     => 'scrape',
    ));