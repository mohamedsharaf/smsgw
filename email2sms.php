#!/usr/bin/php
<?php
require_once 'common.php';
require_once 'check_inbox.php';

// Process Email
function email2sms(){
	$result = mysql_query("select * from process_email WHERE processed = 0 limit ".EMAILPROCESS_LIMIT) or die (mysql_error());
	while ($row = mysql_fetch_assoc($result)){
		$from_email = $row['from_email'];
		$from_name = $row['from_name'];
		$number = get_number($row['to_email']);
		if ( is_valid_subscriber_email($from_email)){
			$body = trim(preg_replace('/[^(\x20-\x7F)]*/','', $row['body']));
			$text = SMSPREFIX . $from_email . "\n" . $body;
			if(preg_match(PHONE_REGEX,$number)){
				$chunk=array();
				split_chunk($text,$chunk);
				foreach($chunk as $value){
					insert_to_smsqueue($number,$value);
				}
				$rowid=intval($row['id']);
				mysql_query('UPDATE process_email set processed = 1 WHERE id = '.$rowid);
				if(!empty($from_name))
					logmsg("From: ".$from_name."<".$from_email."> Added ".$number." to queue");
				else
					logmsg("From: ".$from_email." Added ".$number." to queue");
			} else {
				logmsg("Sender trying to SMS to a blocked number: ".$number);
			}
		} else {
			logmsg("Unauthorized Subscriber Email: ".$row['from_email']);
		}
	}
	mysql_free_result($result);
}

check_inbox();
email2sms();

?>
