<?php  

	class Mailconfig {

        protected $host;
        protected $charset;
        protected $smtp_auth;
        protected $username;
        protected $password;
        protected $from;
        protected $fromname;
        protected $wordwrap;

        function Mailconfig() {

            $this->host = '172.23.50.25';
            $this->charset = 'UTF-8';
            $this->smtp_auth = true;
            $this->username = 'administraciondcai';
            $this->password = '$dcai2015$';
            $this->from = "no-reply@muniguate.com";
            $this->fromname = "noreply";
            $this->wordwrap = 50;

        }

    }

?>