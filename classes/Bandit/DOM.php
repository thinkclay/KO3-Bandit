<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Bandit Web Helpers
 *
 * Creates a set of standard methods to process web endpoints and data
 *
 * @package     Bandit
 * @author      Clay McIlrath
 */
class Bandit_DOM
{
    public static function inner_html(DOMElement $node)
    {
        $innerHTML= '';
        $children = $node->childNodes;

        foreach ( $children as $child )
        {
            $innerHTML .= $child->ownerDocument->saveXML( $child );
        }

        return $innerHTML;
    }
}