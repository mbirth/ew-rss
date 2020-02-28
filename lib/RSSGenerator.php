<?php

namespace BassDrive;

/**
 * BassDrive RSS Feed Generator class
 * @author Markus Birth <markus@birth-online.de>
 */
class RSSGenerator
{
    const RFC_822 = 'D, d M Y H:i:s T';
    const NAMESPACE_URIS = [
        'xmlns' => 'http://www.w3.org/2000/xmlns/',
        'atom' => 'http://www.w3.org/2005/Atom',
        'content' => 'http://purl.org/rss/1.0/modules/content/',
        'itunes' => 'http://www.itunes.com/DTDs/Podcast-1.0.dtd',
        'spotify' => 'http://www.spotify.com/ns/rss',
        'psc' => 'https://podlove.org/simple-chapters/',   // for chapter marks
        'dcterms' => 'https://purl.org/dc/terms',   // for validity periods
    ];

    public function __construct($feed_url, $show_url)
    {
        $this->self_url = $feed_url;
        $this->show_url = $show_url;
        $this->items = array();
    }

    public function setGlobalConfig($config_arr)
    {
        $this->global_conf = $config_arr;
    }

    public function setShowConfig($config_arr)
    {
        $this->show_conf = $config_arr;
    }

    public function setShowIntro($intro_text)
    {
        $this->show_intro = $intro_text;
    }

    public function addItem($url, $title, $size_bytes, $duration_secs, $pub_date)
    {
        array_push($this->items, array(
            'url' => $url,
            'title' => $title,
            'size' => $size_bytes,
            'duration' => $duration_secs,
            'date' => $pub_date,
        ));
    }

