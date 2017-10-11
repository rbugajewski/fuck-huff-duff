<?php

namespace PicoFeed\Parsers;

use SimpleXMLElement;
use PicoFeed\Parser;
use PicoFeed\XmlParser;
use PicoFeed\Logging;
use PicoFeed\Filter;
use PicoFeed\Feed;
use PicoFeed\Item;

/**
 * Atom parser
 *
 * @author  Frederic Guillot
 * @package parser
 */
class Atom extends Parser
{
    /**
     * Get the path to the items XML tree
     *
     * @access public
     * @param  SimpleXMLElement   $xml   Feed xml
     * @return SimpleXMLElement
     */
    public function getItemsTree(SimpleXMLElement $xml)
    {
        return $xml->entry;
    }

    /**
     * Find the feed url
     *
     * @access public
     * @param  SimpleXMLElement   $xml     Feed xml
     * @param  \PicoFeed\Feed     $feed    Feed object
     */
    public function findFeedUrl(SimpleXMLElement $xml, Feed $feed)
    {
        $feed->url = $this->getLink($xml);
    }

    /**
     * Find the feed title
     *
     * @access public
     * @param  SimpleXMLElement   $xml     Feed xml
     * @param  \PicoFeed\Feed     $feed    Feed object
     */
    public function findFeedTitle(SimpleXMLElement $xml, Feed $feed)
    {
        $feed->title = $this->stripWhiteSpace((string) $xml->title) ?: $feed->url;
    }

    /**
     * Find the feed language
     *
     * @access public
     * @param  SimpleXMLElement   $xml     Feed xml
     * @param  \PicoFeed\Feed     $feed    Feed object
     */
    public function findFeedLanguage(SimpleXMLElement $xml, Feed $feed)
    {
        $feed->language = $this->getXmlLang($this->content);
    }

    /**
     * Find the feed id
     *
     * @access public
     * @param  SimpleXMLElement   $xml     Feed xml
     * @param  \PicoFeed\Feed     $feed    Feed object
     */
    public function findFeedId(SimpleXMLElement $xml, Feed $feed)
    {
        $feed->id = (string) $xml->id;
    }

    /**
     * Find the feed date
     *
     * @access public
     * @param  SimpleXMLElement   $xml     Feed xml
     * @param  \PicoFeed\Feed     $feed    Feed object
     */
    public function findFeedDate(SimpleXMLElement $xml, Feed $feed)
    {
        $feed->date = $this->parseDate((string) $xml->updated);
    }

    /**
     * Find the item date
     *
     * @access public
     * @param  SimpleXMLElement   $entry   Feed item
     * @param  Item           $item    Item object
     */
    public function findItemDate(SimpleXMLElement $entry, Item $item)
    {
        $item->date = $this->parseDate((string) $entry->updated);
    }

    /**
     * Find the item title
     *
     * @access public
     * @param  SimpleXMLElement   $entry   Feed item
     * @param  Item           $item    Item object
     */
    public function findItemTitle(SimpleXMLElement $entry, Item $item)
    {
        $item->title = $this->stripWhiteSpace((string) $entry->title);

        if (empty($item->title)) {
            $item->title = $item->url;
        }
    }

    /**
     * Find the item author
     *
     * @access public
     * @param  SimpleXMLElement   $xml     Feed
     * @param  SimpleXMLElement   $entry   Feed item
     * @param  \PicoFeed\Item     $item    Item object
     */
    public function findItemAuthor(SimpleXMLElement $xml, SimpleXMLElement $entry, Item $item)
    {
        if (isset($entry->author->name)) {
            $item->author = (string) $entry->author->name;
        }
        else {
            $item->author = (string) $xml->author->name;
        }
    }

    /**
     * Find the item content
     *
     * @access public
     * @param  SimpleXMLElement   $entry   Feed item
     * @param  \PicoFeed\Item     $item    Item object
     */
    public function findItemContent(SimpleXMLElement $entry, Item $item)
    {
        $item->content = $this->filterHtml($this->getContent($entry), $item->url);
    }

    /**
     * Find the item URL
     *
     * @access public
     * @param  SimpleXMLElement   $entry   Feed item
     * @param  \PicoFeed\Item     $item    Item object
     */
    public function findItemUrl(SimpleXMLElement $entry, Item $item)
    {
        $item->url = $this->getLink($entry);
    }

    /**
     * Genereate the item id
     *
     * @access public
     * @param  SimpleXMLElement   $entry   Feed item
     * @param  \PicoFeed\Item     $item    Item object
     * @param  \PicoFeed\Feed     $feed    Feed object
     */
    public function findItemId(SimpleXMLElement $entry, Item $item, Feed $feed)
    {
        $id = (string) $entry->id;

        if ($id !== $item->url) {
            $item_permalink = $id;
        }
        else {
            $item_permalink = $item->url;
        }

        if ($this->isExcludedFromId($feed->url)) {
            $feed_permalink = '';
        }
        else {
            $feed_permalink = $feed->url;
        }

        $item->id = $this->generateId($item_permalink,  $feed_permalink);
    }

    /**
     * Find the item enclosure
     *
     * @access public
     * @param  SimpleXMLElement   $entry   Feed item
     * @param  \PicoFeed\Item     $item    Item object
     * @param  \PicoFeed\Feed     $feed    Feed object
     */
    public function findItemEnclosure(SimpleXMLElement $entry, Item $item, Feed $feed)
    {
        foreach ($entry->link as $link) {
            if ((string) $link['rel'] === 'enclosure') {

                $item->enclosure_url = (string) $link['href'];
                $item->enclosure_type = (string) $link['type'];

                if (Filter::isRelativePath($item->enclosure_url)) {
                    $item->enclosure_url = Filter::getAbsoluteUrl($item->enclosure_url, $feed->url);
                }

                break;
            }
        }
    }

    /**
     * Find the item language
     *
     * @access public
     * @param  SimpleXMLElement   $entry   Feed item
     * @param  \PicoFeed\Item     $item    Item object
     * @param  \PicoFeed\Feed     $feed    Feed object
     */
    public function findItemLanguage(SimpleXMLElement $entry, Item $item, Feed $feed)
    {
        $item->language = $feed->language;
    }

    /**
     * Get the URL from a link tag
     *
     * @access public
     * @param  SimpleXMLElement   $xml    XML tag
     * @return string
     */
    public function getLink(SimpleXMLElement $xml)
    {
        foreach ($xml->link as $link) {
            if ((string) $link['type'] === 'text/html' || (string) $link['type'] === 'application/xhtml+xml') {
                return (string) $link['href'];
            }
        }

        return (string) $xml->link['href'];
    }

    /**
     * Get the entry content
     *
     * @access public
     * @param  SimpleXMLElement   $entry   XML Entry
     * @return string
     */
    public function getContent(SimpleXMLElement $entry)
    {
        if (isset($entry->content) && ! empty($entry->content)) {

            if (count($entry->content->children())) {
                return (string) $entry->content->asXML();
            }
            else {
                return (string) $entry->content;
            }
        }
        else if (isset($entry->summary) && ! empty($entry->summary)) {
            return (string) $entry->summary;
        }

        return '';
    }
}
