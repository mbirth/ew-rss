<?php

/**
 * BassDrive RSS Feed Generator
 * @author Markus Birth <markus@birth-online.de>
 */

require_once 'lib/BDAParser.php';
require_once 'lib/RSSGenerator.php';

use \BassDrive\BDAParser;
use \BassDrive\RSSGenerator;

$config = parse_ini_file('global.ini', true);
if ($config === false) {
    die('global.ini not found or invalid syntax. See https://www.php.net/manual/en/function.parse-ini-file .');
}

date_default_timezone_set($config['config']['time_zone']);
$base_url   = $config['config']['base_url'];

if (!array_key_exists('show', $_GET)) {
    header('Content-Type: text/html');
    echo '<html><body>No show selected. Available shows (RSS feeds):<br/>';
    echo '<ul>';
    $dirs = glob('shows/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $show = basename($dir);
        $show_conf = parse_ini_file($dir . '/config.ini', true);
        if ($show_conf === false) {
            echo '<li>No config found or invalid: <strong>' . $show . '</strong></li>';
            continue;
        }
        echo '<li>';
        echo '<a href="' . $base_url . '?show=' . $show . '">';
        echo $show_conf['show']['title'] . ' (' . $show . ')';
        echo '</a>';
        echo '</li>';
    }
    echo '</ul>';
    echo '</body></html>';
    exit();
}

// If we get here, a show was selected

$show = $_GET['show'];
$show_dir = realpath('shows/' . $show) . '/';

$show_conf = parse_ini_file($show_dir . 'config.ini', true);
if ($show_conf === false) {
    die('Couldn\'t read (or parse) show\'s config.ini. See https://www.php.net/manual/en/function.parse-ini-file .');
}

// PARSE BASSDRIVE ARCHIVE
$show_data = new BDAParser($show_dir, $show_conf['archive']['url'], $show_conf['archive']['mp3_bitrate']);
$file_list = $show_data->fetchFiles();

// GENERATE RSS XML
$rss_gen = new RSSGenerator($base_url . '?show=' . $show, $base_url . 'shows/' . $show . '/');
$rss_gen->setGlobalConfig($config);
$rss_gen->setShowConfig($show_conf);
$rss_gen->setShowIntro(file_get_contents($show_dir . 'intro.txt'));

foreach ($file_list as $f) {
    $rss_gen->addItem($f['url'], $f['title'], $f['size'], $f['duration'], $f['date']);
}

// OUTPUT RSS
$rss = $rss_gen->getRss();
file_put_contents('feed_' . $show . '.rss2', $rss);

header('Content-Type: application/rss+xml');
echo $rss;
