<?php
	#
	# $Id: newsfeed.php,v 1.7 2013-02-15 02:09:22 dan Exp $
	#
	# Copyright (c) 1998-2007 DVL Software Limited
	#

	DEFINE('MAX_PORTS', 20);
	define('TIME_ZONE', 'UTC');

	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/common.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/freshports.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/databaselogin.php');

	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/getvalues.php');


	require_once('/usr/local/share/UniversalFeedCreator/lib/Element/FeedDate.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Element/FeedHtmlField.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Element/HtmlDescribable.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Element/FeedImage.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Element/FeedItem.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Creator/FeedCreator.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Creator/AtomCreator03.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Creator/HTMLCreator.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Creator/JSCreator.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Creator/MBOXCreator.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Creator/OPMLCreator.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Creator/PIECreator01.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Creator/RSSCreator091.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Creator/RSSCreator10.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/Creator/RSSCreator20.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/UniversalFeedCreator.php'); 
	require_once('/usr/local/share/UniversalFeedCreator/lib/constants.php'); 
	
function newsfeed($dbh, $Format, $WatchListID = 0, $BranchName = BRANCH_HEAD, $Flavor = '') { # $OrderBy = '', $Where = '') {

	# avoid: [24-Jul-2022 12:36:25 UTC] PHP Warning:  Cannot modify header information - headers already sent by (output started at /usr/local/www/freshports/classes/newsfeed.php:353) in /usr/local/share/UniversalFeedCreator/lib/Creator/FeedCreator.php on line 215
	# some watch lists can be large
	$old_value = ini_set('memory_limit', '512M');

	$WatchListID = pg_escape_string($dbh, $WatchListID);
	$Format      = pg_escape_string($dbh, $Format);
	$Flavor      = pg_escape_string($dbh, $Flavor);

	$PHP_SELF = $_SERVER['PHP_SELF'];

	# potential for exploitation here, with $WatchListID, $BranchName, $Format & $Flavor
	if ($WatchListID) {
		define('NEWSFEEDCACHE', NEWS_DIRECTORY . '/news.' . $WatchListID . '.'  . $Format . '.' . $BranchName . '.xml');
	} else {
		if (empty($Flavor)) {
			define('NEWSFEEDCACHE', NEWS_DIRECTORY . '/news.' . $Format . '.' . $BranchName . '.xml');
		} else {
			define('NEWSFEEDCACHE', NEWS_DIRECTORY . '/news.' . $Format . '.' . $BranchName . '.' . $Flavor . '.xml');
		}
	}

	$MaxNumberOfPorts = pg_escape_string($dbh, MAX_PORTS);

	$rss = new UniversalFeedCreator(); 

	# NOTE NOTE NOTE NOTE NOTE NOTE NOTE NOTE NOTE NOTE NOTE
	#
	# this next call may wind up using the cached file and
	# the rest of the function may never be executed.
	#
	# NOTE NOTE NOTE NOTE NOTE NOTE NOTE NOTE NOTE NOTE NOTE

	# Commenting out this next line is useful for Debugging.
	# This will read the cached file file, and it meets the freshness
	# standard (not too old), supply it to the user.
	# Otherwise, we fall through and create a new feed.
	#
	# there is a race condition within UniversalFeedCreator/lib/Creator/FeedCreator.php:
	# useCached() checks for file existence and calls _redirect()
	# if the cache is cleared in that tile, _redirect() will product this error:
	#
	# [28-Mar-2023 23:39:05 UTC] PHP Warning:  readfile(/var/db/freshports/cache/news/news.NEWS.head.xml): Failed to open stream: No such file or directory in /usr/local/share/UniversalFeedCreator/lib/Creator/FeedCreator.php on line 217
	#
	# That will give the user a bad feed:
	#
        # [IPv6 address redacted]- - [28/Mar/2023:23:39:05 +0000] "GET /backend/news.php HTTP/2.0" 200 234 "https://www.freshports.org" "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)"
        #
        # Perhaps we should not be clearing out the cache that way. Perhap we should just let it get stale
        #
	$rss->useCached($Format, NEWSFEEDCACHE, NEWSFEED_REFRESH_SECONDS);

	$rss->title          = 'FreshPorts news'; 
	$rss->description    = 'The place for ports'; 
	$rss->syndicationURL = $_SERVER['HTTP_HOST'] . '/' .  $PHP_SELF;

	$rss->editor    = 'editor@freshports.org (The Editor)';
	$rss->webmaster = 'webmaster@freshports.org (The Webmaster)';
	$rss->language  = 'en-us';
	$rss->copyright = 'Copyright ' . COPYRIGHTYEARS . ' ' . COPYRIGHTHOLDER;

	//optional
	//$rss->descriptionTruncSize = 500;
	//$rss->descriptionHtmlSyndicated = true;
	//$rss->xslStyleSheet = 'http://feedster.com/rss20.xsl';

	$rss->link    = 'http://' . $_SERVER['HTTP_HOST']; 
	$rss->feedURL = 'http://' . $_SERVER['HTTP_HOST'] . '/' .  $PHP_SELF; 

	$image = new FeedImage(); 
	$image->title       = 'FreshPorts news'; 
	$image->url         = 'http://' . $_SERVER['HTTP_HOST'] .'/images/freshports_mini.jpg'; 
	$image->link        = 'http://' . $_SERVER['HTTP_HOST']; ; 
	$image->description = 'Feed provided by FreshPorts. Click to visit.'; 

	//optional
	$image->descriptionTruncSize      = 500;
	$image->descriptionHtmlSyndicated = true;

	$rss->image = $image;

	$MyMaxArticles = 10;
	if (!IsSet($MaxArticles) || !$MaxArticles || $MaxArticles < 1 || $MaxArticles > $MyMaxArticles) {
	    $MaxArticles = $MyMaxArticles;
	}

	if ($WatchListID) {
	# this is for newfeeds based on personal watch lists
	$sql = "-- " . __FILE__ . '::' . __FUNCTION__ . '::WatchListID 
	select E.name 			as port, 
		   P.id 				as id, 
	       C.name 		as category, 
	       C.id 		as category_id, 
	       P.version 		as version, 
	       P.revision 		as revision, 
	       E.id 			as element_id,
           to_char(CL.commit_date - SystemTimeAdjust(), \'DD Mon\')  AS commit_date,
           to_char(CL.commit_date - SystemTimeAdjust(), \'HH24:MI\') AS commit_time,
           commit_date          AS news_date,
           CL.description       AS commit_description,
           CLP.port_epoch as epoch,
           CL.committer,
           CL.committer_name,
           CL.committer_email,
           CL.author_name,
           CL.author_email,
           CL.commit_date as commit_date_sort,
           CL.message_id,
           CL.commit_hash_short
	  FROM watch_list_element WLE, element E, categories C, ports P,
           commit_log CL, commit_log_ports CLP
	 WHERE CLP.commit_log_id = CL.id
       AND CLP.port_id       = P.id
       AND P.element_id      = WLE.element_id
	   AND P.element_id      = E.id
	   AND P.category_id     = C.id 
	   AND WLE.watch_list_id = $1';

	   $params = array($WatchListID);

	} else {
		# no WatchListID supplied
		switch ($Flavor) {
			case 'new':
				$sql = "-- " . __FILE__ . '::' . __FUNCTION__ . '::new
  SELECT C.name    AS category,
         E.name    AS port,
         E.status  AS status,
         P.forbidden,
         P.broken,
         P.deprecated,
         P.element_id                     AS element_id,
         P.version  AS version,
         P.revision AS revision,
         P.version                        AS ports_version,
         P.revision                       AS ports_revision,
         P.portepoch                      AS epoch,
         P.date_added                     AS news_date,
         P.short_description              AS short_description,
         P.category_id
    FROM (SELECT P1.* 
            FROM ports_active            P1
           WHERE P1.date_added IS NOT NULL ORDER BY P1.date_added DESC LIMIT 20) AS P
    JOIN element    E   ON P.element_id  = E.id
    JOIN categories C   ON P.category_id = C.id
ORDER BY P.date_added DESC, E.name, category, version';
				$params = array();
				break;

			case 'broken':
				$sql = "-- " . __FILE__ . '::' . __FUNCTION__ . '::broken
  SELECT C.name    AS category,
         E.name    AS port,
         E.status  AS status,
         P.forbidden,
         P.broken,
         P.deprecated,
         P.element_id                     AS element_id,
         CASE when CLP.port_version  IS NULL then P.version  else CLP.port_version  END as version,
         CASE when CLP.port_revision IS NULL then P.revision else CLP.port_revision END AS revision,
         P.version                        AS ports_version,
         P.revision                       AS ports_revision,
         P.portepoch                      AS epoch,
         date_part(\'epoch\', P.date_added) AS date_added,
         P.short_description              AS short_description,
         P.category_id,
         CLP.port_version  AS clp_version,
         CLP.port_revision AS clp_revision,
         CLP.needs_refresh AS needs_refresh,
         CL.id     AS commit_log_id, 
         CL.commit_date       AS news_date,
         CL.message_subject,
         CL.message_id,
         CL.commit_hash_short,
         CL.committer,
         CL.committer_name,
         CL.committer_email,
         CL.author_name,
         CL.author_email,
         CL.description       AS commit_description,
         to_char(CL.commit_date - SystemTimeAdjust(), \'DD Mon\')  AS commit_date,
         to_char(CL.commit_date - SystemTimeAdjust(), \'HH24:MI\') AS commit_time,
         CL.encoding_losses
    FROM (SELECT P1.* 
            FROM ports_active            P1
           WHERE P1.broken IS NOT NULL) AS P
    JOIN commit_log           CL  ON P.last_commit_id  = CL.id 
    JOIN commit_log_ports     CLP ON CLP.commit_log_id = CL.id AND P.id = CLP.port_id
    JOIN element              E   ON P.element_id      = E.id
    JOIN categories           C   ON P.category_id     = C.id
ORDER BY CL.commit_date DESC, CL.id ASC, E.name, category, version';
				$params = array();
				break;

                        case 'vuln':
                                $sql = "-- " . __FILE__ . '::' . __FUNCTION__ . '::vuln
  SELECT C.name    AS category,
         P.name    AS port,
         P.status  AS status,
         P.forbidden,
         P.broken,
         P.deprecated,
         P.element_id                     AS element_id,
         P.version  AS version,
         P.revision AS revision,
         P.version                        AS ports_version,
         P.revision                       AS ports_revision,
         P.portepoch                      AS epoch,
         date_part(\'epoch\', P.date_added) AS date_added,
         CL.commit_date                   AS news_date,
         P.short_description              AS short_description,
         P.category_id
         FROM ports_active            P
         JOIN ports_vulnerable PV ON PV.current    > 0            AND PV.port_id = P.id
         JOIN categories       C  ON P.category_id = C.id
         JOIN commit_log       CL ON CL.id         = P.last_commit_id
ORDER BY CL.commit_date;
';
				$params = array();
                                break;

			default:
			        # the goal, the latest 100 commits against ports, and one port from that commit.
			        # We start with the last 200 commits added to the system, because we're likely to get 100 ports from that.
			        # The lateral join is to get just one port from the commit_log_ports table
			        # we order by port.id so we get the same results on each query
				$sql = "-- " . __FILE__ . '::' . __FUNCTION__ . '::default
 SELECT  C.name    AS category,
         E.name    AS port,
         E.status  AS status,
         CLP.forbidden,
         CLP.broken,
         CLP.deprecated,
         CLP.element_id,
         CLP.version,
         CLP.revision,
         CLP.ports_version,
         CLP.ports_revision,
         CLP.epoch,
         CLP.date_added,
         CLP.short_description,
         CLP.category_id,
         CLP.clp_version,
         CLP.clp_revision,
         CLP.needs_refresh AS needs_refresh,
         CL.id     AS commit_log_id, 
         CL.commit_date       AS news_date,
         CL.message_subject,
         CL.message_id,
         CL.commit_hash_short,
         CL.committer,
         CL.committer_name,
         CL.committer_email,
         CL.author_name,
         CL.author_email,
         CL.description       AS commit_description,
         to_char(CL.commit_date - SystemTimeAdjust(), \'DD Mon\')  AS commit_date,
         to_char(CL.commit_date - SystemTimeAdjust(), \'HH24:MI\') AS commit_time,
         CL.encoding_losses
    FROM (select cl.* 
            from commit_log cl
           where exists (select *
                           from commit_log_ports clp
                           join commit_log_branches clb on clp.commit_log_id = clb.commit_log_id
                           join system_branch sb on sb.id = clb.branch_id
                          where clp.commit_log_id = cl.id and sb.branch_name = $1)
           order by cl.commit_date desc limit 100 ) AS CL
    JOIN LATERAL ( select CLP1.commit_log_id, P.forbidden, P.broken, P.deprecated, P.element_id,
                          CASE when CLP1.port_version  IS NULL then P.version  else CLP1.port_version  END as version,
                          CASE when CLP1.port_revision IS NULL then P.revision else CLP1.port_revision END AS revision,
                          P.version                        AS ports_version,
                          P.revision                       AS ports_revision,
                          P.portepoch                      AS epoch,
                          date_part(\'epoch\', P.date_added) AS date_added,
                          P.short_description              AS short_description,
                          P.category_id,
                          CLP1.port_version  AS clp_version,
                          CLP1.port_revision AS clp_revision,
                          CLP1.needs_refresh AS needs_refresh
                     from commit_log_ports CLP1, ports P 
                    where CLP1.commit_log_id = CL.id
                      and CLP1.port_id   = P.id
                  ORDER BY P.id 
                     LIMIT 1) AS CLP on true
    JOIN element             E   ON CLP.element_id    = E.id
    JOIN categories          C   ON CLP.category_id   = C.id
    ORDER BY CL.commit_date desc
	LIMIT 500';

                            $params = array($BranchName);

		} # switch flavor
	} # WatchListID	

#	echo "<pre>$sql</pre>";
#
#	exit;

	$ServerName = str_replace('freshports', 'FreshPorts', $_SERVER['HTTP_HOST']);
	
	# get the results
        $result = pg_query_params($dbh, $sql, $params);
	if (!$result) {
		syslog(LOG_ERR, 'sql error ' . pg_last_error($dbh));

		die('We broke the SQL, sorry');
	}

	# build the information for the feed.
	while ($myrow = pg_fetch_array($result)) {
		$item = new FeedItem();

		switch ($Flavor) {
			case 'broken':
			case 'new':
			case 'vuln':
				# this is a relative link
				$link        = freshports_Port_URL($dbh, $myrow['category'], $myrow['port'], $BranchName);;
				$date        = $myrow['news_date'];
				$author      = $myrow['maintainer'] ?? '';
				$description = $myrow['short_description'];
				break;
				
			default:
				$link        = freshports_Commit_Link_Port_URL($myrow['message_id'], $myrow['category'], $myrow['port']);
				$date        = $myrow['news_date'];
				$author      = $myrow['committer'] . '@FreeBSD.org (' . $myrow['committer'] . ')';
				$description = $myrow['commit_description'];
				break;
		}

		$item->title = $myrow['category'] . '/' . $myrow["port"] . ' - ' . freshports_PackageVersion($myrow['version'], $myrow['revision'], $myrow['epoch']);
		$item->link  = $link;
		$item->description = nl2br(htmlspecialchars(trim($description)));

		//optional
		//item->descriptionTruncSize = 500;
		$item->descriptionHtmlSyndicated = false;
	
		if (!empty($date)) {
			$item->date   = strtotime($date);
                }
		$item->source = $_SERVER['HTTP_HOST']; 
		$item->author = $author;
		$item->guid   = $link; 

		$rss->addItem($item); 
	} 

	// valid format strings are: RSS0.91, RSS1.0, RSS2.0, PIE0.1, MBOX, OPML, ATOM0.3, HTML, JS

	return $rss->saveFeed($Format, NEWSFEEDCACHE);
}
