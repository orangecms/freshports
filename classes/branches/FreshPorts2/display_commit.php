<?php
	#
	# $Id: display_commit.php,v 1.1.2.12 2006-12-06 17:51:30 dan Exp $
	#
	# Copyright (c) 2003-2006 DVL Software Limited
	#

	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/constants.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../classes/commit_record.php');

// base class for displaying commits
class DisplayCommit {

	var $Debug = 0;
	var $dbh;

	var $result;
	var $MaxNumberOfPorts;

	var $WatchListAsk    = '';	// either default or ask.  the watch list to which add/remove works.
	var $UserID          = 0;
	var $DaysMarkedAsNew = 10;
	var $LocalResult;
	var $HTML;
	
	var $FlaggedCommits;
	
	var $ShowAllPorts = FALSE;	# by default we show only the first few ports.
	
	var $SanityTestFailure = FALSE;

	function DisplayCommit($dbh, $result) {
		$this->dbh    = $dbh;
		$this->result = $result;
	}

	function SetDaysMarkedAsNew($DaysMarkedAsNew) {
		$this->DaysMarkedAsNew = $DaysMarkedAsNew;
	}

	function SetUserID($UserID) {
		$this->UserID = $UserID;
	}

	function SetWatchListAsk($WatchListAsk) {
		$this->WatchListAsk = $WatchListAsk;
	}
	
	function SetShowAllPorts($ShowAllPorts) {
		$this->ShowAllPorts = $ShowAllPorts;
	}

