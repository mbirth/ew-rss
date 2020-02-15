<?php

$baseUrl   = 'https://rpi4.mbirth.de/electronicwarfare/';
$sourceUrl = 'http://archives.bassdrivearchive.com/6%20-%20Saturday/Electronic%20Warfare%20-%20The%20Overfiend/';

$src_data = file_get_contents($sourceUrl);

define('RFC_822', 'D, d M Y H:i:s T');

$dom = new DOMDocument();
$dom->loadHTML($src_data);

$anchors = $dom->getElementsByTagName('a');

$file_list = array();
foreach ($anchors as $anchor) {
    $title = $anchor->nodeValue;
    $href = $anchor->attributes->getNamedItem('href')->nodeValue;

    if (substr($href, -4) !== '.mp3') {
        continue;
    }

    $title = urldecode(substr($href, 0, -4));   // strip off ".mp3" file extension
    $url = $sourceUrl . $href;

    $file_list[$url] = trim($title);
}

unset($dom);

$namespaceURIs = [
    'xmlns' => 'http://www.w3.org/2000/xmlns/',
    'atom' => 'http://www.w3.org/2005/Atom',
    'content' => 'http://purl.org/rss/1.0/modules/content/',
    'itunes' => 'http://www.itunes.com/DTDs/Podcast-1.0.dtd',
    'spotify' => 'http://www.spotify.com/ns/rss',
    'psc' => 'https://podlove.org/simple-chapters/',   // for chapter marks
    'dcterms' => 'https://purl.org/dc/terms',   // for validity periods
];

date_default_timezone_set('America/New_York');

$dom = new DOMDocument('1.0', 'utf-8');
$dom->formatOutput = true;
$root = $dom->createElement('rss');
$root->appendChild(new DOMAttr('version', '2.0'));
$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', $namespaceURIs['atom']);
$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:content', $namespaceURIs['content']);
$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:itunes', $namespaceURIs['itunes']);
$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:spotify', $namespaceURIs['spotify']);
// $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dcterms', $namespaceURIs['dcterms']);
$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:psc', $namespaceURIs['psc']);
$dom->appendChild($root);

$channel = $dom->createElement('channel');
$channel->appendChild($dom->createElement('title', 'Electronic Warfare'));
$channel->appendChild($dom->createElement('link', 'https://www.facebook.com/louis.overfiend'));
$channel->appendChild($dom->createElement('copyright', 'BassDrive.com / Louis Overfiend'));
$channel->appendChild($dom->createElement('itunes:author', 'BassDrive.com / Louis Overfiend'));

$atom_link = $dom->createElement('atom:link');
$atom_link->appendChild(new DOMAttr('href', $baseUrl . 'index.rss2'));
$atom_link->appendChild(new DOMAttr('rel', 'self'));
$atom_link->appendChild(new DOMAttr('type', 'application/rss+xml'));
$channel->appendChild($atom_link);

$channel->appendChild($dom->createElement('description', trim(str_replace('&', '&amp;', file_get_contents('intro.txt')))));
$channel->appendChild($dom->createElement('language', 'en-us'));

$image = $dom->createElement('image');
$image->appendChild($dom->createElement('url', $baseUrl . 'rsslogo.jpg'));   // RSS spec says max. dimensions are 144x400
$image->appendChild($dom->createElement('title', 'Electronic Warfare'));
$image->appendChild($dom->createElement('link', 'https://www.facebook.com/louis.overfiend'));
$image->appendChild($dom->createElement('width', '144'));
$image->appendChild($dom->createElement('height', '144'));
$channel->appendChild($image);
$i_image = $dom->createElement('itunes:image');
$i_image->appendChild(new DOMAttr('href', $baseUrl . 'biglogo.jpg'));   // Apple requires min.(!) size of 1400x1400, 1:1 aspect
$channel->appendChild($i_image);

$channel->appendChild($dom->createElement('lastBuildDate', date(RFC_822)));
$channel->appendChild($dom->createElement('docs', 'http://blogs.law.harvard.edu/tech/rss'));
$channel->appendChild($dom->createElement('generator', 'Handcrafted with love'));
$channel->appendChild($dom->createElement('managingEditor', 'markus@birth-online.de (Markus Birth)'));
$channel->appendChild($dom->createElement('webMaster', 'markus@birth-online.de (Markus Birth)'));
$channel->appendChild($dom->createElement('ttl', '60'));

