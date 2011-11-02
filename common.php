<?php
//// Common Functions - INCLUDE ////

//******* You may modify these values ********//

// DATABASE DETAILS //
define("HOST","localhost");
define("DB_NAME","smsgw");
define("DB_USER","sms-user");
define("DB_PW","s3cr37passw0rd");

// EMAIL INFORMATION //
define("FROM_EMAIL","sms@example.org");
define("FROM_NAME","SMS Gateway");
define("SMSSUBJECT","SMS2Email From ");
define("MAX_EMAILS_PER_BATCH",10);

// INCOMING EMAIL/SMTP AUTHENTICATION DETAILS //
define("SMTP_SERVER",'mail.example.org');
define("EMAIL_USERNAME",'sms@example.org');
define("EMAIL_PASSWORD",'emailpasswordhere');

// REGULAR EXPRESSION TO MATCH PHONE NUMBERS //
define("PHONE_REGEX",'/^[0-9]{10}/');

// INTERNATIONAL COUNTRY CALLING CODE
define("COUNTRYCODE","379");





// ***************************************//
// WARNING: Do not modify below this line //
// ***************************************//

define("PIDFILE","sms2email.pid");
define("SMSPROCESS_LIMIT", 5);
define("SMSLIMIT",160);
define("EMAILPROCESS_LIMIT", 5);
define("SMSPREFIX","From: ");
define("EMAIL_REGEX",'/^([a-zA-Z0-9_\-.]+)@(([[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.)|(([a-zA-Z0-9\-]+.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(]?)$/');
define("EMAILSTRING_REGEX",'/^(.*)[\[\<](([a-zA-Z0-9_\-.]+)@(([[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.)|(([a-zA-Z0-9\-]+.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(]?))[\]\>]$/');

$db = mysql_connect(HOST,DB_USER,DB_PW) or die("Connect Error");
mysql_select_db(DB_NAME);

// Log Messages
function logmsg($msg) {
	$message = mysql_real_escape_string($msg);
	mysql_query('insert into log (message) values("'.$message.'")') or die(mysql_error());
}

// Get User ID from Number
function get_id_from_number($number) {
	if (preg_match(PHONE_REGEX,$number)) {
		$result = mysql_query("select userinfo_id from phone_list where number = ".$number." limit 1");
		if(mysql_num_rows($result)) {
			$row = mysql_fetch_assoc($result);
			mysql_free_result($result);
			return intval($row['userinfo_id']);
		} else {
			mysql_free_result($result);
			return -1;
		}
	} else { 
		return -1;
	}
}

// Get User ID from Email
function get_id_from_email($email) {
	if (preg_match(EMAIL_REGEX,$email)) {
		$result = mysql_query('select userinfo_id from email_list where email = "'.$email.'" limit 1');
		if(mysql_num_rows($result)) {
			$row = mysql_fetch_assoc($result);
			mysql_free_result($result);
			return intval($row['userinfo_id']);
		} else {
			mysql_free_result($result);
			return -1;
		}
	} else {
		return -1;
	}
}

// Get Service ID from User ID
function get_service_id($userid) {
	$userid = intval($userid);
	$result = mysql_query("select service_type_id from userinfo where id = ".$userid." limit 1");
	if(mysql_num_rows($result)) {
		$row = mysql_fetch_assoc($result);
		mysql_free_result($result);
		return intval($row['service_type_id']);
	} else {
		mysql_free_result($result);
		return -1;
	}
}

// Get Service Name from Service ID
function get_service_name($serviceid) {
	$serviceid = intval($serviceid);
	$result = mysql_query("select name from service_type where id = ".$serviceid." limit 1");
	if(mysql_num_rows($result)) {
		$row = mysql_fetch_assoc($result);
		mysql_free_result($result);
		return intval($row['name']);
	} else {
		mysql_free_result($result);
		return "";
	}
}

// Validate Phone Number
function is_authorized_number($number){
	if (preg_match(PHONE_REGEX,$number)) {
		$result = mysql_query("select userinfo_id from phone_list where number = ".$number);
		if(1==mysql_num_rows($result)){
			$row = mysql_fetch_assoc($result);
			mysql_free_result($result);
			return intval($row['userinfo_id']);
		} else return 0;
	} else {
		return -1;
	}
}

// Validate Email Address
function is_authorized_email($email){
	if (preg_match(EMAIL_REGEX,$email)) {
		$result = mysql_query('select userinfo_id from email_list where email = "'.$email.'"');
		if(1==mysql_num_rows($result)){
			$row = mysql_fetch_assoc($result);
			mysql_free_result($result);
			return intval($row['userinfo_id']);
		} else return 0;
	} else {
		return -1;
	}
}