	function CreateHTML() {
		GLOBAL	$freshports_CommitMsgMaxNumOfLinesToShow;

		if (!$this->result) {
			syslog(LOG_ERR, __FILE__ . '::' . __LINE__ . ': no result set supplied');
            die("read from database failed");
			exit;
		}

		$NumRows = pg_numrows($this->result);
		if ($this->Debug) echo __FILE__ . ':' . __LINE__ . " Number of rows = $NumRows<br>\n";
		if (!$NumRows) { 
			$this->HTML = "<TR><TD>\n<P>Sorry, nothing found in the database....</P>\n</td></tr>\n";
			return 1;
		}
		
		# if we have a UserID, but no flagged commits, grab them
		#
		if ($this->UserID && !IsSet($this->FlaggedCommits)) {
			syslog(LOG_ERR, __FILE__ . '::' . __LINE__ . ': fetching Flagged commits');
		
			require_once($_SERVER['DOCUMENT_ROOT'] . '/../classes/commit_flag.php');

			$FlaggedCommits = new CommitFlag($this->dbh);
			$NumFlaggedCommits = $FlaggedCommits->Fetch($this->UserID);
			for ($i = 0; $i < $NumFlaggedCommits; $i++) {
				$FlaggedCommits->FetchNth($i);
				$this->FlaggedCommits[$FlaggedCommits->commit_log_id] = $FlaggedCommits->commit_log_id;
				if ($this->Debug) echo "fetching record # $i -> $FlaggedCommits->commit_log_id<br>";
			}
		}
	
		$i=0;
		$GlobalHideLastChange = "N";
		for ($i = 0; $i < $NumRows; $i++) {
			$myrow = pg_fetch_array ($this->result, $i);
			$mycommit = new CommitRecord();
			$mycommit->PopulateValues($myrow);
			$commits[$i] = $mycommit;
		}
	
		$LastDate = '';

		$this->HTML = "";
		unset($ThisCommitLogID);
		for ($i = 0; $i < $NumRows; $i++) {
			$mycommit = $commits[$i];
			$ThisCommitLogID = $mycommit->commit_log_id;

			if ($LastDate <> $mycommit->commit_date) {
				$LastDate = $mycommit->commit_date;
				$this->HTML .= '<TR><TD COLSPAN="3" BGCOLOR="' . BACKGROUND_COLOUR . '" HEIGHT="0">' . "\n";
				$this->HTML .= '   <FONT COLOR="#FFFFFF"><BIG>' . FormatTime($mycommit->commit_date, 0, "D, j M Y") . '</BIG></FONT>' . "\n";
				$this->HTML .= '</TD></TR>' . "\n\n";
			}

			$j = $i;

			$this->HTML .= "<TR><TD>\n";

			// OK, while we have the log change log, let's put the port details here.

			# count the number of ports in this commit
			$NumberOfPortsInThisCommit = 0;
			$MaxNumberPortsToShow      = 10;
			while ($j < $NumRows && $commits[$j]->commit_log_id == $ThisCommitLogID) {
				$NumberOfPortsInThisCommit++;
				$mycommit = $commits[$j];

				if ($NumberOfPortsInThisCommit == 1) {
					GLOBAL $freshports_mail_archive;

					$this->HTML .= '<SMALL>';
					$this->HTML .= '[ ' . $mycommit->commit_time . ' ' . freshports_CommitterEmailLink($mycommit->committer) . ' ]';
					$this->HTML .= '</SMALL>';
					$this->HTML .= '&nbsp;';
					$this->HTML .= freshports_Email_Link($mycommit->message_id);

					$this->HTML .= '&nbsp;';
					if ($this->UserID) {
						if (IsSet($this->FlaggedCommits[$mycommit->commit_log_id])) {
							$this->HTML .= freshports_Commit_Flagged_Link($mycommit->message_id);
						} else {
							$this->HTML .= freshports_Commit_Flagged_Not_Link($mycommit->message_id);
						}
					}

					if ($mycommit->EncodingLosses()) {
						$this->HTML .= '&nbsp;' . freshports_Encoding_Errors();
					}

					if ($mycommit->stf_message != '') {
						$this->HTML .= '&nbsp; ' . freshports_SanityTestFailure_Link($mycommit->message_id);
					}
				}

				if (($NumberOfPortsInThisCommit <= $MaxNumberPortsToShow) || $this->ShowAllPorts) {

					$this->HTML .= "<BR>\n";

					if (IsSet($mycommit->category) && $mycommit->category != '') {
						if ($this->UserID) {
							if ($mycommit->watch) {
								$this->HTML .= ' '. freshports_Watch_Link_Remove($this->WatchListAsk, $mycommit->watch, $mycommit->element_id) . ' ';
							} else {
								$this->HTML .= ' '. freshports_Watch_Link_Add   ($this->WatchListAsk, $mycommit->watch, $mycommit->element_id) . ' ';
							}
						}

						$this->HTML .= '<BIG><B>';
						$this->HTML .= '<A HREF="/' . $mycommit->category . '/' . $mycommit->port . '/">';
						$this->HTML .= $mycommit->port;
						$this->HTML .= '</A>';

						$PackageVersion = freshports_PackageVersion($mycommit->version, $mycommit->revision, $mycommit->epoch);
						if (strlen($PackageVersion) > 0) {
							$this->HTML .= ' ' . $PackageVersion;
						}

						$this->HTML .= "</B></BIG>\n";

						$this->HTML .= '<A HREF="/' . $mycommit->category . '/">';
						$this->HTML .= $mycommit->category. "</A>";
						$this->HTML .= '&nbsp;';

						$this->HTML .= ' ' . freshports_Commit_Link($mycommit->message_id) . "\n";

						// indicate if this port has been removed from cvs
						if ($mycommit->status == "D") {
							$this->HTML .= " " . freshports_Deleted_Icon_Link() . "\n";
						}

						// indicate if this port needs refreshing from CVS
						if ($mycommit->needs_refresh) {
							$this->HTML .= " " . freshports_Refresh_Icon_Link() . "\n";
						}
						if ($mycommit->date_added > Time() - 3600 * 24 * $this->DaysMarkedAsNew) {
							$MarkedAsNew = "Y";
							$this->HTML .= freshports_New_Icon() . "\n";
						}

						if ($mycommit->forbidden) {
							$this->HTML .= ' ' . freshports_Forbidden_Icon_Link() . "\n";
						}

						if ($mycommit->broken) {
							$this->HTML .= ' '. freshports_Broken_Icon_Link() . "\n";
						}

						if ($mycommit->deprecated) {
							$this->HTML .= ' '. freshports_Deprecated_Icon_Link() . "\n";
						}

						if ($mycommit->expiration_date) {
							if (date('Y-m-d') >= $mycommit->expiration_date) {
								$this->HTML .= freshports_Expired_Icon_Link($mycommit->expiration_date) . "\n";
							} else {
								$this->HTML .= freshports_Expiration_Icon_Link($mycommit->expiration_date) . "\n";
							}
						}

						if ($mycommit->ignore) {
							$this->HTML .= ' '. freshports_Ignore_Icon_Link() . "\n";
						}

						$this->HTML .= freshports_Commit_Link_Port($mycommit->message_id, $mycommit->category, $mycommit->port);
						$this->HTML .= "&nbsp;";

						if ($mycommit->vulnerable_current) {
							$this->HTML .= '&nbsp;' . freshports_VuXML_Icon() . '&nbsp;';
						} else {
							if ($mycommit->vulnerable_past) {
								$this->HTML .= '&nbsp;' . freshports_VuXML_Icon_Faded() . '&nbsp;';
							}
						}

						if ($mycommit->restricted) {
							$this->HTML .= freshports_Restricted_Icon_Link($mycommit->restricted) . '&nbsp;';
						}

						if ($mycommit->no_cdrom) {
							$this->HTML .= freshports_No_CDROM_Icon_Link($mycommit->no_cdrom) . '&nbsp;';
						}

						if ($mycommit->is_interactive) {
							$this->HTML .= freshports_Is_Interactive_Icon_Link($mycommit->is_interactive) . '&nbsp;';
						}

					} else {
						# This is a non-port element... 
						$this->HTML .= $mycommit->revision_name . ' ';
						$this->HTML .= '<big><B>';
						$PathName = preg_replace('|^/?ports/|', '', $mycommit->pathname);
						if ($PathName != $mycommit->pathname) {
							$this->HTML .= '<a href="/' . $PathName . '">' . $PathName . '</a>';
							$this->HTML .= "</B></BIG>\n";
						} else {
							$this->HTML .= '<a href="' . FRESHPORTS_FREEBSD_CVS_URL . $PathName . '#rev' . $mycommit->revision_name . '">' . $PathName . '</a>';
							$this->HTML .= "</B></BIG>\n";
						}
					}
					$this->HTML .= htmlspecialchars($mycommit->short_description) . "\n";
				}

				$j++;
			} // end while

			if (($NumberOfPortsInThisCommit > $MaxNumberPortsToShow) && !$this->ShowAllPorts) {
				$this->HTML .= '<BR>' . freshports_MorePortsToShow($mycommit->message_id, $NumberOfPortsInThisCommit, $MaxNumberPortsToShow);
			}
			$i = $j - 1;

			$this->HTML .= "\n<BLOCKQUOTE>";

			$this->HTML .= freshports_PortDescriptionPrint($mycommit->commit_description, $mycommit->encoding_losses, $freshports_CommitMsgMaxNumOfLinesToShow, freshports_MoreCommitMsgToShow($mycommit->message_id, $freshports_CommitMsgMaxNumOfLinesToShow));

			$this->HTML .= "\n</BLOCKQUOTE>\n</TD></TR>\n\n\n";
		}
		
		return $this->HTML;
	}
}
?>
