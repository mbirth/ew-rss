<?php

namespace BassDrive;

/**
 * BassDrive Archive Parser class
 * @author Markus Birth <markus@birth-online.de>
 */
class BDAParser
{
    const RFC_822 = 'D, d M Y H:i:s T';

    public function __construct($show_dir, $archive_url, $mp3_bitrate = 128)
    {
        $this->show_dir = $show_dir;
        $this->archive_url = $archive_url;
        $this->mp3_bitrate = $mp3_bitrate;
        $this->cachefile = $show_dir . 'filesize.cache';
        $this->initCache();
    }

    private function initCache()
    {
        $this->len_cache = array();
        $this->cache_dirty = false;
        if (file_exists($this->cachefile)) {
            $this->len_cache = json_decode(file_get_contents($this->cachefile), true);
        }
    }

    public function __destruct()
    {
        // Flush filesize cache if needed
        if ($this->cache_dirty) {
            file_put_contents($this->cachefile, json_encode($this->len_cache, JSON_PRETTY_PRINT), LOCK_EX);
        }
    }

    public function fetchFiles()
    {
        $src_data = file_get_contents($this->archive_url);

        $dom = new \DOMDocument();
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
            $url = $this->archive_url . $href;
            $size = $this->getSize($url);

            array_push($file_list, array(
                'url' => $url,
                'title' => trim($title),
                'size' => $size,
                'duration' => $this->getDuration($size),
                'date' => $this->getDateFromTitle(trim($title)),
            ));
        }

        return $file_list;
    }

    public function getSize($url)
    {
        if (isset($this->len_cache[$url])) {
            return $this->len_cache[$url];
        }

        $context = stream_context_set_default(array(
            'http' => array(
                'method' => 'HEAD',
            ),
        ));
        $headers = get_headers($url, 1);
        $length = intval($headers['Content-Length']);
        $this->len_cache[$url] = $length;
        $this->cache_dirty = true;
        return $length;
    }

    public function getDuration($size)
    {
        //  70445111 Bytes = 01:13:22.80 =  73:22.80 = 4402.80 seconds (128 kb/s)
        // 147530737 Bytes = 02:33:40.64 = 153:40.64 = 9220.64 seconds (128 kb/s)
        $bitrate = intval($this->mp3_bitrate) * 1000;
        $seconds = $size / ($bitrate / 8);

        $hours = intdiv($seconds, 3600);
        $seconds = $seconds % 3600;
        $minutes = intdiv($seconds, 60);
        $seconds = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function getDateFromTitle($title)
    {
        $datestr = substr($title, 1, 10);
        $iso_date = substr($datestr, 0, 4) . '-' . substr($datestr, 5, 2) . '-' . substr($datestr, 8, 2);
        return $iso_date;
    }
}
