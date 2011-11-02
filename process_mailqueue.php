<?php
require_once 'common.php';

// Process Mail Queue
function process_mailqueue() {
	require_once("phpmailer/class.phpmailer.php");
	
	// query email_queue for records where success = 0
	$result = mysql_query("SELECT * FROM mailqueue WHERE success = 0 AND attempts <= maxattempts LIMIT ".MAX_EMAILS_PER_BATCH);
	
	if (!$result) {
	        logmsg('Could not query the database: '.mysql_error());
	        exit;
	}
	
	// check if records found
	if (mysql_num_rows( $result )) {
	// prepare mailer
	// loop through records to send emails
	        while ($queued_mail = mysql_fetch_array($result, MYSQL_ASSOC)) {
	        // send email
	                $fromemail =  $queued_mail["from_email"];
	                $fromname =   $queued_mail["from_name"];
	                $subject =    $queued_mail["subject"];
	                $toemail =    $queued_mail["to_email"];
	                $toname =     $queued_mail["to_name"];
	                $bodyplain =  $queued_mail["body_plain"];
	                $bodyhtml =   $queued_mail["body_html"];
	
	                 unset($mail);
	                 $mail = new PHPMailer();
	                 $mail->IsSMTP();
	                 $mail->Host = SMTP_SERVER;	                 
	                 $mail->SMTPAuth = true;
	                 $mail->Username = EMAIL_USERNAME;
	                 $mail->Password = EMAIL_PASSWORD;
	                 $mail->WordWrap = 70;
	                 $mail->IsHTML(true);
	                 $mail->Subject = "$subject";
	                 $mail->From = "$fromemail";
	                 $mail->FromName = "$fromname";
	                 $mail->AddAddress("$toemail","$toname");
	                 $mail->Body    = "$bodyhtml";
	                 $mail->AltBody = "$bodyplain";
	
	                if (!$mail->Send()) {
	                // if not successful, update attempts
	                        mysql_query("UPDATE mailqueue SET attempts = attempts+1 WHERE id = ".$queued_mail['id']);
	                        logmsg('ERROR: '.$mail->getMessage());
	                } else {
	                // if successful, update attempts, success
	                        mysql_query("UPDATE mailqueue SET attempts = attempts+1, success = 1 WHERE id = ".$queued_mail['id']);
	                        logmsg('Message successfully sent to '.$toemail);
	                }
	
	                $mail->ClearAddresses();
	                $mail->ClearAllRecipients();
	                $mail->ClearAttachments();
	
	                unset($mail);
	
	        } // end while (loop through records and sending emails)
	} // no rows so quit
	
	// cleanup
	// TODO - mysql_query("DELETE from mailqueue WHERE success = 1");
	
	// release resources
	mysql_free_result($result);
}

?>
