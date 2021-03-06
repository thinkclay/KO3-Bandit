<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Bandit Exception Handler
 *
 * @package  Bandit
 * @author   Clay McIlrath
 */
class Bandit_Exception extends Exception
{
    /**
     * Creates a new translated exception.
     *
     *     throw new Kohana_Exception('Something went terrible wrong, :user',
     *         array(':user' => $user));
     *
     * severe will send an email right away when it breaks
     * moderate will add the error to a report document in mongo that we can retrieve
     * low will add the error to a report marked as low
     *
     * @param   string     error message
     * @param   array      translation variables
     * @param   integer    the exception code
     * @return  void
     */
    public function __construct($message, $code = NULL)
    {
        // Pass the message to the parent
        switch ( $code )
        {
            case 'severe' :
                $data['message'] = $message;
                Model_Annex_Email::factory()->send('mail.exception.severe', 'system', $data);
                parent::__construct($message, 300);
                break;

            case 'moderate' :
                parent::__construct($message, 200);
                break;

            case 'low' :
                parent::__construct($message, 100);
                break;

            default :
                parent::__construct($message, $code);
                break;
        }

    }

}