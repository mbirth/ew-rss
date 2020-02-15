This is a script that fetches a directory listing from
[The BassDrive archives](http://archives.bassdrivearchive.com/)
and generates an RSS feed from the files located there.

It uses rsslogo.jpg for the RSS2 logo (max. 144x400px) and
biglogo.jpg for the iTunes logo (min. 1400x1400px). Also
intro.txt for the feed description.

Files' sizes are fetched via a HEAD request and cached. The
show length is calculated based upon assumed 128 kbps.
