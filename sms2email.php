#!/usr/bin/php
<?php
require_once 'common.php';
require_once 'process_mailqueue.php';

// Process SMS
function sms2email(){
	$result = mysql_query("select * from inbox where processed = 0 limit ".SMSPROCESS_LIMIT);
	while ($row = mysql_fetch_assoc($result)){
		$sender = preg_replace('/^\+'.COUNTRYCODE.'/','',$row['number']);
		$msg = $row['text'];
		if ( is_valid_subscriber_number($sender) ){
			$from_email = FROM_EMAIL;
			$from_name = FROM_NAME;
			$subject = SMSSUBJECT.(string)$sender;
			$to_name = "";
			list($to_email,$body) = parse_email($msg);
			if ( (empty($to_email)) || (!preg_match(EMAIL_REGEX,$to_email)) ){
				logmsg("Invalid destination email address from ".$sender);
			} else {
				$body_plain = $body;
				$body_html = nl2br($body);
				insert_to_mailqueue($from_email,$from_name,$subject,$to_email,$to_name,$body_plain,$body_html);
				$rowid=intval($row['id']);
				mysql_query('UPDATE inbox set processed = 1 WHERE id = '.$rowid);				
				if(!empty($from_name))
					logmsg("From: ".$sender." Added ".$from_name."<".$from_email."> to queue");
				else
					logmsg("From: ".$sender." Added ".$from_email." to queue");
			}
		} else {
			logmsg("Unauthorized Subscriber Number: " + $sender);
		}
	}
	mysql_free_result($result);
}

sms2email();
process_mailqueue();

?>
