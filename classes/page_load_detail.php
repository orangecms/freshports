<?php
	#
	# $Id: page_load_detail.php,v 1.3 2013-04-08 12:15:34 dan Exp $
	#
	# Copyright (c) 2003 DVL Software Limited
	#

	$Debug = 0;

// base class for keeping statistics on page rendering issues
class PageLoadDetail {

	var $dbh;

	var $StartTime;

	var $LocalResult;


	function __construct() {
		$this->StartTime = microtime();
	}
	
	function DBSet($dbh) {
		$this->dbh	= $dbh;
	}
	
	function ElapsedTime() {
		#
		# function to return the absolute difference between two microtime strings.
		# as obtained from PHP user contributed notes
		# mdchaney@michaelchaney.com (19-Oct-2002 07:53)
		#

		list($a_micro, $a_int)=explode(' ', $this->StartTime);
		list($b_micro, $b_int)=explode(' ', microtime());
		if ($a_int > $b_int) {
			return ($a_int-$b_int)+($a_micro-$b_micro);
		} elseif ($a_int==$b_int) {
			if ($a_micro>$b_micro) {
				return ($a_int-$b_int)+($a_micro-$b_micro);
			} elseif ($a_micro<$b_micro) {
				return ($b_int-$a_int)+($b_micro-$a_micro);
			} else {
				return 0;
			}
		} else { // $a_int<$b_int
			return ($b_int-$a_int)+($b_micro-$a_micro);
		}
	}

	function Save() {
		#
		# Record the statistics
		#

		GLOBAL $User;

		$Debug = 0;

		$UserID = null;
		if (IsSet($User) && IsSet($User->id) && $User->id !== '') {
			$UserID = $User->id;
		}

#		echo "\$UserID='$UserID'<br>";
		$params = array($_SERVER['SCRIPT_NAME']);
		if (!empty($UserID)) {
			$params[] = $UserID;
			$sql = '
INSERT INTO page_load_detail(page_name,
                             user_id,
                             ip_address,
                             full_url,
                             rendering_time)
                     values ($1, $2, $3, $4, $5)';
		} else {
			$sql = '
INSERT INTO page_load_detail(page_name,
                             ip_address,
                             full_url,
                             rendering_time)
                     values ($1, $2, $3, $4)';
		}
		$params[] = $_SERVER['REMOTE_ADDR'];
		$params[] = $_SERVER['REQUEST_URI'];
		$params[] = $this->ElapsedTime() . ' seconds';
		if ($Debug) echo $sql;
		$result = pg_query_params($this->dbh, $sql, $params);
		if ($result) {
			$return = 1;
		} else {
			echo "error " . pg_last_error($this->dbh);
			$return = -1;
		}

		return $return;
	}

}