$skipDays = $dom->createElement('skipDays');
$skipDays->appendChild($dom->createElement('day', 'Monday'));
$skipDays->appendChild($dom->createElement('day', 'Tuesday'));
$skipDays->appendChild($dom->createElement('day', 'Wednesday'));
$skipDays->appendChild($dom->createElement('day', 'Thursday'));
$skipDays->appendChild($dom->createElement('day', 'Friday'));
$skipDays->appendChild($dom->createElement('day', 'Sunday'));
$channel->appendChild($skipDays);

$i_cat = $dom->createElement('itunes:category');
$i_cat->appendChild(new DOMAttr('text', 'Music'));
$channel->appendChild($i_cat);
$channel->appendChild($dom->createElement('itunes:explicit', 'clean'));
$channel->appendChild($dom->createElement('itunes:complete', 'no'));      // "yes" = show has ended, no further episodes
$channel->appendChild($dom->createElement('itunes:type', 'episodic'));    // "serial" = to be consumed oldest to newest; "episodic" = can be consumed randomly

//$s_limit = $dom->createElement('spotify:limit');
//$s_limit->appendChild(new DOMAttr('recentCount', '3'));   // At most this number of episodes appear in the client
//$channel->appendChild($s_limit);

//$channel->appendChild($dom->createElement('spotify:countryOfOrigin', 'us'));   // set target market/territory by country, omit for "global"

$len_cache = array();
$cache_dirty = false;
if (file_exists('genfeed.cache')) {
    $len_cache = json_decode(file_get_contents('genfeed.cache'), true);
}

function getSize($url)
{
    global $len_cache, $cache_dirty;
    if (isset($len_cache[$url])) {
        return $len_cache[$url];
    }

    $context = stream_context_set_default(array(
        'http' => array(
            'method' => 'HEAD',
        ),
    ));
    $headers = get_headers($url, 1);
    $length = intval($headers['Content-Length']);
    $len_cache[$url] = $length;
    $cache_dirty = true;
    return $length;
}

function getDuration($size)
{
    //  70445111 Bytes = 01:13:22.80 =  73:22.80 = 4402.80 seconds (128 kb/s)
    // 147530737 Bytes = 02:33:40.64 = 153:40.64 = 9220.64 seconds (128 kb/s)
    $seconds = $size / (128000 / 8);

    $hours = intdiv($seconds, 3600);
    $seconds = $seconds % 3600;
    $minutes = intdiv($seconds, 60);
    $seconds = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

$latest_date = -1;
foreach ($file_list as $url => $title) {
    $item = $dom->createElement('item');
    $guid = $dom->createElement('guid', $url);
    $guid->appendChild(new DOMAttr('isPermalink', 'true'));   // guid is a static URL
    $item->appendChild($guid);
    $item->appendChild($dom->createElement('title', $title));

    $size = getSize($url);

    $enclosure = $dom->createElement('enclosure');
    $enclosure->appendChild(new DOMAttr('type', 'audio/mpeg'));
    $enclosure->appendChild(new DOMAttr('length', $size));
    $enclosure->appendChild(new DOMAttr('url', $url));
    $item->appendChild($enclosure);

    $item->appendChild($dom->createElement('itunes:duration', getDuration($size)));

    $datestr = substr($title, 1, 10);
    $pubDate = mktime(11, 00, 00, intval(substr($datestr, 5, 2)), intval(substr($datestr, 8, 2)), intval(substr($datestr, 0, 4)));
    $item->appendChild($dom->createElement('pubDate', date(RFC_822, $pubDate)));

    if ($pubDate > $latest_date) {
        $latest_date = $pubDate;
    }
    
    $channel->appendChild($item);
}

$channel->appendChild($dom->createElement('pubDate', date(RFC_822, $latest_date)));

$root->appendChild($channel);

$dom->save('podcast.rss2');

if ($cache_dirty) {
    file_put_contents('genfeed.cache', json_encode($len_cache, JSON_PRETTY_PRINT));
}

header('Content-Type: application/rss+xml');
echo file_get_contents('podcast.rss2');
