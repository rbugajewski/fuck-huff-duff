<?php

namespace PicoFeed;

/**
 * Feed
 *
 * @author  Frederic Guillot
 * @package picofeed
 */
class Feed
{
    /**
     * Feed items
     *
     * @access public
     * @var array
     */
    public $items = array();

    /**
     * Feed id
     *
     * @access public
     * @var string
     */
    public $id = '';

    /**
     * Feed title
     *
     * @access public
     * @var string
     */
    public $title = '';

    /**
     * Item url
     *
     * @access public
     * @var string
     */
    public $url = '';

    /**
     * Item date
     *
     * @access public
     * @var integer
     */
    public $date = 0;

    /**
     * Item language
     *
     * @access public
     * @var string
     */
    public $language = '';

    /**
     * Return feed information
     *
     * @access public
     * $return string
     */
    public function __toString()
    {
        $output = '';

        foreach (array('id', 'title', 'url', 'date', 'language') as $property) {
            $output .= 'Feed::'.$property.' = '.$this->$property.PHP_EOL;
        }

        $output .= 'Feed::items = '.count($this->items).' items'.PHP_EOL;

        foreach ($this->items as $item) {
            $output .= '----'.PHP_EOL;
            $output .= $item;
        }

        return $output;
    }

    /**
     * Get title
     *
     * @access public
     * $return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Get url
     *
     * @access public
     * $return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Get date
     *
     * @access public
     * $return integer
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Get language
     *
     * @access public
     * $return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Get id
     *
     * @access public
     * $return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get feed items
     *
     * @access public
     * $return array
     */
    public function getItems()
    {
        return $this->items;
    }
}
