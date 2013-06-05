<?php defined('SYSPATH') or die('No direct access allowed.');


Route::set('bandit scrape', 'bandit(/<name>(/<sub>))')
    ->defaults(array(
        'controller' => 'bandit',
        'action'     => 'scrape',
    ));