// Check Validity of User
function is_authorized_user($userid) {
	$userid = intval($userid);
	$result = mysql_query('select id from userinfo where id = '.$userid.' and authorized = 1 limit 1');
	// TODO - Is the validity enough?
	if(mysql_num_rows($result)) {
		mysql_free_result($result);
		return true;
	} else {
		mysql_free_result($result);
		return false;
	}
}

///////////////////////////////////////////////////////////////////////////////
/* Services Types
* - sms2email
* - email2sms
* - two-way
*/

// Check Email Subscription Validity
function is_valid_subscriber_email($email) {
	if(is_authorized_email($email)) {
		$userid = get_id_from_email($email);
		if(($userid) && is_authorized_user($userid)){
			$serviceid = get_service_id($userid);
			if($serviceid){
				$servicename = get_service_name($serviceid);
				if(($servicename == 'email2sms')||($servicename=='two-way'))
					return true;
				else
					return false;
			} else return false;
		} else return false;
	} else return false;
}

// Check Number Subscription Validity
function is_valid_subscriber_number($number) {
	if(is_authorized_number($number)){
		$userid = get_id_from_number($number);
		if(($userid) && is_authorized_user($userid)){
			$serviceid = get_service_id($userid);
			if($serviceid){
				$servicename = get_service_name($serviceid);
				if(($servicename == 'sms2email')||($servicename=='two-way'))
					return true;
				else
					return false;
			} else return false;
		} else return false;
	} else return false;
}
///////////////////////////////////////////////////////////////////////////////


// Extract email headers and body
function parse_email($msg){
	$to_email = "";
	$body = "";
	$msg = trim($msg);
		
	$keywords = preg_split("/[\s]+/", $msg);
	if (preg_match(EMAIL_REGEX,$keywords[0])) {
		$to_email = $keywords[0]; 
	}
	$body = trim(substr($msg,strlen($to_email),strlen($msg)));
	
	return array($to_email,$body);	
}

// Get Number from Email Address
function get_number($email){
	$keywords = preg_split("/[@]/", trim($email));
	return intval($keywords[0]);
}

// Get Name and Email from Email String
function split_email_string($emailstring){
	$emailstring = trim($emailstring);
	if (preg_match(EMAILSTRING_REGEX, $emailstring, $keywords)) {
		if (preg_match(EMAIL_REGEX,$keywords[2]))
			return array($keywords[1],$keywords[2]); 
		else
			return array('',$emailstring);
	} else
		return array('',$emailstring);
}

// Split text to 160 chunks
function split_chunk($text,&$chunk){
        if(strlen($text) <= SMSLIMIT) {
                array_push($chunk,$text);
                return $chunk;
        } else {
                array_push($chunk,substr($text,0,160));
                $remaining_text = substr($text,160,strlen($text));
                split_chunk($remaining_text,$chunk);
        }
}

// Insert Email for Processing
function insert_to_process_email($from_email,$from_name,$subject,$to_email,$to_name,$body) {
	$from_email = mysql_real_escape_string($from_email);
	$from_name = mysql_real_escape_string($from_name);
	$subject = mysql_real_escape_string($subject);
	$to_email = mysql_real_escape_string($to_email);
	$to_name = mysql_real_escape_string($to_name);
	$body = mysql_real_escape_string($body);
	
	mysql_query('INSERT INTO process_email (from_email,from_name,subject,to_email,to_name,body) VALUES ( "'.$from_email.'", "'.$from_name.'", "'.$subject.'", "'.$to_email.'", "'.$to_name.'", "'.$body.'" )');
	
}

// Mail Queue
function insert_to_mailqueue($from_email,$from_name,$subject,$to_email,$to_name,$body_plain,$body_html){
	$from_email = mysql_real_escape_string($from_email);
	$from_name = mysql_real_escape_string($from_name);
	$subject = mysql_real_escape_string($subject);
	$to_email = mysql_real_escape_string($to_email);
	$to_name = mysql_real_escape_string($to_name);
	$body_plain = mysql_real_escape_string($body_plain);
	$body_html = mysql_real_escape_string($body_html);
	
	mysql_query('INSERT INTO mailqueue (from_email,from_name,subject,to_email,to_name,body_plain,body_html) VALUES ( "'.$from_email.'", "'.$from_name.'", "'.$subject.'", "'.$to_email.'", "'.$to_name.'", "'.$body_plain.'", "'.$body_html.'"  )');
}

// SMS Queue
function insert_to_smsqueue($number,$text){
	$number = mysql_real_escape_string(intval($number));
	$text = mysql_real_escape_string($text);
	mysql_query('INSERT INTO outbox (number,text) VALUES( '.$number.', "'.$text.'" )');
}

//// End of Common Functions ////
?>