    public function getRss()
    {
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('rss');
        $root->appendChild(new \DOMAttr('version', '2.0'));
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', self::NAMESPACE_URIS['atom']);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:content', self::NAMESPACE_URIS['content']);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:itunes', self::NAMESPACE_URIS['itunes']);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:spotify', self::NAMESPACE_URIS['spotify']);
        // $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dcterms', self::NAMESPACE_URIS['dcterms']);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:psc', self::NAMESPACE_URIS['psc']);
        $dom->appendChild($root);
        
        $channel = $dom->createElement('channel');
        $channel->appendChild($dom->createElement('title', $this->show_conf['show']['title']));
        $channel->appendChild($dom->createElement('link', $this->show_conf['show']['link']));
        $channel->appendChild($dom->createElement('copyright', $this->show_conf['show']['author']));
        $channel->appendChild($dom->createElement('itunes:author', $this->show_conf['show']['author']));
        
        $atom_link = $dom->createElement('atom:link');
        $atom_link->appendChild(new \DOMAttr('href', $this->self_url));
        $atom_link->appendChild(new \DOMAttr('rel', 'self'));
        $atom_link->appendChild(new \DOMAttr('type', 'application/rss+xml'));
        $channel->appendChild($atom_link);
        
        $channel->appendChild($dom->createElement('description', trim(str_replace('&', '&amp;', $this->show_intro))));
        $channel->appendChild($dom->createElement('language', $this->show_conf['show']['language']));
        
        $image = $dom->createElement('image');
        $image->appendChild($dom->createElement('url', $this->show_url . $this->show_conf['show']['small_logo']));   // RSS spec says max. dimensions are 144x400
        $image->appendChild($dom->createElement('title', $this->show_conf['show']['title']));
        $image->appendChild($dom->createElement('link', $this->show_conf['show']['link']));
        $image->appendChild($dom->createElement('width', $this->show_conf['show']['small_logo_width']));
        $image->appendChild($dom->createElement('height', $this->show_conf['show']['small_logo_height']));
        $channel->appendChild($image);
        $i_image = $dom->createElement('itunes:image');
        $i_image->appendChild(new \DOMAttr('href', $this->show_url . $this->show_conf['show']['big_logo']));   // Apple requires min.(!) size of 1400x1400, 1:1 aspect
        $channel->appendChild($i_image);
        
        $channel->appendChild($dom->createElement('lastBuildDate', date(self::RFC_822)));
        $channel->appendChild($dom->createElement('docs', 'http://blogs.law.harvard.edu/tech/rss'));
        $channel->appendChild($dom->createElement('generator', 'ew-rss 2020-02-15 by Markus Birth'));
        $channel->appendChild($dom->createElement('managingEditor', $this->global_conf['config']['managing_editor']));
        $channel->appendChild($dom->createElement('webMaster', $this->global_conf['config']['webmaster']));
        $channel->appendChild($dom->createElement('ttl', $this->global_conf['config']['caching_ttl']));
        
        $i_owner = $dom->createElement('itunes:owner');
        $i_owner->appendChild($dom->createElement('itunes:name', $this->global_conf['config']['itunes_owner_name']));
        $i_owner->appendChild($dom->createElement('itunes:email', $this->global_conf['config']['itunes_owner_email']));
        $channel->appendChild($i_owner);
        
        $skipDays = $dom->createElement('skipDays');
        $days_on = explode(',', $this->show_conf['show']['schedule_days']);
        if (!in_array('Mo', $days_on)) {
            $skipDays->appendChild($dom->createElement('day', 'Monday'));
        }
        if (!in_array('Tu', $days_on)) {
            $skipDays->appendChild($dom->createElement('day', 'Tuesday'));
        }
        if (!in_array('We', $days_on)) {
            $skipDays->appendChild($dom->createElement('day', 'Wednesday'));
        }
        if (!in_array('Th', $days_on)) {
            $skipDays->appendChild($dom->createElement('day', 'Thursday'));
        }
        if (!in_array('Fr', $days_on)) {
            $skipDays->appendChild($dom->createElement('day', 'Friday'));
        }
        if (!in_array('Sa', $days_on)) {
            $skipDays->appendChild($dom->createElement('day', 'Saturday'));
        }
        if (!in_array('Su', $days_on)) {
            $skipDays->appendChild($dom->createElement('day', 'Sunday'));
        }
        $channel->appendChild($skipDays);
        
        $i_cat = $dom->createElement('itunes:category');
        $i_cat->appendChild(new \DOMAttr('text', $this->show_conf['show']['category']));
        $channel->appendChild($i_cat);
        $channel->appendChild($dom->createElement('itunes:explicit', $this->show_conf['show']['explicit']));
        $channel->appendChild($dom->createElement('itunes:complete', $this->show_conf['show']['complete']));
        $channel->appendChild($dom->createElement('itunes:type', $this->show_conf['show']['type']));

        //$s_limit = $dom->createElement('spotify:limit');
        //$s_limit->appendChild(new DOMAttr('recentCount', '3'));   // At most this number of episodes appear in the client
        //$channel->appendChild($s_limit);
        
        if (array_key_exists('country_of_origin', $this->show_conf['show'])) {
            // set target market/territory by country, omit for "global"
            $channel->appendChild($dom->createElement('spotify:countryOfOrigin', $this->show_conf['show']['country_of_origin']));
        }
        
        $latest_date = -1;
        foreach ($this->items as $i) {
            $item = $dom->createElement('item');
            $guid = $dom->createElement('guid', $i['url']);
            $guid->appendChild(new \DOMAttr('isPermalink', 'true'));   // guid is a static URL
            $item->appendChild($guid);
            $item->appendChild($dom->createElement('title', $i['title']));
                
            $enclosure = $dom->createElement('enclosure');
            $enclosure->appendChild(new \DOMAttr('type', 'audio/mpeg'));
            $enclosure->appendChild(new \DOMAttr('length', $i['size']));
            $enclosure->appendChild(new \DOMAttr('url', $i['url']));
            $item->appendChild($enclosure);
        
            $item->appendChild($dom->createElement('itunes:duration', $i['duration']));
            $dt = explode('-', $i['date']);
            $tt = explode(':', $this->show_conf['show']['schedule_time']);
            $pubDate = mktime($tt[0], $tt[1], $tt[2], $dt[1], $dt[2], $dt[0]);
            $item->appendChild($dom->createElement('pubDate', date(self::RFC_822, $pubDate)));
        
            if ($pubDate > $latest_date) {
                $latest_date = $pubDate;
            }
            
            $channel->appendChild($item);
        }

        $channel->appendChild($dom->createElement('pubDate', date(self::RFC_822, $latest_date)));

        $root->appendChild($channel);

        return $dom->saveXML();
    }
}
