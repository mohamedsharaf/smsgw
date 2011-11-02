<?php
require_once 'common.php';

function check_inbox(){
	$mbox = imap_open('{'.SMTP_SERVER.':143/novalidate-cert}INBOX', EMAIL_USERNAME, EMAIL_PASSWORD);
	$total = imap_num_msg($mbox);
	if($total) {
		for($n=1;$n<=$total;$n++) {
			$header = imap_header($mbox,$n);
			$fromaddress = $header->fromaddress;
			$toaddress = $header->toaddress;
			$subject = $header->subject;
			$message_id = $header->message_id;
			$date    = date('Y-m-d H:i:s', $header->udate);
			$body = "";
			
			$st = imap_fetchstructure($mbox,$n);
			if (!empty($st->parts)) {
				for ($i=0,$j=count($st->parts);$i<$j;$i++) {
					$parts = $st->parts[$i];
					if ($parts->subtype == 'PLAIN') {
						$body = imap_fetchbody($mbox,$n,$i+1); 
						if ($parts->encoding == 4)
							$body = quoted_printable_decode($body);
						elseif ($parts->encoding == 3)
							$body = base64_decode($body);
					}
				}
			} else {
				$body = imap_body($mbox,$n);
				if ($st->encoding == 4)
					$body = quoted_printable_decode($body);
				elseif ($st->encoding == 3)
					$body = base64_decode($body);
			}

			list($from_name,$from_email) = split_email_string($fromaddress);
			list($to_name,$to_email) = split_email_string($toaddress);
			
			/* echo "Message No. ".(string)$n."\n";
			echo "-----------------\n";
			echo 'From: ' .$from_name. '='.$from_email.'=' ."\n";
			echo 'To: ' .$to_name. '='.$to_email.'=' ."\n";
			echo "Message ID: ".$message_id."\n";
			echo 'Subject: ' . $subject ."\n";
			echo 'Date: ' . $date."\n";
			echo $body;
			echo "\n"; */
			
			if (is_valid_subscriber_email($from_email)){
				// TODO - Add check for "To Email Address"
				insert_to_process_email($from_email,$from_name,$subject,$to_email,$to_name,$body);
			} else {
				log("Error Inserting Unauthorized Email to Process Email: ".$from_email);
			}
			
			imap_delete($mbox, $n);
			
		}
	}
	
	imap_expunge($mbox);
	imap_close($mbox);
}

?>
