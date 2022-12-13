<?php  

	include $_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/mail_config.php';

	require $_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/PHPMailerAutoload.php';

	class Mail extends Mailconfig{

		protected $mail;

		function __construct(){

			$mail_config = new Mailconfig();

			$this->mail = new PHPMailer();
			$this->mail->CharSet = 'UTF-8';
			$this->mail->isSMTP();
			$this->mail->Host = 'smtp.gmail.com';
			$this->mail->SMTPAuth = true;
			$this->mail->SMTPSecure = 'ssl';
			$this->mail->Port = 465;
			$this->mail->Username = 'ave.muniguate@gmail.com';
			$this->mail->Password = 'avemuniguate2019';
			$this->mail->From = 'no-reply@muniguate.com';
			$this->mail->FromName = 'AVE Personalizado - noreply';
			$this->mail->WordWrap = $mail_config->wordwrap;
			$this->mail->addReplyTo($mail_config->from);
			$this->mail->isHTML(true);

		}
		
		function send_mail($email, $subject,$body){

			$this->mail->addAddress($email);
			$this->mail->Body = html_entity_decode($body);
			$this->mail->Subject = $subject;
			
			if(!$this->mail->send()) {

        		$error_message = 'Message could not be sent.<br>Mailer Error: ' . $this->mail->ErrorInfo;
        		return array('id_send_email' => 0, 'txt_mail' => $error_message);

		    } else {

		        $error_message = 'Message has been sent';
		        return array('id_send_email' => 1, 'txt_mail' => $error_message);

			}
			
		}

	}

?>