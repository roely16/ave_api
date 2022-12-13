<?php

	//include $_SERVER['DOCUMENT_ROOT'] . '/ave_api/database/db.php';

	/* Envio de notificaciones */
	include $_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/GCM.php';
	include $_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/mail.php';
	//error_reporting(E_ALL);

	class Api extends Rest{

		public $dbConn;

		public function __construct(){

			parent::__construct();

			$db = new Db();
			$this->dbConn = $db->connect();

		}

		public function generateToken(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$email = $this->validateParameter('email', $this->param['email'], STRING);

			$password = $this->validateParameter('password', $this->param['pass'], STRING);

			try {

				$query = "	SELECT *
							FROM CAT_PERSONAS";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$personas = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$personas [] = $data;
				}

				/*
				$stmt = $this->dbConn->prepare("SELECT * FROM users WHERE email = :email AND password = :pass");

				$stmt->bindParam(":email", $email);
				$stmt->bindParam(":pass", $password);

				$stmt->execute();

				$user = $stmt->fetch(PDO::FETCH_ASSOC);

				if (!is_array($user)) {
					$this->returnResponse(INVALID_USER_PASS, "Email or Password is incorrect");
				}

				if ($user['active'] == 0) {
					$this->returnResponse(USER_NOT_ACTIVE, "User is not activated.  Please contact to admin");
				}

				$payload = array(
					'iat' 	=> 	time(),
					'iss'	=>	'localhost',
					'exp'	=>	time() + (60),
					'userId'	=>	$user['id'],
					'userEmail'	=>	$user['email']
				);
				*/
				$token = JWT::encode($payload, SECRET_KEY);

				$data = array(
					'token'		=>	$token
				);

				/* Data Encriptada */
				$this->returnResponse(SUCCESS_RESPONSE, $personas);

				/* Data Descencriptada */
				// $algorithms = array('HS256');
				// $payload = JWT::decode($token, 'test123', $algorithms);
				// print_r($payload);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function login(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$telefono = $this->validateParameter('phone', $this->param['phone'], STRING);

			$password = $this->validateParameter('password', $this->param['pass'], STRING);

			$token = $this->param['token'];

			try {

				$query = "	SELECT *
							FROM LOC_PERSONA
							WHERE TELEFONO = '$telefono' AND DESENCRIPTAR(PASSWORD) = '$password'";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$usuario = oci_fetch_array($stid, OCI_ASSOC);

				if (!is_array($usuario)) {

					$this->throwError(INVALID_USER_PASS, "Número de teléfono o contraseña incorrectos");


				}else{

					/* Actualizar token del dispositivo */
					$id_persona = $usuario["ID_PERSONA"];
					$query = "UPDATE LOC_PERSONA SET TOKEN = '$token', UPDATED_AT = SYSDATE WHERE ID_PERSONA = $id_persona";

					$stid = oci_parse($this->dbConn, $query);
					oci_execute($stid);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $usuario);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function register(){

			$name = $this->validateParameter('name', $this->param['name'], STRING);

			$phone = $this->validateParameter('phone', $this->param['phone'], STRING);

			$email = $this->validateParameter('email', $this->param['email'], STRING);

			$pass = $this->validateParameter('pass', $this->param['pass'], STRING);
			$confirm_pass = $this->validateParameter('confirm_pass', $this->param['confirm_pass'], STRING);

			// $organizacion = $this->validateParameter('organizacion', $this->param['organizacion'], STRING);

			$token = 1;

			try {

				$db = new Db();
				$this->dbConn = $db->connect();

				/*Validar que la clave sea la misma */
				if ($pass != $confirm_pass) {
					
					$this->throwError(GENERAL_ERROR, "Ambas contraseñas deben de coincidir");

				}

				/* Validar que esta persona no este registrada */
				$query = "SELECT * FROM LOC_PERSONA WHERE TELEFONO = '$phone'";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);

				if ($result) {
					
					$this->throwError(GENERAL_ERROR, "Ya existe una persona registrada con este número de teléfono, inicie sesión o bien intente con otro número");

				}

				/* Crear la organizacion */
				// $query = "INSERT INTO LOC_ORGANIZACION (NOMBRE, CREATED_AT) VALUES ('$organizacion', SYSDATE)";
				// $stid = oci_parse($this->dbConn, $query);
				// oci_execute($stid);

				// /* Seleccionar la ultima creada */
				// $query = "SELECT ID_ORGANIZACION FROM LOC_ORGANIZACION WHERE ROWNUM = 1 ORDER BY ID_ORGANIZACION DESC";
				// $stid = oci_parse($this->dbConn, $query);
				// oci_execute($stid);

				// $result = oci_fetch_array($stid, OCI_ASSOC);
				// $id_organizacion = $result["ID_ORGANIZACION"];

				/* Registrar a la persona */
				$query = "INSERT INTO LOC_PERSONA (NOMBRE, TELEFONO, EMAIL, PASSWORD, TOKEN, CREATED_AT, ADMINISTRADOR) VALUES ('$name', '$phone', '$email', ENCRIPTAR('$pass'), '$token', SYSDATE, 'S')";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$this->returnResponse(SUCCESS_RESPONSE, $result);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}



		}

		public function sendPassword(){

			$phone = $this->validateParameter('phone', $this->param['phone'], STRING);	

			try {
				
				$query = "SELECT EMAIL, DESENCRIPTAR(PASSWORD) AS PASSWORD FROM LOC_PERSONA WHERE TELEFONO = '$phone'";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);

				if (!array_key_exists("EMAIL", $result)) {
					
					/* El usuario no tiene un correo registrado */

					$this->throwError(GENERAL_ERROR, "El número de teléfono no existe o no tiene un correo electrónico registrado");

				}

				$password = $result["PASSWORD"];
				$email = $result["EMAIL"];

				$text = "AVE Personalizado le envia la contraseña asociada al número de telefono <strong>$phone</strong>:<br>
				<ul>
				<li>Contraseña: <strong>$password</strong></li>
				</ul><br>";

				$mail = new Mail();

				$response = $mail->send_mail($email, 'Recuperación de contraseña', $text);

				$this->returnResponse(SUCCESS_RESPONSE, $response);

			} catch (\Throwable $th) {
				
				$this->throwError(JWT_PROCESSING_ERROR, $th->getMessage());

			}

		}

		public function aceptarTerminos(){

			$id_usuario = $this->validateParameter('id_usuario', $this->param['id_usuario'], STRING);

			try {
				
				$query = "UPDATE LOC_PERSONA SET TERMINOS = 'S' WHERE ID_PERSONA = $id_usuario";
				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "Error general";

					$this->throwError($err["code"], $str_error);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $id_usuario);

			} catch (\Throwable $th) {
				


			}

		}

		/* My Account */

		public function userInfo(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);
			$id_organizacion = $this->param['id_organizacion'];

			try {

				if ($id_organizacion) {
				
					$query = "	SELECT LOC_PERSONA.ID_PERSONA, LOC_PERSONA.NOMBRE, LOC_PERSONA.EMAIL, LOC_PERSONA.PASSWORD, LOC_PERSONA.TOKEN, LOC_PERSONA.CREATED_AT, LOC_PERSONA.UPDATED_AT, LOC_PERSONA.ID_ORGANIZACION, LOC_PERSONA_ORGANIZACION.ADMINISTRADOR, LOC_PERSONA.TELEFONO, TO_CHAR(LOC_PERSONA.AVATAR) AS AVATAR
								FROM LOC_PERSONA
								INNER JOIN LOC_PERSONA_ORGANIZACION
								ON LOC_PERSONA.ID_PERSONA = LOC_PERSONA_ORGANIZACION.ID_PERSONA
								WHERE LOC_PERSONA.ID_PERSONA = '$userID'
								AND LOC_PERSONA_ORGANIZACION.ID_ORGANIZACION = '$id_organizacion'";

				}else{

					$query = "	SELECT LOC_PERSONA.ID_PERSONA, LOC_PERSONA.NOMBRE, LOC_PERSONA.EMAIL, LOC_PERSONA.PASSWORD, LOC_PERSONA.TOKEN, LOC_PERSONA.CREATED_AT, LOC_PERSONA.UPDATED_AT, LOC_PERSONA.ID_ORGANIZACION, LOC_PERSONA.ADMINISTRADOR, LOC_PERSONA.TELEFONO, TO_CHAR(LOC_PERSONA.AVATAR) AS AVATAR
								FROM LOC_PERSONA
								WHERE LOC_PERSONA.ID_PERSONA = '$userID'";

				}
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$usuario = oci_fetch_array($stid, OCI_ASSOC);

				//$avatar = $usuario["AVATAR"];

				if(!array_key_exists('ADMINISTRADOR', $usuario)){

					$usuario["ADMINISTRADOR"] = '';

				}

				if(array_key_exists('AVATAR', $usuario)){

					//$myfile = fopen($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar, "r") or die("Unable to open file!");

					$avatar = $usuario["AVATAR"];
					$photo = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar);
					$usuario["AVATAR"] = $photo;

				}else{

					$usuario["AVATAR"] = "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";

				}

				$this->returnResponse(SUCCESS_RESPONSE, $usuario);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}


		}

		public function infoIntegranteEquipo(){

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);
			$id_organizacion = $this->param['id_organizacion'];
			$id_persona_edita = $this->validateParameter('id_persona_edita', $this->param['id_persona_edita'], STRING);

			try {

				if ($id_organizacion) {
				
					$query = "	SELECT LOC_PERSONA.ID_PERSONA, LOC_PERSONA.NOMBRE, LOC_PERSONA.EMAIL, LOC_PERSONA.PASSWORD, LOC_PERSONA.TOKEN, LOC_PERSONA.CREATED_AT, LOC_PERSONA.UPDATED_AT, LOC_PERSONA.ID_ORGANIZACION, LOC_PERSONA_ORGANIZACION.ADMINISTRADOR, LOC_PERSONA.TELEFONO, TO_CHAR(LOC_PERSONA.AVATAR) AS AVATAR
								FROM LOC_PERSONA
								INNER JOIN LOC_PERSONA_ORGANIZACION
								ON LOC_PERSONA.ID_PERSONA = LOC_PERSONA_ORGANIZACION.ID_PERSONA
								WHERE LOC_PERSONA.ID_PERSONA = '$userID'
								AND LOC_PERSONA_ORGANIZACION.ID_ORGANIZACION = '$id_organizacion'";

				}else{

					$query = "	SELECT LOC_PERSONA.ID_PERSONA, LOC_PERSONA.NOMBRE, LOC_PERSONA.EMAIL, LOC_PERSONA.PASSWORD, LOC_PERSONA.TOKEN, LOC_PERSONA.CREATED_AT, LOC_PERSONA.UPDATED_AT, LOC_PERSONA.ID_ORGANIZACION, LOC_PERSONA.ADMINISTRADOR, LOC_PERSONA.TELEFONO, TO_CHAR(LOC_PERSONA.AVATAR) AS AVATAR
								FROM LOC_PERSONA
								WHERE LOC_PERSONA.ID_PERSONA = '$userID'";

				}
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$usuario = oci_fetch_array($stid, OCI_ASSOC);

				//$avatar = $usuario["AVATAR"];

				if(!array_key_exists('ADMINISTRADOR', $usuario)){

					$usuario["ADMINISTRADOR"] = '';

				}

				if(array_key_exists('AVATAR', $usuario)){

					//$myfile = fopen($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar, "r") or die("Unable to open file!");

					$avatar = $usuario["AVATAR"];
					$photo = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar);
					$usuario["AVATAR"] = $photo;

				}else{

					$usuario["AVATAR"] = "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";

				}

				/** Validar si el usuario que edita es administrador */
				$query = "SELECT ADMINISTRADOR FROM LOC_PERSONA_ORGANIZACION WHERE ID_PERSONA = $id_persona_edita AND ID_ORGANIZACION = $id_organizacion";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);

				if ($result) {
					
					$usuario["USUARIO_EDITA_ADMINISTRADOR"] = true;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $usuario);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function editUserInfo(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			$userName = $this->validateParameter('userName', $this->param['userName'], STRING);

			$userPhone = $this->validateParameter('userPhone', $this->param['userPhone'], STRING);

			$userMail = $this->validateParameter('userMail', $this->param['userMail'], STRING);

			$avatar = $this->param['avatar'];

			try {

				/** Verificar si tiene avatar */

				$query = "SELECT AVATAR FROM LOC_PERSONA WHERE ID_PERSONA = $userID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$persona = oci_fetch_array($stid, OCI_ASSOC);

				if(!$persona){

					/** Persona sin avatar */
					$key = '';
					$keys = array_merge(range(0, 9), range('a', 'z'));

					for ($i = 0; $i < 50; $i++) {

						$key .= $keys[array_rand($keys)];

					}


				}else{

					/** Persona con avatar */
					$key = $persona["AVATAR"];

				}

				$myfile = fopen($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$key, "w") or die("Unable to open file!");
				fwrite($myfile, $avatar);
				fclose($myfile);

				$query = "UPDATE LOC_PERSONA SET NOMBRE = '$userName', TELEFONO = '$userPhone', EMAIL = '$userMail', AVATAR = '$key' WHERE ID_PERSONA = '$userID'";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					if ($err["code"] == 1) {

						$str_error = "El número de teléfono ya esta registrado";

					}else{

						$str_error = "No se ha podido actualizar";

					}

					$this->throwError($err["code"], $str_error);

				}else{

					$this->returnResponse(SUCCESS_RESPONSE, $persona);

				}

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function checkAdmin(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			try {

				$query = "SELECT ADMINISTRADOR FROM LOC_PERSONA WHERE ID_PERSONA = $userID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);

				$user_admin = false;

				if ($result) {

					$user_admin = true;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $user_admin);

			} catch (\Exception $e) {

			}


		}

		public function volverAdmin(){

			$phone = $this->validateParameter('phone', $this->param['phone'], STRING);

			try {
			
				$query = "UPDATE LOC_PERSONA SET ADMINISTRADOR = 'S' WHERE TELEFONO = '$phone'";
				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "Error al actualizar";

					$this->throwError($err["code"], $str_error);

				}

				$this->returnResponse(SUCCESS_RESPONSE, 'Registro actualizado exitosamente');

			} catch (\Throwable $th) {
				


			}

		}

		public function changePassword(){

			$id_usuario = $this->validateParameter('id_usuario', $this->param['id_usuario'], STRING);
			$actual_password = $this->validateParameter('actual_password', $this->param['actual_password'], STRING);
			$nuevo_password = $this->validateParameter('nuevo_password', $this->param['nuevo_password'], STRING);

			try {
				
				//Validar que el password actual sea correcto
				$query = "	SELECT *
							FROM LOC_PERSONA 
							WHERE ID_PERSONA = $id_usuario
							AND DESENCRIPTAR(PASSWORD) = '$actual_password'";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);

				if (!$result) {
					
					$err = oci_error($stid);

					$str_error = "La contraseña actual es incorrecta";

					$this->throwError(GENERAL_ERROR, $str_error);

				}
				
				//Realizar la actualizacion de la clave
				$query = "UPDATE LOC_PERSONA SET PASSWORD = ENCRIPTAR('$nuevo_password') WHERE ID_PERSONA = $id_usuario";
				
				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "Error al cambiar la contraseña";

					$this->throwError($err["code"], $str_error);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $id_usuario);

			} catch (\Throwable $th) {
				


			}

		}

		/* Protocolos */

		public function createProtocol(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			$protocolName = $this->validateParameter('protocolName', $this->param['protocolName'], STRING);

			$protocolDescription = $this->param['protocolDescription'];

			/* Obtener el ID de la organizacion a la que pertenece */
			$query = "SELECT ID_ORGANIZACION FROM LOC_PERSONA WHERE ID_PERSONA = '$userID'";

			$stid = oci_parse($this->dbConn, $query);
			oci_execute($stid);

			$persona = oci_fetch_array($stid, OCI_ASSOC);
			$id_organizacion = $persona["ID_ORGANIZACION"];

			/* Creación de registro */

			try {

				$query = "INSERT INTO LOC_PROTOCOLO (NOMBRE, DESCRIPCION, ID_ORGANIZACION, ESTADO) VALUES ('$protocolName', '$protocolDescription', '$id_organizacion', 'A')";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido registrar";

					$this->throwError($err["code"], $str_error);

				}else{

					$this->returnResponse(SUCCESS_RESPONSE, $userID);

				}

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function createProtocol2(){

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);
			$protocolName = $this->validateParameter('protocolName', $this->param['protocolName'], STRING);
			$protocolDescription = $this->param['protocolDescription'];
			$id_organizacion =  $this->validateParameter('id_organizacion', $this->param['id_organizacion'], STRING);

			try {

				$query = "INSERT INTO LOC_PROTOCOLO (NOMBRE, DESCRIPCION, ID_ORGANIZACION, ESTADO) VALUES ('$protocolName', '$protocolDescription', '$id_organizacion', 'A')";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido registrar";

					$this->throwError($err["code"], $str_error);

				}else{

					$this->returnResponse(SUCCESS_RESPONSE, $userID);

				}

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function getProtocols(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			/* Obtener el ID de la organizacion a la que pertenece */
			$query = "SELECT ID_ORGANIZACION FROM LOC_PERSONA WHERE ID_PERSONA = '$userID'";

			$stid = oci_parse($this->dbConn, $query);
			oci_execute($stid);

			$persona = oci_fetch_array($stid, OCI_ASSOC);
			$id_organizacion = $persona["ID_ORGANIZACION"];

			try {

				$query = "SELECT * FROM LOC_PROTOCOLO WHERE ID_ORGANIZACION = '$id_organizacion' ORDER BY ID_PROTOCOLO ASC";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$protocolos = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$protocolos [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $protocolos);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function getDetailsProtocol(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);

			try {

				$query = "SELECT * FROM LOC_PROTOCOLO WHERE ID_PROTOCOLO = '$protocolID'";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$protocolo = oci_fetch_array($stid,OCI_ASSOC);

				$this->returnResponse(SUCCESS_RESPONSE, $protocolo);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function getDetailsProtocol2(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);
			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			try {

				// $query = "SELECT * FROM LOC_PROTOCOLO WHERE ID_PROTOCOLO = '$protocolID'";

				$query = "	SELECT T1.*, T3.ADMINISTRADOR 
							FROM LOC_PROTOCOLO T1
							INNER JOIN LOC_ORGANIZACION T2
							ON T1.ID_ORGANIZACION = T2.ID_ORGANIZACION 
							INNER JOIN LOC_PERSONA_ORGANIZACION T3 
							ON T2.ID_ORGANIZACION = T3.ID_ORGANIZACION
							WHERE T1.ID_PROTOCOLO = '$protocolID'
							AND T3.ID_PERSONA = '$userID'";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$protocolo = oci_fetch_array($stid,OCI_ASSOC);

				$query = '	SELECT T1.ID_ORGANIZACION AS "id", T2.NOMBRE as "name"
							FROM LOC_PERSONA_ORGANIZACION T1
							INNER JOIN LOC_ORGANIZACION T2
							ON T1.ID_ORGANIZACION = T2.ID_ORGANIZACION
							WHERE T1.ID_PERSONA = ' . $userID . 
							'ORDER BY T1.ID_ORGANIZACION DESC';

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$equipos = array();

				$result = 	array(
					"name" => "Equipos",
					"id" => "1",
				);

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
				
					$equipos [] = $data;

				}

				$result["children"] = $equipos;

				$this->returnResponse(SUCCESS_RESPONSE, array($protocolo, $result));

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function editProtocol(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);

			$protocolName = $this->validateParameter('protocolName', $this->param['protocolName'], STRING);

			if (array_key_exists('protocolDescription', $this->param)) {
				$protocolDescription = $this->param['protocolDescription'];
			}else{
				$protocolDescription = '';
			}

			//$protocolDescription = $this->param['protocolDescription'];

			try {

				$query = "UPDATE LOC_PROTOCOLO SET NOMBRE = '$protocolName', DESCRIPCION = '$protocolDescription' WHERE ID_PROTOCOLO = '$protocolID'";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido actualizar";

					$this->throwError($err["code"], $str_error);

				}else{

					$this->returnResponse(SUCCESS_RESPONSE, $protocolID);

				}

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function editProtocol2(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);
			$protocolName = $this->validateParameter('protocolName', $this->param['protocolName'], STRING);

			if (array_key_exists('protocolDescription', $this->param)) {
				$protocolDescription = $this->param['protocolDescription'];
			}else{
				$protocolDescription = '';
			}

			$id_organizacion = $this->validateParameter('id_organizacion', $this->param['id_organizacion'], STRING);

			//$protocolDescription = $this->param['protocolDescription'];

			try {

				$query = "UPDATE LOC_PROTOCOLO SET NOMBRE = '$protocolName', DESCRIPCION = '$protocolDescription', ID_ORGANIZACION = '$id_organizacion' WHERE ID_PROTOCOLO = '$protocolID'";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido actualizar";

					$this->throwError($err["code"], $str_error);

				}else{

					$this->returnResponse(SUCCESS_RESPONSE, $protocolID);

				}

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function updateStateProtocol(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);

			$protocolState = $this->validateParameter('protocolState', $this->param['protocolState'], STRING);

			try {

				$query = "UPDATE LOC_PROTOCOLO SET ESTADO = '$protocolState' WHERE ID_PROTOCOLO = '$protocolID'";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido actualizar";

					$this->throwError($err["code"], $str_error);

				}else{

					$protocol = array( 'protocolID' => $protocolID, 'protocolState' => $protocolState );

					$this->returnResponse(SUCCESS_RESPONSE, $protocol);

				}

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}


		}

		public function searchProtocols(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			$textSearch = $this->param['textSearch'];

			/* Obtener el ID de la organizacion a la que pertenece */
			$query = "SELECT ID_ORGANIZACION FROM LOC_PERSONA WHERE ID_PERSONA = '$userID'";

			$stid = oci_parse($this->dbConn, $query);
			oci_execute($stid);

			$persona = oci_fetch_array($stid, OCI_ASSOC);
			$id_organizacion = $persona["ID_ORGANIZACION"];

			try {

				$query = "	SELECT *
							FROM LOC_PROTOCOLO
							WHERE ID_ORGANIZACION = $id_organizacion
							AND (UPPER(NOMBRE) LIKE UPPER('%$textSearch%') OR UPPER(DESCRIPCION) LIKE UPPER('%$textSearch%'))
							ORDER BY ID_PROTOCOLO DESC";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$protocolos = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$protocolos [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $protocolos);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

			$this->returnResponse(SUCCESS_RESPONSE, $persona);

		}

		public function searchProtocolCollapse(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);
			$textSearch = $this->param['textSearch'];

			try {

				$query = "	SELECT T1.ID_ORGANIZACION, T1.NOMBRE
							FROM LOC_ORGANIZACION T1
							INNER JOIN LOC_PERSONA_ORGANIZACION T2
							ON T1.ID_ORGANIZACION = T2.ID_ORGANIZACION
							WHERE T2.ID_PERSONA = $userID";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$datos = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
				
					$titulo = $data["NOMBRE"];

					/* Buscar los protocolos */
					$id_organizacion = $data["ID_ORGANIZACION"];

					$query = "SELECT * FROM LOC_PROTOCOLO WHERE ID_ORGANIZACION = '$id_organizacion' AND (UPPER(NOMBRE) LIKE UPPER('%$textSearch%') OR UPPER(DESCRIPCION) LIKE UPPER('%$textSearch%'))  ORDER BY ID_PROTOCOLO ASC";

					$stid2 = oci_parse($this->dbConn, $query);
					oci_execute($stid2);

					$protocolos = array();

					while ($data2 = oci_fetch_array($stid2,OCI_ASSOC)) {

						$protocolos [] = $data2;

					}

					if ($protocolos) {
					
						$item = array("title" => $titulo, "content" => $protocolos);
						$datos [] = $item;

					}

				}

				$this->returnResponse(SUCCESS_RESPONSE, $datos);

			} catch (\Throwable $th) {
				
				$this->throwError(JWT_PROCESSING_ERROR, $th->getMessage());

			}

		}

		public function deleteProtocol(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);

			try {

				$query = "DELETE FROM LOC_PROTOCOLO WHERE ID_PROTOCOLO = $protocolID";
				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					if ($err["code"] == 2292) {
						
						$str_error = "El protocolo cuenta con mensajes y actividades configuradas, deberá eliminarlas primero.";

					}else{

						$str_error = "Se ha producido un error al intentar eliminar el protocolo";

					}

					$this->throwError($err["code"], $str_error);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $protocolID);

			} catch (\Exception $e) {

			}

		}

		public function protocolosAgrupados(){

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			try {

				$query = "	SELECT T1.ID_ORGANIZACION, T1.NOMBRE
							FROM LOC_ORGANIZACION T1
							INNER JOIN LOC_PERSONA_ORGANIZACION T2
							ON T1.ID_ORGANIZACION = T2.ID_ORGANIZACION
							WHERE T2.ID_PERSONA = $userID";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$datos = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
				
					$titulo = $data["NOMBRE"];

					/* Buscar los protocolos */
					$id_organizacion = $data["ID_ORGANIZACION"];

					$query = "SELECT * FROM LOC_PROTOCOLO WHERE ID_ORGANIZACION = '$id_organizacion' ORDER BY ID_PROTOCOLO ASC";

					$stid2 = oci_parse($this->dbConn, $query);
					oci_execute($stid2);

					$protocolos = array();

					while ($data2 = oci_fetch_array($stid2,OCI_ASSOC)) {

						$protocolos [] = $data2;

					}

					if ($protocolos) {
					
						$item = array("title" => $titulo, "content" => $protocolos);
						$datos [] = $item;

					}

				}

				$this->returnResponse(SUCCESS_RESPONSE, $datos);

			} catch (\Throwable $th) {
				
				$this->throwError(JWT_PROCESSING_ERROR, $th->getMessage());

			}

		}

		/* Activities */

		public function getActivities(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);

			try {

				$query = "SELECT * FROM LOC_ACTIVIDADES WHERE ID_PROTOCOLO = '$protocolID' ORDER BY ID_ACTIVIDAD DESC";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$actividades = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$actividades [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $actividades);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function getActivities2(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);
			$id_usuario = $this->validateParameter('id_usuario', $this->param['id_usuario'], STRING);

			try {

				$datos = array();

				$query = "	SELECT T1.*, T2.NOMBRE AS RESPONSABLE 
							FROM LOC_ACTIVIDADES T1
							INNER JOIN LOC_PERSONA T2
							ON T1.ID_RESPONSABLE = T2.ID_PERSONA
							WHERE T1.ID_PROTOCOLO = '$protocolID' 
							ORDER BY T1.ID_ACTIVIDAD DESC
						";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$actividades = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$actividades [] = $data;

				}

				// Validar si el usuario es administrador del equipo
				$query = "SELECT ID_ORGANIZACION FROM LOC_PROTOCOLO WHERE ID_PROTOCOLO = $protocolID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$id_organizacion = $result["ID_ORGANIZACION"];

				$query = "SELECT ADMINISTRADOR FROM LOC_PERSONA_ORGANIZACION WHERE ID_PERSONA = $id_usuario AND ID_ORGANIZACION = $id_organizacion";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$administrador = $result["ADMINISTRADOR"];

				$datos["ACTIVIDADES"] = $actividades;
				$datos["ADMINISTRADOR"] = $administrador;

				$this->returnResponse(SUCCESS_RESPONSE, $datos);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function createActivity(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);

			$activityName = $this->validateParameter('activityName', $this->param['activityName'], STRING);

			$activityDescription = $this->param['activityDescription'];

			$activityResponsableID = $this->validateParameter('activityResponsableID', $this->param['activityResponsableID'], STRING);

			try {

				$query = "INSERT INTO LOC_ACTIVIDADES (NOMBRE, DESCRIPCION, ID_PROTOCOLO,  ID_RESPONSABLE, ESTADO) VALUES ('$activityName', '$activityDescription', '$protocolID', '$activityResponsableID', 'A')";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido registrar la actividad";

					$this->throwError($err["code"], $str_error);

				}else{

					$this->returnResponse(SUCCESS_RESPONSE, $protocolID);

				}

			} catch (\Exception $e) {

			}


		}

		public function editActivity(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$activityID = $this->validateParameter('activityID', $this->param['activityID'], STRING);

			$activityName = $this->validateParameter('activityName', $this->param['activityName'], STRING);

			if (array_key_exists('activityDescription', $this->param)) {
				$activityDescription = $this->param['activityDescription'];
			}else{
				$activityDescription = '';
			}

			//$activityDescription = $this->param['activityDescription'];

			$activityResponsableID = $this->validateParameter('activityResponsableID', $this->param['activityResponsableID'], STRING);

			try {

				$query = "UPDATE LOC_ACTIVIDADES SET NOMBRE = '$activityName', DESCRIPCION = '$activityDescription', ID_RESPONSABLE = '$activityResponsableID' WHERE ID_ACTIVIDAD = '$activityID'";
				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido actualizar";

					$this->throwError($err["code"], $str_error);

				}else{

					$this->returnResponse(SUCCESS_RESPONSE, $activityID);

				}



			} catch (\Exception $e) {

			}


		}

		public function getDetailsActivity(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$activityID = $this->validateParameter('activityID', $this->param['activityID'], STRING);

			try {

				/* Detalles de la actividad */
				$query = "SELECT * FROM LOC_ACTIVIDADES WHERE ID_ACTIVIDAD = $activityID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$actividad = oci_fetch_array($stid,OCI_ASSOC);
				$protocolID = $actividad["ID_PROTOCOLO"];

				/* Responsables */
				$query = "SELECT ID_PERSONA AS \"value\", ID_PERSONA AS \"key\", NOMBRE as \"label\"
				FROM LOC_PERSONA
				WHERE ID_ORGANIZACION = (

				    SELECT LOC_PROTOCOLO.ID_ORGANIZACION AS ID_ORGANIZACION FROM
				    LOC_ACTIVIDADES
				    INNER JOIN LOC_PROTOCOLO
				    ON LOC_ACTIVIDADES.ID_PROTOCOLO = LOC_PROTOCOLO.ID_PROTOCOLO
				    WHERE LOC_ACTIVIDADES.ID_ACTIVIDAD = '$activityID'

				)";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$personas = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$personas [] = $data;

				}

				/* Equipo */
				$query = "SELECT ID_ORGANIZACION FROM LOC_PROTOCOLO WHERE ID_PROTOCOLO = $protocolID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$id_organizacion = $result["ID_ORGANIZACION"];

				$query = '	SELECT ID_PERSONA AS "id", NOMBRE as "name"
							FROM LOC_PERSONA
							WHERE ID_ORGANIZACION = ' . $id_organizacion;

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = array(
								"name" => "Equipo",
								"id" => 1,
							);
				$equipo = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$equipo [] = $data;

				}

				$result["children"] = $equipo;

				$this->returnResponse(SUCCESS_RESPONSE, array($actividad, $personas, $result));

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}


		}

		public function getDetailsActivity2(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$activityID = $this->validateParameter('activityID', $this->param['activityID'], STRING);
			$id_usuario = $this->param['id_usuario'];

			try {

				/* Detalles de la actividad */
				$query = "SELECT * FROM LOC_ACTIVIDADES WHERE ID_ACTIVIDAD = $activityID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$actividad = oci_fetch_array($stid,OCI_ASSOC);
				$protocolID = $actividad["ID_PROTOCOLO"];

				/* Responsables */
				$query = "SELECT ID_PERSONA AS \"value\", ID_PERSONA AS \"key\", NOMBRE as \"label\"
				FROM LOC_PERSONA
				WHERE ID_ORGANIZACION = (

				    SELECT LOC_PROTOCOLO.ID_ORGANIZACION AS ID_ORGANIZACION FROM
				    LOC_ACTIVIDADES
				    INNER JOIN LOC_PROTOCOLO
				    ON LOC_ACTIVIDADES.ID_PROTOCOLO = LOC_PROTOCOLO.ID_PROTOCOLO
				    WHERE LOC_ACTIVIDADES.ID_ACTIVIDAD = '$activityID'

				)";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$personas = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$personas [] = $data;

				}

				/* Equipo */
				$query = "SELECT ID_ORGANIZACION FROM LOC_PROTOCOLO WHERE ID_PROTOCOLO = $protocolID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$id_organizacion = $result["ID_ORGANIZACION"];

				$query = '	SELECT T1.ID_PERSONA AS "id", T1.NOMBRE as "name"
							FROM LOC_PERSONA T1
							INNER JOIN LOC_PERSONA_ORGANIZACION T2
							ON T1.ID_PERSONA = T2.ID_PERSONA
							WHERE T2.ID_ORGANIZACION = ' . $id_organizacion;

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = array(
								"name" => "Equipo",
								"id" => 1,
							);
				$equipo = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$equipo [] = $data;

				}

				$result["children"] = $equipo;

				/** Validar si el usuario es administrador */
				$query = "SELECT ADMINISTRADOR FROM LOC_PERSONA_ORGANIZACION WHERE ID_PERSONA = $id_usuario AND ID_ORGANIZACION = $id_organizacion";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$resultado = oci_fetch_array($stid, OCI_ASSOC);
				$administrador = $resultado["ADMINISTRADOR"];

				$this->returnResponse(SUCCESS_RESPONSE, array($actividad, $personas, $result, $administrador));

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function updateActivityState(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$activityID = $this->validateParameter('activityID', $this->param['activityID'], STRING);

			$activityState = $this->validateParameter('activityState', $this->param['activityState'], STRING);

			try {

				$query = "UPDATE LOC_ACTIVIDADES SET ESTADO = '$activityState' WHERE ID_ACTIVIDAD = '$activityID'";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido actualizar";

					$this->throwError($err["code"], $str_error);

				}else{

					$activity = array( 'activityID' => $activityID, 'activityState' => $activityState );

					$this->returnResponse(SUCCESS_RESPONSE, $activity);

				}

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function searchActivities(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);

			$textSearch = $this->param['textSearch'];

			try {

				$query = "	SELECT *
							FROM LOC_ACTIVIDADES
							WHERE ID_PROTOCOLO = $protocolID
							AND (
								UPPER(NOMBRE) LIKE UPPER('%$textSearch%')
								OR UPPER(DESCRIPCION) LIKE UPPER('%$textSearch%')
							)";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$actividades = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$actividades [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $actividades);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}


		}

		public function deleteActivity(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$activityID = $this->validateParameter('activityID', $this->param['activityID'], STRING);

			try {

				$query = "DELETE FROM LOC_ACTIVIDADES WHERE ID_ACTIVIDAD = $activityID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$this->returnResponse(SUCCESS_RESPONSE, $activityID);

			} catch (\Exception $e) {

			}

		}

		public function deleteActivity2(){

			$actividades = $this->param['actividades'];

			foreach ($actividades as $actividad) {
				
				try {

					$query = "DELETE FROM LOC_ACTIVIDADES WHERE ID_ACTIVIDAD = $actividad";
					$stid = oci_parse($this->dbConn, $query);
					// oci_execute($stid);
					
					if (false === oci_execute($stid)) {

						$err = oci_error($stid);
	
						$str_error = "Error al eliminar la actividad";
	
						$this->throwError($err["code"], $str_error);
	
					}

				} catch (\Exception $e) {
	
				}

			}

			$this->returnResponse(SUCCESS_RESPONSE, $actividades);

		}

		/*Team*/

		public function loadTeam(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			/* Obtener ID de la organizacion */
			$query = "SELECT ID_ORGANIZACION FROM LOC_PERSONA WHERE ID_PERSONA = $userID";
			$stid = oci_parse($this->dbConn, $query);
			oci_execute($stid);

			$persona = oci_fetch_array($stid, OCI_ASSOC);
			$id_organizacion = $persona["ID_ORGANIZACION"];

			try {

				$query = "SELECT ID_PERSONA AS \"value\", ID_PERSONA AS \"key\", NOMBRE as \"label\" FROM LOC_PERSONA WHERE ID_ORGANIZACION = $id_organizacion";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$personas = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$personas [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $personas);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function getTeam(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			if (array_key_exists("organizacionID", $this->param)) {
				
				$id_organizacion = $this->param['organizacionID'];

			}else{

				/* Obtener ID de la organizacion */
				$query = "SELECT ID_ORGANIZACION FROM LOC_PERSONA WHERE ID_PERSONA = $userID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$persona = oci_fetch_array($stid, OCI_ASSOC);
				$id_organizacion = $persona["ID_ORGANIZACION"];

			}

			// /* Obtener ID de la organizacion */
			// $query = "SELECT ID_ORGANIZACION FROM LOC_PERSONA WHERE ID_PERSONA = $userID";
			// $stid = oci_parse($this->dbConn, $query);
			// oci_execute($stid);

			// $persona = oci_fetch_array($stid, OCI_ASSOC);
			// $id_organizacion = $persona["ID_ORGANIZACION"];

			try {

				// $query = "	SELECT ID_PERSONA AS ID, NOMBRE, TO_CHAR(AVATAR) AS AVATAR
				// 			FROM LOC_PERSONA
				// 			WHERE ID_ORGANIZACION = $id_organizacion
				// 			AND ID_PERSONA != $userID";

				$query = "	SELECT T1.ID_PERSONA AS ID, T2.NOMBRE, TO_CHAR(T2.AVATAR) AS AVATAR, 	
							T1.ADMINISTRADOR
							FROM LOC_PERSONA_ORGANIZACION T1
							LEFT JOIN LOC_PERSONA T2
							ON T1.ID_PERSONA = T2.ID_PERSONA
							WHERE T1.ID_ORGANIZACION = $id_organizacion
							ORDER BY T2.NOMBRE ASC";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$personas = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					if(array_key_exists('AVATAR', $data)){

						$avatar = $data["AVATAR"];
						$photo = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar);
						$data["AVATAR"] = $photo;

					}else{

						$data["AVATAR"] = "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";

					}

					$personas [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $personas);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function getTeam2(){

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);
			$id_organizacion = $this->param['organizacionID'];

			try {

				/** Validar si el usuario es administrador del equipo */
				$query = "SELECT ADMINISTRADOR FROM LOC_PERSONA_ORGANIZACION WHERE ID_PERSONA = $userID AND ID_ORGANIZACION = $id_organizacion";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);

				$resultados = array();

				if ($result) {
					
					$resultados["ADMINISTRADOR"] = true;

				}

				$query = "	SELECT T1.ID_PERSONA AS ID, T2.NOMBRE, TO_CHAR(T2.AVATAR) AS AVATAR, 	
							T1.ADMINISTRADOR
							FROM LOC_PERSONA_ORGANIZACION T1
							LEFT JOIN LOC_PERSONA T2
							ON T1.ID_PERSONA = T2.ID_PERSONA
							WHERE T1.ID_ORGANIZACION = $id_organizacion
							ORDER BY T2.NOMBRE ASC";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$personas = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					if(array_key_exists('AVATAR', $data)){

						$avatar = $data["AVATAR"];
						$photo = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar);
						$data["AVATAR"] = $photo;

					}else{

						$data["AVATAR"] = "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";

					}

					$personas [] = $data;

				}

				$resultados["PERSONAS"] = $personas;

				$this->returnResponse(SUCCESS_RESPONSE, $resultados);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}


		}

		public function searchTeam(){

			$db = new Db();
			$this->dbConn = $db->connect();

			// $userID = $this->validateParameter('userID', , STRING);
			$userID = $this->param['userID'];
			$id_organizacion = $this->param['id_organizacion'];
			$textSearch = $this->param['textSearch'];

			try {

				if (!$id_organizacion) {
					
					$query = "	SELECT ID_PERSONA AS ID, NOMBRE, AVATAR
								FROM LOC_PERSONA
								WHERE ID_ORGANIZACION = (

									SELECT ID_ORGANIZACION
									FROM LOC_PERSONA
									WHERE ID_PERSONA = $userID

								)
								AND UPPER(NOMBRE) LIKE UPPER('%$textSearch%') ORDER BY T1.NOMBRE ASC";

				}else{

					$query = "	SELECT T1.ID_PERSONA AS ID, T1.NOMBRE, T1.AVATAR, T2.ADMINISTRADOR
								FROM LOC_PERSONA T1
								INNER JOIN LOC_PERSONA_ORGANIZACION T2 
								ON T1.ID_PERSONA = T2.ID_PERSONA
								WHERE T2.ID_ORGANIZACION = '$id_organizacion' AND UPPER(NOMBRE) LIKE UPPER('%$textSearch%') ORDER BY T1.NOMBRE ASC";

				}

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$team = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					if(array_key_exists('AVATAR', $data)){

						$avatar = $data["AVATAR"];
						$photo = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar);
						$data["AVATAR"] = $photo;

					}else{

						$data["AVATAR"] = "iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAMAAACdt4HsAAAAA3NCSVQICAjb4U/gAAAACXBIWXMAABC3AAAQtwE91jKoAAAAGXRFWHRTb2Z0d2FyZQB3d3cuaW5rc2NhcGUub3Jnm+48GgAAAn9QTFRF////AAD/////gICAVVWq////QICA////ZmaZVVWA29v/39/fVVWO5ubmTmKJW1uAVWaIUGCA3+/v5PLyVWGGUV2AVWCAUlyFWGGEV1+D6O7u5ervVV+CV2CCV2CAVl+C5+vrVl6B6OzsVmKA5+7uVV+BVGGAVV+AVGCAVWGAVF+B5uvuVmGA5uzu5uzsVGGBVGGAVmCB5uvuVGGBVmCA5+zuVl+BVWGAVGCBVWCBk5muVF+B5+vtVmGAeYSc5+zuVmCA5uzsVmCBVmCA6OztVGCAVWCAVmGAVGGAVmCA5+zsVWGA5uvtVl+AVmCA5+3tVmCAVV+A5+zt5+ztVWCAVWGA5+zuVWCAVV+A5+zt5+ztVWCAgougg4yihI6kho+kiJCmfYaehY6ljZapkpuuVWCAdn+Yd4CZl6Cxcn2WdX6XmqKzVV+AcHqUoKe3oai56OvtVWCApq+9Z3GOrLPBrbTCrbXD5+ztrrXDVWCAZW+Mr7fEsbjFZG+LsrnFVWCAt7/KuMDLVWCAucDLVWCAYGuIvMPNvcTOVV+AVWCAXWeGwMbQ5+ztVWCAXGeG5+ztXGaFW2aFy9DXVWCAy9HY5+zsWWSEzdPa5+3tztPa5+vt5+ztVWCAWWODWGOCWGKC0dbc5+ztVV+AV2KCVWCAV2KC1Nne5+zt5+ztV2GB1tzgV2KBV2KC5+ztVWCA2N7jVWCAVV+AVmGBVmGAVWCAVWGB3eLl3ePm3uTnVWGA3uTn4OXo4ebp5+ztVWGAVWCAVWCB4ufp5+ztVWCAVWCA5+zsVWCA5OjrVWCAVWCBVWGAVWCAVWCBVWCA5uvs5+ztVWCAVWCB5uvtVWCA5+ztqoANgQAAANN0Uk5TAAEBAgMDBAQFBgcICQoNDg8QEBMVFhgZHSMtMTM1ODtAQUNESUtMTlJUW1tcXF1hZGVmZ2hqa2xtb3Fzc3R0d3p8fYCDhYeJkZWVmZuep6eqq6utsLGxsrO4vb+/v7+/v8DAwMDBwcHBwsLCw8PDxMTFxcjIyMjIycrKysrLy8zOzs/P0NDR0tPU1NTU1dXV1tfb3Nzc3d3d3t7f4ODh4uLi4+Tl5eXl5ufn6Ojo6enq6+vt7u/v8fLz8/T09PX29vb29/j4+fn6+vv8/P39/f7+/mpYuL8AAAMHSURBVBgZlcGLY5VzGAfw7yxLSS10c4lupho1TZlrGmWNLJeMo2guaSZyCTXT6OqVToUcRNYoyspMCsklp1oO63yfP8hu2vv7Pc97zunzQZRBE8orqmIbW5JNy+fNmVE0DGdlZGn1UTpOxaaPQI4KStbQkqorK0R2+cXbGOlAaT9kljdxEzPaMA6ZDK9jVtWjEGl0M3PQNgYRSpLMyYkpsAyoZM5m50HJm0+lfW985cr43nYq8wfAN5ueHYu2p6VbevuiHfRUwjOFrtZ6cdS30lUCx5gTdKzdL579a+lIjkbIqDY6dh0U5eAuOpqHo081XY1iaKSrLg//G0fXvrQY0vvomohe/TbQtVRMS+nalI8epfSsENMKeorRrfAAPYGYAnq2FaBLGX2BmAL6StCljr5ATAF9a9BpRIq+QEwBlZEAplMJxBRQKQUQoxKIKaBSDQw7RSUQU0Dl6CAUUTm5U0w7T1KZgBlUFkqEhVTKMYfKeomwnkoF5lFJSIQElSosp5KQCAkqMTRRSUiEBJWNSFJJSIQElRY0U3lbIjRQSeJNKoslwnNUPsVDVP5+RSz/PJGkEkMltQ/FspiGe1BOw1bRfv+BhlmYRsNjoi2jpRhX0fDlcfGl47RcgSHHaHhGfMto6bgYeJCGr38R14+ttDwFoJiWJeJaQtNUAEOO0XB6tYS9TNOfhej0AC01ElZLUxW6TKKlRsJqaSpCl8FtNNRIWC0t352HbtNoeFzCnqblFvTIf53aKgl7loa3CtDrshR9v/4kYV+dppK6HGdU0HN4nbieb6fvLvQZ2kLHG++Lb/UWupoGI+TqFPvEG8Xy2haGdBTBMbWDvT5/MS0RVsV5xg3wlLHb908elwzq32OPm6DMJPnbo4cki4Z32GkmDHfypW8kBw2fsRymG/+VnPx8KyJc+YXkYM94RLrwXsnqvouQyeTdktG31yCLgdd9JJE+uf58ZHfu5HfFtPna/sjRJTc//Ic4/nrktkvPwdkYOPb2ufe/8MGRIx+/uuDuO8ZfgAj/AcrrExM69B9sAAAAAElFTkSuQmCC";

					}

					$team [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $team);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function itemsEquipo(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			/* Obtener ID de la organizacion */
			$query = "SELECT ID_ORGANIZACION FROM LOC_PERSONA WHERE ID_PERSONA = $userID";
			$stid = oci_parse($this->dbConn, $query);
			oci_execute($stid);

			$persona = oci_fetch_array($stid, OCI_ASSOC);
			$id_organizacion = $persona["ID_ORGANIZACION"];

			try {

				$query = '	SELECT ID_PERSONA AS "id", NOMBRE as "name"
							FROM LOC_PERSONA
							WHERE ID_ORGANIZACION = ' . $id_organizacion;

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = array(
								"name" => "Equipo",
								"id" => 1,
							);
				$personas = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$data["icon"] = array("uri" => "https://cdn4.iconfinder.com/data/icons/free-crystal-icons/512/Gemstone.png");

					$data["sub"] = array(
											"name" => "Prueba",
											"id" => 789,
										);

					$personas [] = $data;

				}

				$result["children"] = $personas;

				$this->returnResponse(SUCCESS_RESPONSE, $result);

			} catch (\Exception $e) {

			}


		}

		public function addPerson(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$nombre = $this->validateParameter('nombre', $this->param['nombre'], STRING);
			$telefono = $this->validateParameter('telefono', $this->param['telefono'], STRING);
			$email = $this->param['email'];
			
			$tipo = null;
			
			try {

				if (array_key_exists("id_administrador", $this->param)) {
					
					$id_administrador = $this->param['id_administrador'];
					$tipo = 1;

					/* Obtener el ID de la organizacion */
					$query = "SELECT ID_ORGANIZACION FROM LOC_PERSONA WHERE ID_PERSONA = $id_administrador";
					$stid = oci_parse($this->dbConn, $query);
					oci_execute($stid);

					$result = oci_fetch_array($stid, OCI_ASSOC);
					$id_organizacion = $result["ID_ORGANIZACION"];

				}else{

					$id_organizacion = $this->param['id_organizacion'];
					$tipo = 2;

				}

				/* Registrar Persona */

				/* Validar que la persona no este registrada */
				$query = "SELECT * FROM LOC_PERSONA WHERE TELEFONO = '$telefono'";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);

				if ($result) {
					
					$this->throwError(GENERAL_ERROR, "Ya existe una persona registrada con este número de teléfono, inicie sesión o bien intente con otro número");

				}

				$query = "INSERT INTO LOC_PERSONA (NOMBRE, TELEFONO, EMAIL, PASSWORD, TOKEN, CREATED_AT, ADMINISTRADOR) VALUES ('$nombre', '$telefono', '$email', ENCRIPTAR('123456'), '1', SYSDATE, 'S')";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "Error al registrar a la persona";

					$this->throwError($err["code"], $this->param);

				}

				/* Registrar en LOC_PERSONA_ORGANIZACION */
				/* Obtener la ultima persona */

				if ($tipo == 2) {
					
					$query = "SELECT ID_PERSONA FROM LOC_PERSONA WHERE ROWNUM = 1 ORDER BY ID_PERSONA DESC";
					$stid = oci_parse($this->dbConn, $query);
					oci_execute($stid);

					$result = oci_fetch_array($stid, OCI_ASSOC);
					$id_persona = $result["ID_PERSONA"];

					$query = "INSERT INTO LOC_PERSONA_ORGANIZACION (ID_PERSONA, ID_ORGANIZACION) VALUES ('$id_persona', '$id_organizacion')";
					$stid = oci_parse($this->dbConn, $query);

					if (false === oci_execute($stid)) {

						$err = oci_error($stid);

						$str_error = "Error al registrar a la persona";

						$this->throwError($err["code"], $this->param);

					}

				}

				$this->returnResponse(SUCCESS_RESPONSE, $id_organizacion);

			} catch (\Exception $e) {

			}


		}

		public function deletePerson(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$id_persona = $this->validateParameter('id_persona', $this->param['id_persona'], STRING);

			try {

				$query = "DELETE FROM LOC_PERSONA WHERE ID_PERSONA = $id_persona";
				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					if ($err["code"] == 2292) {
						
						$str_error = "Esta persona cuenta con actividades o mensajes en un protocolo por lo que no se puede eliminar.";

					}else{

						$str_error = "Error al eliminar a la persona";
					}

					$this->throwError($err["code"], $str_error);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $id_persona);

			} catch (\Exception $e) {

			}


		}

		public function deletePerson2(){

			$id_persona = $this->validateParameter('id_persona', $this->param['id_persona'], STRING);
			$id_organizacion = $this->validateParameter('id_organizacion', $this->param['id_organizacion'], STRING);

			try {
				
				$query = "DELETE FROM LOC_PERSONA_ORGANIZACION WHERE ID_PERSONA = $id_persona AND ID_ORGANIZACION = $id_organizacion";

				$stid = oci_parse($this->dbConn, $query);
				
				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					if ($err["code"] == 2292) {
						
						$str_error = "Esta persona cuenta con actividades o mensajes en un protocolo por lo que no se puede eliminar.";

					}else{

						$str_error = "Error al eliminar a la persona";
					}

					$this->throwError($err["code"], $str_error);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $id_persona);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

		public function deletePerson3(){

			$id_personas = $this->param['id_personas'];
			$id_organizacion = $this->validateParameter('id_organizacion', $this->param['id_organizacion'], STRING);

			try {
				
				foreach ($id_personas as $id_persona) {
				
					$query = "DELETE FROM LOC_PERSONA_ORGANIZACION WHERE ID_PERSONA = $id_persona AND ID_ORGANIZACION = $id_organizacion";

					$stid = oci_parse($this->dbConn, $query);
					
					if (false === oci_execute($stid)) {

						$err = oci_error($stid);

						if ($err["code"] == 2292) {
							
							$str_error = "Esta persona cuenta con actividades o mensajes en un protocolo por lo que no se puede eliminar.";

						}else{

							$str_error = "Error al eliminar a la persona";
						}

						$this->throwError($err["code"], $str_error);

					}

				}

				$this->returnResponse(SUCCESS_RESPONSE, $id_organizacion);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

		public function detailsPerson(){

			$personID = $this->validateParameter('personID', $this->param['personID'], STRING);

			try {

				$query = "SELECT ID_PERSONA, NOMBRE, TELEFONO, EMAIL FROM LOC_PERSONA";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$persona = oci_fetch_array($stid, OCI_ASSOC);

				$this->returnResponse(SUCCESS_RESPONSE, $persona);

			} catch (\Exception $e) {

			}


		}

		public function getTeams(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			try {
				
				$query = "	SELECT T1.ID_ORGANIZACION, T2.NOMBRE, T2.DESCRIPCION, 
							T1.ADMINISTRADOR, T2.AVATAR
							FROM LOC_PERSONA_ORGANIZACION T1
							INNER JOIN LOC_ORGANIZACION T2
							ON T1.ID_ORGANIZACION = T2.ID_ORGANIZACION
							WHERE T1.ID_PERSONA = $userID 
							ORDER BY T1.ID_ORGANIZACION DESC";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$equipos = array();

				while($data = oci_fetch_array($stid, OCI_ASSOC)){

					if(array_key_exists('AVATAR', $data)){

						$avatar = $data["AVATAR"];
						$photo = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar);
						$data["AVATAR"] = $photo;

					}else{

						$data["AVATAR"] = "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";

					}

					/** Validar si es administrador */
					if (array_key_exists('ADMINISTRADOR', $data)) {
					
						$data["ADMINISTRADOR"] = true;

					}

					$equipos [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $equipos);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

		public function itemsEquipo2(){

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);

			try {
				
				/* Obtener el ID de la organizacion */
				$query = "SELECT ID_ORGANIZACION FROM LOC_PROTOCOLO WHERE ID_PROTOCOLO = $protocolID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$id_organizacion = $result["ID_ORGANIZACION"];
				
				$query = '	SELECT T1.ID_PERSONA AS "id", T1.NOMBRE as "name"
							FROM LOC_PERSONA T1
							INNER JOIN LOC_PERSONA_ORGANIZACION T2
							ON T1.ID_PERSONA = T2.ID_PERSONA
							WHERE T2.ID_ORGANIZACION = ' . $id_organizacion;

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = array(
								"name" => "Equipo",
								"id" => 1,
							);
				$personas = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$personas [] = $data;

				}

				$result["children"] = $personas;

				$this->returnResponse(SUCCESS_RESPONSE, $result);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

		public function itemsEquipoAlertas(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			try {

				/* Obtener las organizaciones */
				$query = '	SELECT T1.ID_ORGANIZACION AS "id", T1.NOMBRE as "name" 
							FROM LOC_ORGANIZACION T1
							INNER JOIN LOC_PERSONA_ORGANIZACION T2
							ON T1.ID_ORGANIZACION = T2.ID_ORGANIZACION
							WHERE T2.ID_PERSONA = ' . $userID .
							'AND T2.ADMINISTRADOR = ' ."'S'";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$equipos = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
				
					$id_organizacion = $data["id"];

					$query = '	SELECT T1.ID_PERSONA AS "id", T1.NOMBRE as "name"
								FROM LOC_PERSONA T1
								INNER JOIN LOC_PERSONA_ORGANIZACION T2
								ON T1.ID_PERSONA = T2.ID_PERSONA
								WHERE T2.ID_ORGANIZACION = ' . $id_organizacion;

					$stid2 = oci_parse($this->dbConn, $query);
					oci_execute($stid2);

					$integrantes = array();

					while ($data2 = oci_fetch_array($stid2, OCI_ASSOC)) {
						
						$integrantes [] = $data2;

					}

					$data["children"] = $integrantes;

					$equipos [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $equipos);

			} catch (\Exception $e) {

			}

		}

		public function getTeamsSimple(){

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);
			
			try {
				
				$query = '	SELECT T1.ID_ORGANIZACION AS "id", T2.NOMBRE as "name"
							FROM LOC_PERSONA_ORGANIZACION T1
							INNER JOIN LOC_ORGANIZACION T2
							ON T1.ID_ORGANIZACION = T2.ID_ORGANIZACION
							WHERE T1.ID_PERSONA = ' . $userID . 
							' AND T1.ADMINISTRADOR = ' ."'S'".
							' ORDER BY T1.ID_ORGANIZACION DESC';

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$equipos = array();

				$result = 	array(
					"name" => "Equipos",
					"id" => "1",
				);

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
				
					$equipos [] = $data;

				}

				$result["children"] = $equipos;

				$this->returnResponse(SUCCESS_RESPONSE, $result);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

		public function infoTeam(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$organizacionID = $this->validateParameter('organizacionID', $this->param['organizacionID'], STRING);

			try {
			
				$query = "	SELECT T1.*, T2.ADMINISTRADOR
							FROM LOC_ORGANIZACION T1
							INNER JOIN LOC_PERSONA_ORGANIZACION T2
							ON T1.ID_ORGANIZACION = T2.ID_ORGANIZACION
							WHERE T1.ID_ORGANIZACION = $organizacionID";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$organizacion = oci_fetch_array($stid, OCI_ASSOC);

				if (array_key_exists('AVATAR', $organizacion)) {
					
					$avatar = $organizacion["AVATAR"];
					$photo = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar);
					$organizacion["AVATAR"] = $photo;

				}else{

					$organizacion["AVATAR"] = "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";

				}

				if (array_key_exists('ADMINISTRADOR', $organizacion)) {
				
					$organizacion["ADMINISTRADOR"] = true;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $organizacion);

			} catch (\Throwable $th) {
				
			}

		}

		public function infoTeam2(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$organizacionID = $this->validateParameter('organizacionID', $this->param['organizacionID'], STRING);

			$id_persona = $this->validateParameter('id_persona', $this->param['id_persona'], STRING);

			try {
			
				$query = "	SELECT T1.*, T2.ADMINISTRADOR
							FROM LOC_ORGANIZACION T1
							INNER JOIN LOC_PERSONA_ORGANIZACION T2
							ON T1.ID_ORGANIZACION = T2.ID_ORGANIZACION
							WHERE T2.ID_ORGANIZACION = $organizacionID
							AND T2.ID_PERSONA = $id_persona";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$organizacion = oci_fetch_array($stid, OCI_ASSOC);

				if (array_key_exists('AVATAR', $organizacion)) {
					
					$avatar = $organizacion["AVATAR"];
					$photo = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar);
					$organizacion["AVATAR"] = $photo;

				}else{

					$organizacion["AVATAR"] = "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";

				}

				$this->returnResponse(SUCCESS_RESPONSE, $organizacion);

			} catch (\Throwable $th) {
				
			}

		}

		public function editTeam(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$id_organizacion = $this->validateParameter('id_organizacion', $this->param['id_organizacion'], STRING);
			$nombre = $this->validateParameter('nombre', $this->param['nombre'], STRING);
			$descripcion = $this->param["descripcion"];
			$telefono = $this->param["telefono"];
			$direccion = $this->param["direccion"];
			$avatar = $this->param['avatar'];

			try {
									
				$query = "SELECT AVATAR FROM LOC_ORGANIZACION WHERE ID_ORGANIZACION = $id_organizacion";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$organizacion = oci_fetch_array($stid, OCI_ASSOC);

				if(!$organizacion){

					/** Persona sin avatar */
					$key = '';
					$keys = array_merge(range(0, 9), range('a', 'z'));

					for ($i = 0; $i < 50; $i++) {

						$key .= $keys[array_rand($keys)];

					}

				}else{

					/** Persona con avatar */
					$key = $organizacion["AVATAR"];

				}

				$myfile = fopen($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$key, "w") or die("Unable to open file!");
				fwrite($myfile, $avatar);
				fclose($myfile);

				$query = "	UPDATE LOC_ORGANIZACION 
							SET NOMBRE = '$nombre', DESCRIPCION = '$descripcion', 
							TELEFONO = '$telefono', DIRECCION = '$direccion', 
							AVATAR = '$key', UPDATED_AT = SYSDATE 
							WHERE ID_ORGANIZACION = '$id_organizacion' ";


				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$this->returnResponse(SUCCESS_RESPONSE, $id_organizacion);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

		public function addTeam(){

			$nombre = $this->validateParameter('nombre', $this->param['nombre'], STRING);
			$descripcion = $this->param['descripcion'];
			$telefono = $this->param['telefono'];
			$direccion = $this->param['direccion'];
			$administrador = $this->validateParameter('administrador', $this->param['administrador'], STRING);
			$avatar = $this->param['avatar'];

			try {
				
				/* Registrar el equipo */

				/* Con Avatar */
				if ($avatar) {
			
					/** Persona sin avatar */
					$key = '';
					$keys = array_merge(range(0, 9), range('a', 'z'));

					for ($i = 0; $i < 50; $i++) {

						$key .= $keys[array_rand($keys)];

					}

					$myfile = fopen($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$key, "w") or die("Unable to open file!");
					fwrite($myfile, $avatar);
					fclose($myfile);

					$query = "INSERT INTO LOC_ORGANIZACION (NOMBRE, DESCRIPCION, TELEFONO, DIRECCION, AVATAR, CREATED_AT) VALUES ('$nombre', '$descripcion', '$telefono', '$direccion', '$key', SYSDATE)";
					$stid = oci_parse($this->dbConn, $query);

				}else{

					$query = "INSERT INTO LOC_ORGANIZACION (NOMBRE, DESCRIPCION, TELEFONO, DIRECCION, CREATED_AT) VALUES ('$nombre', '$descripcion', '$telefono', '$direccion', SYSDATE)";
					$stid = oci_parse($this->dbConn, $query);

				}

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "Error al registrar el equipo";

					$this->throwError($err["code"], $str_error);

				}

				/* Obtener el ultimo ID */
				$query = "SELECT ID_ORGANIZACION FROM LOC_ORGANIZACION WHERE ROWNUM = 1 ORDER BY ID_ORGANIZACION DESC";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$id_organizacion = $result["ID_ORGANIZACION"];

				/* Registrar al administrador */
				$query = "INSERT INTO LOC_PERSONA_ORGANIZACION (ID_PERSONA, ID_ORGANIZACION, ADMINISTRADOR) VALUES ('$administrador', '$id_organizacion', 'S')";
				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "Error al registrar el equipo";

					$this->throwError($err["code"], $str_error);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $avatar);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

		public function buscarEquipo(){

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);
			$textSearch = $this->param['textSearch'];

			try {
			
				$query = "	SELECT * 
							FROM LOC_ORGANIZACION
							WHERE UPPER(NOMBRE) LIKE UPPER('%$textSearch%') AND
							ID_ORGANIZACION IN (
								
								SELECT ID_ORGANIZACION 
								FROM LOC_PERSONA_ORGANIZACION
								WHERE ID_PERSONA = $userID
							
							)
							ORDER BY ID_ORGANIZACION DESC";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$equipos = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					if(array_key_exists('AVATAR', $data)){

						$avatar = $data["AVATAR"];
						$photo = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar);
						$data["AVATAR"] = $photo;

					}else{

						$data["AVATAR"] = "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";

					}

					$equipos [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $equipos);

			} catch (\Throwable $th) {

				$this->throwError(JWT_PROCESSING_ERROR, $th->getMessage());

			}

		}

		public function deleteTeam(){

			$id_organizacion = $this->validateParameter('id_organizacion', $this->param['id_organizacion'], STRING);

			try {
				
				$query = "DELETE FROM LOC_ORGANIZACION WHERE ID_ORGANIZACION = '$id_organizacion'";
				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "Error al eliminar el equipo";

					$this->throwError($err["code"], $str_error);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $id_organizacion);

			} catch (\Throwable $th) {

				

			}


		}

		public function deleteTeam2(){

			$id_equipos =$this->param['id_equipos'];

			foreach ($id_equipos as $id_equipo) {
				
				$query = "DELETE FROM LOC_ORGANIZACION WHERE ID_ORGANIZACION = '$id_equipo'";
				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "Error al eliminar el equipo";

					$this->throwError($err["code"], $str_error);

				}

			}

			$this->returnResponse(SUCCESS_RESPONSE, $id_equipos);

		}

		public function cambiarRol(){

			$id_persona = $this->validateParameter('id_persona', $this->param['id_persona'], STRING);
			$id_organizacion = $this->validateParameter('id_organizacion', $this->param['id_organizacion'], STRING);
			$rol = $this->param['rol'];

			try {
				
				$query = "UPDATE LOC_PERSONA_ORGANIZACION SET ADMINISTRADOR = '$rol', UPDATED_AT = SYSDATE WHERE ID_PERSONA = '$id_persona' AND ID_ORGANIZACION = '$id_organizacion'";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "Error al actualizar el rol";

					$this->throwError($err["code"], $str_error);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $id_persona);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

		public function abandonarEquipo(){

			$id_usuario = $this->validateParameter('id_usuario', $this->param['id_usuario'], STRING);
			$id_equipo = $this->validateParameter('id_equipo', $this->param['id_equipo'], STRING);

			//Validar si es el unico administrador
			$query = "	SELECT ADMINISTRADOR
						FROM LOC_PERSONA_ORGANIZACION
						WHERE ID_PERSONA = $id_usuario
						AND ID_ORGANIZACION = $id_equipo";

			$stid = oci_parse($this->dbConn, $query);
			oci_execute($stid);
			
			$data = oci_fetch_array($stid, OCI_ASSOC);

			if ($data["ADMINISTRADOR"]) {
				
				//Si es administrador verificar que exista otro administrador antes de abandonar
				$query = "	SELECT COUNT(*) AS TOTAL_ADMINISTRADORES
							FROM LOC_PERSONA_ORGANIZACION
							WHERE ID_ORGANIZACION = $id_equipo
							AND ADMINISTRADOR = 'S'";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);
				
				$data = oci_fetch_array($stid, OCI_ASSOC);
				$total_administradores = intval($data["TOTAL_ADMINISTRADORES"]);
				
				if ($total_administradores < 2) {
				
					$str_error = "Debe ceder el rol de administrador a otro usuario para poder abandonar el equipo";

					$this->throwError(GENERAL_ERROR, $str_error);

				}

			}

			//Abandonar el equipo
			$query = "	DELETE
						FROM LOC_PERSONA_ORGANIZACION
						WHERE ID_PERSONA = $id_usuario
						AND ID_ORGANIZACION = $id_equipo";

			$stid = oci_parse($this->dbConn, $query);

			if (false === oci_execute($stid)) {

				$err = oci_error($stid);

				$str_error = "Error al abandonar el equipo";

				$this->throwError($err["code"], $str_error);

			}

			$this->returnResponse(SUCCESS_RESPONSE, $id_usuario);

		}

		/*Messages*/

		public function getMessages(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);

			try {

				$query = "SELECT * FROM LOC_MENSAJE WHERE ID_PROTOCOLO = '$protocolID'";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$mensajes = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$mensajes [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $mensajes);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function getMessages2(){

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);
			$id_usuario = $this->validateParameter('id_usuario', $this->param['id_usuario'], STRING);

			try {

				$datos = array();

				$query = "SELECT * FROM LOC_MENSAJE WHERE ID_PROTOCOLO = '$protocolID'";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$mensajes = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$mensajes [] = $data;

				}

				// Validar si el usuario es administrador del equipo
				$query = "SELECT ID_ORGANIZACION FROM LOC_PROTOCOLO WHERE ID_PROTOCOLO = $protocolID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$id_organizacion = $result["ID_ORGANIZACION"];

				$query = "SELECT ADMINISTRADOR FROM LOC_PERSONA_ORGANIZACION WHERE ID_PERSONA = $id_usuario AND ID_ORGANIZACION = $id_organizacion";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$administrador = $result["ADMINISTRADOR"];

				$datos["MENSAJES"] = $mensajes;
				$datos["ADMINISTRADOR"] = $administrador;

				$this->returnResponse(SUCCESS_RESPONSE, $datos);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function createMessage(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);

			$messageName = $this->validateParameter('messageName', $this->param['messageName'], STRING);

			$message = $this->validateParameter('message', $this->param['message'], STRING);

			$destinations = $this->param['personID'];

			try {

				$query = "INSERT INTO LOC_MENSAJE (NOMBRE, MENSAJE, ID_PROTOCOLO, ESTADO) VALUES ('$messageName', '$message', '$protocolID', 'A')";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido registrar el mensaje";

					$this->throwError($err["code"], $str_error);

				}

				/* Registrar los destinos del mensaje */
				$query = "	SELECT ID_MENSAJE
							FROM LOC_MENSAJE
							WHERE ROWNUM = 1
							AND ID_PROTOCOLO = $protocolID
							ORDER BY ID_MENSAJE DESC";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$id_mensaje = $result["ID_MENSAJE"];

				foreach ($destinations as $destination) {

					$query = "INSERT INTO LOC_MENSAJE_PERSONA (ID_MENSAJE, ID_PERSONA) VALUES ($id_mensaje, $destination)";
					$stid = oci_parse($this->dbConn, $query);
					oci_execute($stid);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $protocolID);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

			//$array_without_strawberries = array_diff($array, array('strawberry'));

			//$this->returnResponse(SUCCESS_RESPONSE, $personID);

		}

		public function getDetailsMessage(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$messageID = $this->validateParameter('messageID', $this->param['messageID'], STRING);

			try {

				/* Detalles del mensaje */
				$query = "SELECT * FROM LOC_MENSAJE WHERE ID_MENSAJE = $messageID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$mensaje = oci_fetch_array($stid,OCI_ASSOC);
				$id_mensaje = $mensaje["ID_MENSAJE"];
				$protocolID = $mensaje["ID_PROTOCOLO"];

				/* Equipo */
				$query = "SELECT ID_ORGANIZACION FROM LOC_PROTOCOLO WHERE ID_PROTOCOLO = $protocolID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$id_organizacion = $result["ID_ORGANIZACION"];

				$query = '	SELECT ID_PERSONA AS "id", NOMBRE as "name"
							FROM LOC_PERSONA
							WHERE ID_ORGANIZACION = ' . $id_organizacion;

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = array(
								"name" => "Equipo",
								"id" => 1,
							);
				$equipo = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$equipo [] = $data;

				}

				$result["children"] = $equipo;

				/* Destinos del mensaje */
				$query = "SELECT ID_PERSONA FROM LOC_MENSAJE_PERSONA WHERE ID_MENSAJE = $id_mensaje";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$destinos = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$destinos [] = $data["ID_PERSONA"];

				}

				$mensaje["DESTINOS"] = $destinos;

				$this->returnResponse(SUCCESS_RESPONSE, array($mensaje, $result));

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function getDetailsMessage2(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$messageID = $this->validateParameter('messageID', $this->param['messageID'], STRING);
			$id_usuario = $this->param['id_usuario'];

			try {

				/* Detalles del mensaje */
				$query = "SELECT * FROM LOC_MENSAJE WHERE ID_MENSAJE = $messageID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$mensaje = oci_fetch_array($stid,OCI_ASSOC);
				$id_mensaje = $mensaje["ID_MENSAJE"];
				$protocolID = $mensaje["ID_PROTOCOLO"];

				/* Equipo */
				$query = "SELECT ID_ORGANIZACION FROM LOC_PROTOCOLO WHERE ID_PROTOCOLO = $protocolID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$id_organizacion = $result["ID_ORGANIZACION"];

				$query = '	SELECT T1.ID_PERSONA AS "id", T1.NOMBRE as "name"
							FROM LOC_PERSONA T1
							INNER JOIN LOC_PERSONA_ORGANIZACION T2
							ON T1.ID_PERSONA = T2.ID_PERSONA
							WHERE T2.ID_ORGANIZACION = ' . $id_organizacion;

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = array(
								"name" => "Equipo",
								"id" => 1,
							);
				$equipo = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$equipo [] = $data;

				}

				$result["children"] = $equipo;

				/* Destinos del mensaje */
				$query = "SELECT ID_PERSONA FROM LOC_MENSAJE_PERSONA WHERE ID_MENSAJE = $id_mensaje";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$destinos = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$destinos [] = $data["ID_PERSONA"];

				}

				$mensaje["DESTINOS"] = $destinos;

				/** Validar si el usuario es administrador */
				$query = "SELECT ADMINISTRADOR FROM LOC_PERSONA_ORGANIZACION WHERE ID_PERSONA = $id_usuario AND ID_ORGANIZACION = $id_organizacion";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$resultado = oci_fetch_array($stid, OCI_ASSOC);
				$administrador = $resultado["ADMINISTRADOR"];

				$this->returnResponse(SUCCESS_RESPONSE, array($mensaje, $result, $administrador));

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}


		}

		public function editMessage(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$messageID = $this->validateParameter('messageID', $this->param['messageID'], STRING);

			$messageName = $this->validateParameter('messageName', $this->param['messageName'], STRING);

			$message = $this->validateParameter('message', $this->param['message'], STRING);

			$destinations = $this->param['personID'];

			try {

				$query = "UPDATE LOC_MENSAJE SET NOMBRE = '$messageName', MENSAJE = '$message' WHERE ID_MENSAJE = '$messageID'";
				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido actualizar";

					$this->throwError($err["code"], $str_error);

				}

				/* Registrar destinos */
				$query = "DELETE FROM LOC_MENSAJE_PERSONA WHERE ID_MENSAJE = $messageID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				foreach ($destinations as $destination) {

					if ($destination != 1) {

						$query = "INSERT INTO LOC_MENSAJE_PERSONA (ID_MENSAJE, ID_PERSONA) VALUES ($messageID, $destination)";
						$stid = oci_parse($this->dbConn, $query);
						oci_execute($stid);

					}

				}

				$this->returnResponse(SUCCESS_RESPONSE, $messageID);

			} catch (\Exception $e) {

			}

		}

		public function updateMessageState(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$messageID = $this->validateParameter('messageID', $this->param['messageID'], STRING);

			$messageState = $this->validateParameter('messageState', $this->param['messageState'], STRING);

			try {

				$query = "UPDATE LOC_MENSAJE SET ESTADO = '$messageState' WHERE ID_MENSAJE = '$messageID'";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido actualizar";

					$this->throwError($err["code"], $str_error);

				}else{

					$message = array( 'messageID' => $messageID, 'messageState' => $messageState );

					$this->returnResponse(SUCCESS_RESPONSE, $message);

				}

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}


		}

		public function searchMessages(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);

			$textSearch = $this->param['textSearch'];

			try {

				$query = "	SELECT *
							FROM LOC_MENSAJE
							WHERE ID_PROTOCOLO = $protocolID
							AND (
								UPPER(NOMBRE) LIKE UPPER('%$textSearch%')
								OR UPPER(MENSAJE) LIKE UPPER('%$textSearch%')
							)";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$actividades = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$actividades [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $actividades);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function deleteMessage(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$messageID = $this->validateParameter('messageID', $this->param['messageID'], STRING);

			try {

				$query = "DELETE FROM LOC_MENSAJE WHERE ID_MENSAJE = $messageID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$this->returnResponse(SUCCESS_RESPONSE, $messageID);

			} catch (\Exception $e) {

			}

		}

		public function deleteMessage2(){

			$id_mensajes = $this->param['id_mensajes'];

			foreach ($id_mensajes as $id_mensaje) {
				
				try {

					$query = "DELETE FROM LOC_MENSAJE WHERE ID_MENSAJE = $id_mensaje";
					$stid = oci_parse($this->dbConn, $query);
					
					if (false === oci_execute($stid)) {

						$err = oci_error($stid);
	
						$str_error = "Error al eliminar el mensaje";
	
						$this->throwError($err["code"], $str_error);
	
					}

				} catch (\Exception $e) {
	
				}

			}

			$this->returnResponse(SUCCESS_RESPONSE, $id_mensajes);

		}

		/* Protocol Activation */

		public function activateProtocol(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$protocolID = $this->validateParameter('protocolID', $this->param['protocolID'], STRING);

			try {

				$query = "INSERT INTO LOC_PROTOCOLO_ACTIVADO (ID_PROTOCOLO, FECHA_ACTIVACION) VALUES ('$protocolID', SYSDATE)";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido insertar";

					$this->throwError($err["code"], $str_error);

				}else{

					/* Obtener el ID del ultimo registro */
					$query = "SELECT ID_CORRELATIVO FROM LOC_PROTOCOLO_ACTIVADO WHERE ID_PROTOCOLO = $protocolID ORDER BY ID_CORRELATIVO ASC";

					$stid = oci_parse($this->dbConn, $query);
					oci_execute($stid);

					while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

						$registro = $data;

					}

					$id_correlativo = $registro["ID_CORRELATIVO"];

					/* Obtener personas para enviar alertas */
					$query = "	SELECT LOC_PERSONA.ID_PERSONA AS ID_PERSONA, TOKEN,
								LOC_MENSAJE.MENSAJE AS MENSAJE
								FROM LOC_PERSONA
								INNER JOIN  LOC_PROTOCOLO
								ON LOC_PERSONA.ID_ORGANIZACION = LOC_PROTOCOLO.ID_ORGANIZACION
								INNER JOIN LOC_MENSAJE
								ON LOC_PERSONA.ID_PERSONA = LOC_MENSAJE.ID_PERSONA
								WHERE LOC_PROTOCOLO.ID_PROTOCOLO = $protocolID
								AND LOC_MENSAJE.ID_PROTOCOLO = $protocolID
								AND LOC_MENSAJE.ESTADO != 'I'";

					$stid = oci_parse($this->dbConn, $query);
					oci_execute($stid);

					$personas = array();

					while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

						$personas [] = $data;

					}

					/* Por cada persona se envia alerta y se inserta en tabla de log */
					foreach ($personas as $persona) {

						$id_persona = $persona["ID_PERSONA"];
						$token = $persona["TOKEN"];
						$mensaje = $persona["MENSAJE"];

						/* Si tiene token enviar la notificacion */
						if ($token != '') {

							/* Enviar notificacion */
							$result = $this->send_notification($token, $mensaje);

							$success = $result["success"];

							/* Se almacena el resultado del envio de la notificacion */
							$results = $result["results"];

							if (array_key_exists("error", $results[0])) {

								$message_id = $results[0]["error"];

							}elseif (array_key_exists("message_id", $results[0])) {

								$message_id = $results[0]["message_id"];

							}

							/* Registrar en log */
							$query = "INSERT INTO LOC_ALERTA_LOG (ID_CORRELATIVO, ID_PERSONA, FECHA_ENVIO, RESULTADO, ID_MENSAJE, MENSAJE) VALUES ('$id_correlativo', '$id_persona', SYSDATE, $success, '$message_id', '$mensaje')";

						}else{

							/* Registrar en log */
							$query = "INSERT INTO LOC_ALERTA_LOG (ID_CORRELATIVO, ID_PERSONA, FECHA_ENVIO, RESULTADO) VALUES ('$id_correlativo', '$id_persona', SYSDATE, 'No tiene token o es invalido por lo que no se puede enviar notificación')";

						}

						$stid = oci_parse($this->dbConn, $query);
						oci_execute($stid);

					}

					/* Registrar las actividades del incidente */
					$query = "	INSERT INTO LOC_ACTIVIDADES_INCIDENTE (ID_INCIDENTE, ID_ACTIVIDAD)
								SELECT $id_correlativo AS ID_INCIDENTE, ID_ACTIVIDAD
								FROM LOC_ACTIVIDADES
								WHERE ID_PROTOCOLO = (

								    SELECT ID_PROTOCOLO
								    FROM LOC_PROTOCOLO_ACTIVADO
								    WHERE ID_CORRELATIVO = $id_correlativo

								)";

					$stid = oci_parse($this->dbConn, $query);

					if (false === oci_execute($stid)) {

						$err = oci_error($stid);

						$str_error = "Error al registrar las actividades";

						$this->throwError($err["code"], $str_error);

					}

					/* Obtener cantidad de personas a las que se notifico */
					$query = "	SELECT COUNT(*) AS TOTAL
								FROM LOC_ALERTA_LOG
								WHERE ID_CORRELATIVO = $id_correlativo
								AND RESULTADO = 1";

					$stid = oci_parse($this->dbConn, $query);
					oci_execute($stid);

					$total = oci_fetch_array($stid);

					$this->returnResponse(SUCCESS_RESPONSE, $total["TOTAL"]);

				}

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function send_notification($registatoin_ids, $message) {

	        // variable post http://developer.android.com/google/gcm/http.html#auth
	       // $url = 'https://gcm-http.googleapis.com/gcm/send';

		   	$priority = array("priority" => "high", "priority" => 10);

		    $url = 'https://fcm.googleapis.com/fcm/send';
			$noti = array(
				'title' => 'AVE',
				'body' =>$message,
				'sound' => "default",
				'soundname' => "default",
				'color' => '#E91E63',
				'icon' => 'myicon',
				'vibrate' => 1,
				'flash'=> 1,
				'android' => $priority,
				'priority' => 'high',
			);
	        $fields = array(
	            'to' => $registatoin_ids,
				'priority' => 'high',
				'notification' => $noti,
				'data' => $priority


	        );
	        $headers = array(
	            'Authorization: key=AAAAzGfDS3Y:APA91bFAWsonvEE8PWArI8owZBbdsM0x9hCdvwz0oyXxUutS5lcfysn_irH7ZTty_a1_9PwSqiB4xl7BVeCFVqv7BcmxAAs_ORlir18eYoVClR-XgNtpPSN1UrUK_x5qcJ80lmo9eBBl',
	            'Content-Type: application/json'
	        );

	        // abriendo la conexion
	        $ch = curl_init();

	        // Set the url, number of POST vars, POST data
	        curl_setopt($ch, CURLOPT_URL, $url);
	        curl_setopt($ch, CURLOPT_POST, true);
	        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	        // Deshabilitamos soporte de certificado SSL temporalmente
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

	        // ejecutamos el post

	        $result = curl_exec($ch);

	        if ($result === FALSE) {

	            //die('Curl failed: ' . curl_error($ch));

	        }else{

	        	//echo $result;

			}

			$json = json_decode($result, true);

	        // Cerramos la conexion
	        curl_close($ch);

	       	return $json;

	    }

		public function getActivesProtocols(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			/* Obtener el ID de la organizacion a la que pertenece */
			$query = "SELECT ID_ORGANIZACION FROM LOC_PERSONA WHERE ID_PERSONA = '$userID'";

			$stid = oci_parse($this->dbConn, $query);
			oci_execute($stid);

			$persona = oci_fetch_array($stid, OCI_ASSOC);
			$id_organizacion = $persona["ID_ORGANIZACION"];

			try {

				$query = "SELECT * FROM LOC_PROTOCOLO WHERE ID_ORGANIZACION = '$id_organizacion' AND ESTADO != 'I' ORDER BY ID_PROTOCOLO DESC";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$protocolos = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$protocolos [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $protocolos);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function getActivesProtocols2(){

			// $db = new Db();
			// $this->dbConn = $db->connect();

			// $userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			/* Obtener el ID de la organizacion a la que pertenece */
			// $query = "SELECT ID_ORGANIZACION FROM LOC_PERSONA WHERE ID_PERSONA = '$userID'";

			// $stid = oci_parse($this->dbConn, $query);
			// oci_execute($stid);

			// $persona = oci_fetch_array($stid, OCI_ASSOC);
			// $id_organizacion = $persona["ID_ORGANIZACION"];

			// try {

			// 	$query = "SELECT * FROM LOC_PROTOCOLO WHERE ID_ORGANIZACION = '$id_organizacion' AND ESTADO != 'I' ORDER BY ID_PROTOCOLO DESC";

			// 	$stid = oci_parse($this->dbConn, $query);
			// 	oci_execute($stid);

			// 	$protocolos = array();

			// 	while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

			// 		$protocolos [] = $data;

			// 	}

			// 	$this->returnResponse(SUCCESS_RESPONSE, $protocolos);

			// } catch (\Exception $e) {

			// 	$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			// }

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			try {

				$query = "	SELECT T1.ID_ORGANIZACION, T1.NOMBRE
							FROM LOC_ORGANIZACION T1
							INNER JOIN LOC_PERSONA_ORGANIZACION T2
							ON T1.ID_ORGANIZACION = T2.ID_ORGANIZACION
							WHERE T2.ID_PERSONA = $userID";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$datos = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
				
					$titulo = $data["NOMBRE"];

					/* Buscar los protocolos */
					$id_organizacion = $data["ID_ORGANIZACION"];

					$query = "SELECT * FROM LOC_PROTOCOLO WHERE ID_ORGANIZACION = '$id_organizacion' AND ESTADO != 'I' ORDER BY ID_PROTOCOLO DESC";

					$stid2 = oci_parse($this->dbConn, $query);
					oci_execute($stid2);

					$protocolos = array();

					while ($data2 = oci_fetch_array($stid2,OCI_ASSOC)) {

						$protocolos [] = $data2;

					}

					if ($protocolos) {
					
						$item = array("title" => $titulo, "content" => $protocolos);
						$datos [] = $item;

					}

				}

				$this->returnResponse(SUCCESS_RESPONSE, $datos);

			} catch (\Throwable $th) {
				
				$this->throwError(JWT_PROCESSING_ERROR, $th->getMessage());

			}

		}

		public function administradorEquipo(){

			$id_correlativo = $this->validateParameter('id_correlativo', $this->param['id_correlativo'], STRING);

			$id_usuario = $this->validateParameter('id_usuario', $this->param['id_usuario'], STRING);

			try {
				
				$query = "";

			} catch (\Throwable $th) {
				


			}

			$this->returnResponse(SUCCESS_RESPONSE, $id_correlativo);

		}

		/* Notifications */

		public function getNotifications(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			try {

				$query = "SELECT LOC_ALERTA_LOG.ID_LOG AS ID_LOG, TO_CHAR(LOC_ALERTA_LOG.FECHA_ENVIO, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ENVIO,
				LOC_ALERTA_LOG.FECHA_LECTURA AS FECHA_LECTURA, LOC_ALERTA_LOG.MENSAJE AS MENSAJE, LOC_ALERTA_LOG.ID_MENSAJE AS ID_MENSAJE, LOC_PROTOCOLO.NOMBRE AS PROTOCOLO
				FROM LOC_ALERTA_LOG
				LEFT JOIN LOC_PROTOCOLO_ACTIVADO
				ON LOC_ALERTA_LOG.ID_CORRELATIVO = LOC_PROTOCOLO_ACTIVADO.ID_CORRELATIVO
				LEFT JOIN LOC_PROTOCOLO
				ON LOC_PROTOCOLO_ACTIVADO.ID_PROTOCOLO = LOC_PROTOCOLO.ID_PROTOCOLO
				WHERE LOC_ALERTA_LOG.ID_PERSONA = $userID AND LOC_ALERTA_LOG.FECHA_LECTURA IS NULL ORDER BY ID_LOG DESC";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$notifications = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$notifications [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $notifications);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function getNotifications2(){

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			try {

				/* Busqueda de notificaciones */

				$query = "SELECT LOC_ALERTA_LOG.ID_LOG AS ID_LOG, TO_CHAR(LOC_ALERTA_LOG.FECHA_ENVIO, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ENVIO,
				LOC_ALERTA_LOG.FECHA_LECTURA AS FECHA_LECTURA, LOC_ALERTA_LOG.MENSAJE AS MENSAJE, LOC_ALERTA_LOG.ID_MENSAJE AS ID_MENSAJE, LOC_PROTOCOLO.NOMBRE AS PROTOCOLO
				FROM LOC_ALERTA_LOG
				LEFT JOIN LOC_PROTOCOLO_ACTIVADO
				ON LOC_ALERTA_LOG.ID_CORRELATIVO = LOC_PROTOCOLO_ACTIVADO.ID_CORRELATIVO
				LEFT JOIN LOC_PROTOCOLO
				ON LOC_PROTOCOLO_ACTIVADO.ID_PROTOCOLO = LOC_PROTOCOLO.ID_PROTOCOLO
				WHERE LOC_ALERTA_LOG.ID_PERSONA = $userID AND LOC_ALERTA_LOG.FECHA_LECTURA IS NULL ORDER BY ID_LOG DESC";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$notifications = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$notifications [] = $data;

				}

				/** Busqueda de invitaciones */
				$query = "	SELECT T1.*, T2.NOMBRE AS PERSONA_INVITA, T2.AVATAR AS AVATAR, 
							T3.NOMBRE AS ORGANIZACION
							FROM LOC_INVITACION_EQUIPO T1
							INNER JOIN LOC_PERSONA T2
							ON T1.ID_PERSONA_INVITA = T2.ID_PERSONA
							INNER JOIN LOC_ORGANIZACION T3 
							ON T1.ID_ORGANIZACION = T3.ID_ORGANIZACION
							WHERE T1.ID_PERSONA = $userID
							AND T1.ESTADO = 1";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$invitaciones = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					if(array_key_exists('AVATAR', $data)){

						$avatar = $data["AVATAR"];
						$photo = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar);
						$data["AVATAR"] = $photo;

					}else{

						$data["AVATAR"] = "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";

					}

					$invitaciones [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, array($notifications, $invitaciones));

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function getNotificationsReaded(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			try {

				$query = "SELECT LOC_ALERTA_LOG.ID_LOG AS ID_LOG, TO_CHAR(LOC_ALERTA_LOG.FECHA_ENVIO, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ENVIO,
				LOC_ALERTA_LOG.FECHA_LECTURA AS FECHA_LECTURA, LOC_ALERTA_LOG.MENSAJE AS MENSAJE, LOC_PROTOCOLO.NOMBRE AS PROTOCOLO
				FROM LOC_ALERTA_LOG
				LEFT JOIN LOC_PROTOCOLO_ACTIVADO
				ON LOC_ALERTA_LOG.ID_CORRELATIVO = LOC_PROTOCOLO_ACTIVADO.ID_CORRELATIVO
				LEFT JOIN LOC_PROTOCOLO
				ON LOC_PROTOCOLO_ACTIVADO.ID_PROTOCOLO = LOC_PROTOCOLO.ID_PROTOCOLO
				WHERE LOC_ALERTA_LOG.ID_PERSONA = $userID AND LOC_ALERTA_LOG.FECHA_LECTURA IS NOT NULL AND LOC_ALERTA_LOG.ELIMINADA IS NULL ORDER BY ID_LOG DESC";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$notifications = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$notifications [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $notifications);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function setNotificationReaded(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$logID = $this->validateParameter('logID', $this->param['logID'], STRING);

			try {

				$query = "UPDATE LOC_ALERTA_LOG SET FECHA_RECEPCION = SYSDATE, FECHA_LECTURA = SYSDATE WHERE ID_LOG = $logID";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido actualizar";

					$this->throwError($err["code"], $str_error);

				}else{

					$this->returnResponse(SUCCESS_RESPONSE, $logID);

				}

			} catch (\Exception $e) {

			}


		}

		public function getNotification(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$notificationID = $this->validateParameter('notificationID', $this->param['notificationID'], STRING);

			try {

				$query = "	SELECT LOC_ALERTA_LOG.ID_LOG, LOC_ALERTA_LOG.ID_CORRELATIVO, TO_CHAR(LOC_ALERTA_LOG.FECHA_ENVIO, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ENVIO,
							LOC_ALERTA_LOG.FECHA_LECTURA, LOC_ALERTA_LOG.MENSAJE, LOC_PROTOCOLO.NOMBRE
							FROM LOC_ALERTA_LOG
							LEFT JOIN LOC_PROTOCOLO_ACTIVADO
							ON LOC_ALERTA_LOG.ID_CORRELATIVO = LOC_PROTOCOLO_ACTIVADO.ID_CORRELATIVO
							LEFT JOIN LOC_PROTOCOLO
							ON LOC_PROTOCOLO_ACTIVADO.ID_PROTOCOLO = LOC_PROTOCOLO.ID_PROTOCOLO
							WHERE LOC_ALERTA_LOG.ID_MENSAJE = '$notificationID'";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$notification = oci_fetch_array($stid, OCI_ASSOC);

				$this->returnResponse(SUCCESS_RESPONSE, $notification);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}


		}

		public function getNumberNotifications(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			try {

				$query = "	SELECT COUNT(*) AS TOTAL
							FROM LOC_ALERTA_LOG
							WHERE ID_PERSONA = $userID
							AND FECHA_LECTURA IS NULL";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$total = oci_fetch_array($stid, OCI_ASSOC);

				/** Invitaciones pendientes */
				$query = "	SELECT COUNT(*) AS INVITACIONES_PENDIENTES
							FROM LOC_INVITACION_EQUIPO
							WHERE ID_PERSONA = $userID
							AND ESTADO = 1";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$invitaciones_pendientes = oci_fetch_array($stid, OCI_ASSOC);			

				$total["TOTAL"] = intval($total["TOTAL"]) + intval($invitaciones_pendientes["INVITACIONES_PENDIENTES"]);

				/** Incidentes En Curso */
				$query = "	SELECT COUNT(*) AS EN_CURSO
							FROM LOC_PERSONA_ORGANIZACION T1 
							INNER JOIN LOC_PROTOCOLO T2
							ON T1.ID_ORGANIZACION = T2.ID_ORGANIZACION
							INNER JOIN LOC_PROTOCOLO_ACTIVADO T3
							ON T2.ID_PROTOCOLO = T3.ID_PROTOCOLO
							WHERE T3.FECHA_FINALIZACION IS NULL
							AND T1.ID_PERSONA = $userID";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$incidentes_en_curso = oci_fetch_array($stid, OCI_ASSOC);	

				$total["EN_CURSO"] = intval($incidentes_en_curso["EN_CURSO"]);

				$this->returnResponse(SUCCESS_RESPONSE, $total);

			} catch (\Exception $e) {

			}


		}

		public function limpiarNotificaciones(){

			$id_usuario = $this->validateParameter('id_usuario', $this->param['id_usuario'], STRING);

			try {
				
				//En lugar de eliminarlas se colocara una bandera para indicar que fue marcada como eliminada

				/* $query = "DELETE FROM LOC_ALERTA_LOG WHERE ID_PERSONA = $id_usuario AND FECHA_LECTURA IS NOT NULL"; */

				$query = "UPDATE LOC_ALERTA_LOG SET ELIMINADA = 'S' WHERE ID_PERSONA = $id_usuario AND FECHA_LECTURA IS NOT NULL ";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "Error al eliminar las notificaciones";

					$this->throwError($err["code"], $str_error);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $id_usuario);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

		/* Offline Content */

		public function getUserMessages(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			try {

				$query = "	SELECT LOC_MENSAJE_PERSONA.ID_MENSAJE, MENSAJE
							FROM LOC_MENSAJE_PERSONA
							INNER JOIN LOC_MENSAJE
							ON LOC_MENSAJE_PERSONA.ID_MENSAJE = LOC_MENSAJE.ID_MENSAJE
							WHERE LOC_MENSAJE_PERSONA.ID_PERSONA = $userID";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$mensajes = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$mensajes [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $mensajes);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function contenidoSinConexion(){

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			$contenido_sin_conexion = array();

			try {
				
				/** Buscar los equipos del usuario */
				$query = "	SELECT ID_ORGANIZACION
							FROM LOC_PERSONA_ORGANIZACION 
							WHERE ID_PERSONA = $userID";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$equipos = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {
					
					$id_organizacion = $data["ID_ORGANIZACION"];

					/** Buscar protocolos por cada una de las organizaciones */
					$query = "	SELECT *
								FROM LOC_PROTOCOLO
								WHERE ID_ORGANIZACION = $id_organizacion";

					$stid2 = oci_parse($this->dbConn, $query);
					oci_execute($stid2);

					$protocolos = array();

					while ($data2 = oci_fetch_array($stid2, OCI_ASSOC)) {
						
						/** Por cada protocolo buscar si se tienen actividades o mensajes */
						$id_protocolo = $data2["ID_PROTOCOLO"];

						/** Actividades */
						$query = "	SELECT *
									FROM LOC_ACTIVIDADES
									WHERE ID_RESPONSABLE = $userID AND ID_PROTOCOLO = $id_protocolo";

						$stid3 = oci_parse($this->dbConn, $query);
						oci_execute($stid3);

						$actividades = array();

						while ($data3 = oci_fetch_array($stid3, OCI_ASSOC)) {
							
							$actividades [] = $data3;

						}

						$data2["ACTIVIDADES"] = $actividades;

						/** Mensajes */
						$query = "	SELECT T2.*
									FROM LOC_MENSAJE_PERSONA T1
									INNER JOIN LOC_MENSAJE T2
									ON T1.ID_MENSAJE = T2.ID_MENSAJE
									WHERE T1.ID_PERSONA = $userID AND T2.ID_PROTOCOLO = $id_protocolo";

						$stid3 = oci_parse($this->dbConn, $query);
						oci_execute($stid3);

						$mensajes = array();

						while ($data4 = oci_fetch_array($stid3, OCI_ASSOC)) {
							
							$mensajes [] = $data4;

						}

						$data2["MENSAJES"] = $mensajes;

						if (count($data2["MENSAJES"]) > 0 || count($data2["ACTIVIDADES"]) > 0) {
							
							$contenido_sin_conexion [] = $data2;

						}

					}

					/** Agregar unicamente los protocolos que tengan actividades y mensajes */

					$data["PROTOCOLOS"] = $protocolos;

					$equipos [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $contenido_sin_conexion);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

		/* Incidents */

		public function getIncidents(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);
			$ended = $this->param['ended'];

			/* Obtener el ID de la organizacion a la que pertenece */
			$query = "SELECT ID_ORGANIZACION FROM LOC_PERSONA WHERE ID_PERSONA = '$userID'";

			$stid = oci_parse($this->dbConn, $query);
			oci_execute($stid);

			$persona = oci_fetch_array($stid, OCI_ASSOC);
			$id_organizacion = $persona["ID_ORGANIZACION"];

			try {

				if ($ended) {

					$query = "	SELECT LOC_PROTOCOLO_ACTIVADO.ID_CORRELATIVO,
								TO_CHAR(LOC_PROTOCOLO_ACTIVADO.FECHA_ACTIVACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ACTIVACION, LOC_PROTOCOLO.NOMBRE AS NOMBRE,
								TO_CHAR(LOC_PROTOCOLO_ACTIVADO.FECHA_FINALIZACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_FINALIZACION, LOC_PROTOCOLO_ACTIVADO.EVACUADOS, LOC_PROTOCOLO_ACTIVADO.HERIDOS, LOC_PROTOCOLO_ACTIVADO.FALLECIDOS, LOC_PROTOCOLO_ACTIVADO.NOTAS
								FROM LOC_PROTOCOLO_ACTIVADO
								INNER JOIN LOC_PROTOCOLO
								ON LOC_PROTOCOLO_ACTIVADO.ID_PROTOCOLO = LOC_PROTOCOLO.ID_PROTOCOLO
								WHERE LOC_PROTOCOLO_ACTIVADO.ID_PROTOCOLO IN (
								    SELECT ID_PROTOCOLO FROM LOC_PROTOCOLO
								    WHERE ID_ORGANIZACION = '$id_organizacion'
								)
								AND LOC_PROTOCOLO_ACTIVADO.FECHA_FINALIZACION IS NOT NULL
								ORDER BY LOC_PROTOCOLO_ACTIVADO.ID_CORRELATIVO DESC";

				}else{

					$query = "	SELECT LOC_PROTOCOLO_ACTIVADO.ID_CORRELATIVO,
								TO_CHAR(LOC_PROTOCOLO_ACTIVADO.FECHA_ACTIVACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ACTIVACION, LOC_PROTOCOLO.NOMBRE AS NOMBRE,
								TO_CHAR(LOC_PROTOCOLO_ACTIVADO.FECHA_FINALIZACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_FINALIZACION
								FROM LOC_PROTOCOLO_ACTIVADO
								INNER JOIN LOC_PROTOCOLO
								ON LOC_PROTOCOLO_ACTIVADO.ID_PROTOCOLO = LOC_PROTOCOLO.ID_PROTOCOLO
								WHERE LOC_PROTOCOLO_ACTIVADO.ID_PROTOCOLO IN (
								    SELECT ID_PROTOCOLO FROM LOC_PROTOCOLO
								    WHERE ID_ORGANIZACION = '$id_organizacion'
								)
								AND LOC_PROTOCOLO_ACTIVADO.FECHA_FINALIZACION IS NULL
								ORDER BY LOC_PROTOCOLO_ACTIVADO.ID_CORRELATIVO DESC";

				}



				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$incidentes = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$id_incidente = $data["ID_CORRELATIVO"];

					/* Total de Actividades */
					$query = "	SELECT COUNT(*) AS TOTAL_ACTIVIDADES
								FROM LOC_ACTIVIDADES_INCIDENTE
								WHERE ID_INCIDENTE = '$id_incidente'";

					$stid_ = oci_parse($this->dbConn, $query);
					oci_execute($stid_);

					$total_actividades = oci_fetch_array($stid_, OCI_ASSOC);

					/* Actividades realizadas */
					$query = "	SELECT COUNT(*) AS TOTAL_ACTIVIDADES
								FROM LOC_ACTIVIDADES_INCIDENTE
								WHERE ID_INCIDENTE = '$id_incidente'
								AND FECHA_FINALIZACION IS NOT NULL";

					$stid_ = oci_parse($this->dbConn, $query);
					oci_execute($stid_);

					$total_actividades_realizadas = oci_fetch_array($stid_, OCI_ASSOC);

					/* Actividades Pendientes */
					$query = "	SELECT COUNT(*) AS TOTAL_ACTIVIDADES
								FROM LOC_ACTIVIDADES_INCIDENTE
								WHERE ID_INCIDENTE = '$id_incidente'
								AND FECHA_FINALIZACION IS NULL";

					$stid_ = oci_parse($this->dbConn, $query);
					oci_execute($stid_);

					$total_actividades_pendientes = oci_fetch_array($stid_, OCI_ASSOC);

					if ($total_actividades["TOTAL_ACTIVIDADES"] > $total_actividades_pendientes["TOTAL_ACTIVIDADES"]) {

						$porcentaje = 100 - round(($total_actividades_pendientes["TOTAL_ACTIVIDADES"] / $total_actividades["TOTAL_ACTIVIDADES"]) * 100);

					}else if($total_actividades["TOTAL_ACTIVIDADES"] == $total_actividades_realizadas["TOTAL_ACTIVIDADES"]){

						$porcentaje = 100;

					}else if($total_actividades["TOTAL_ACTIVIDADES"] == $total_actividades_pendientes["TOTAL_ACTIVIDADES"]){

						$porcentaje = 0;

					}

					$data["PORCENTAJE"] = $porcentaje;
					$data["ACTIVIDADES_PENDIENTES"] = $total_actividades_pendientes["TOTAL_ACTIVIDADES"];
					$data["TOTAL_ACTIVIDADES"] = $total_actividades["TOTAL_ACTIVIDADES"];
					$data["ACTIVIDADES_REALIZADAS"] = $total_actividades_realizadas["TOTAL_ACTIVIDADES"];

					$incidentes [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $incidentes);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function getIncidents2(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);
			$ended = $this->param['ended'];

			$query = "	SELECT T2.ID_ORGANIZACION
						FROM LOC_PERSONA T1
						INNER JOIN LOC_PERSONA_ORGANIZACION T2
						ON T1.ID_PERSONA = T2.ID_PERSONA
						WHERE T2.ID_PERSONA = $userID";
						
			$stid = oci_parse($this->dbConn, $query);
			oci_execute($stid);

			$equipos = array();

			while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
			
				$equipos [] = $data["ID_ORGANIZACION"];

			}

			$str = implode (", ", $equipos);

			try {

				if ($ended) {

					// Finalizados

					$query = "	SELECT LOC_PROTOCOLO_ACTIVADO.ID_CORRELATIVO,
								TO_CHAR(LOC_PROTOCOLO_ACTIVADO.FECHA_ACTIVACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ACTIVACION, LOC_PROTOCOLO.NOMBRE AS NOMBRE, LOC_PROTOCOLO.ID_PROTOCOLO,
								TO_CHAR(LOC_PROTOCOLO_ACTIVADO.FECHA_FINALIZACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_FINALIZACION, LOC_PROTOCOLO_ACTIVADO.EVACUADOS, LOC_PROTOCOLO_ACTIVADO.HERIDOS, LOC_PROTOCOLO_ACTIVADO.FALLECIDOS, LOC_PROTOCOLO_ACTIVADO.NOTAS, LOC_PERSONA.NOMBRE AS ACTIVADO_POR
								FROM LOC_PROTOCOLO_ACTIVADO
								INNER JOIN LOC_PROTOCOLO
								ON LOC_PROTOCOLO_ACTIVADO.ID_PROTOCOLO = LOC_PROTOCOLO.ID_PROTOCOLO
								LEFT JOIN LOC_PERSONA
                                ON LOC_PROTOCOLO_ACTIVADO.ACTIVADO_POR = LOC_PERSONA.ID_PERSONA
								WHERE LOC_PROTOCOLO_ACTIVADO.ID_PROTOCOLO IN (
								    SELECT ID_PROTOCOLO FROM LOC_PROTOCOLO
								    WHERE ID_ORGANIZACION IN ($str)
								)
								AND LOC_PROTOCOLO_ACTIVADO.FECHA_FINALIZACION IS NOT NULL
								ORDER BY LOC_PROTOCOLO_ACTIVADO.ID_CORRELATIVO DESC";

				}else{

					// En Curso

					$query = "	SELECT LOC_PROTOCOLO_ACTIVADO.ID_CORRELATIVO,
								TO_CHAR(LOC_PROTOCOLO_ACTIVADO.FECHA_ACTIVACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ACTIVACION, LOC_PROTOCOLO.NOMBRE AS NOMBRE, LOC_PROTOCOLO.ID_PROTOCOLO,
								TO_CHAR(LOC_PROTOCOLO_ACTIVADO.FECHA_FINALIZACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_FINALIZACION, LOC_PERSONA.NOMBRE AS ACTIVADO_POR
								FROM LOC_PROTOCOLO_ACTIVADO
								INNER JOIN LOC_PROTOCOLO
								ON LOC_PROTOCOLO_ACTIVADO.ID_PROTOCOLO = LOC_PROTOCOLO.ID_PROTOCOLO
								LEFT JOIN LOC_PERSONA
                                ON LOC_PROTOCOLO_ACTIVADO.ACTIVADO_POR = LOC_PERSONA.ID_PERSONA
								WHERE LOC_PROTOCOLO_ACTIVADO.ID_PROTOCOLO IN (
								    SELECT ID_PROTOCOLO FROM LOC_PROTOCOLO
								    WHERE ID_ORGANIZACION IN ($str)
								)
								AND LOC_PROTOCOLO_ACTIVADO.FECHA_FINALIZACION IS NULL
								ORDER BY LOC_PROTOCOLO_ACTIVADO.ID_CORRELATIVO DESC";

				}

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$incidentes = array();

				while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

					$id_incidente = $data["ID_CORRELATIVO"];
					$id_protocolo = $data["ID_PROTOCOLO"];

					/* Total de Actividades */
					$query = "	SELECT COUNT(*) AS TOTAL_ACTIVIDADES
								FROM LOC_ACTIVIDADES_INCIDENTE
								WHERE ID_INCIDENTE = '$id_incidente'";

					$stid_ = oci_parse($this->dbConn, $query);
					oci_execute($stid_);

					$total_actividades = oci_fetch_array($stid_, OCI_ASSOC);

					/* Actividades realizadas */
					$query = "	SELECT COUNT(*) AS TOTAL_ACTIVIDADES
								FROM LOC_ACTIVIDADES_INCIDENTE
								WHERE ID_INCIDENTE = '$id_incidente'
								AND FECHA_FINALIZACION IS NOT NULL";

					$stid_ = oci_parse($this->dbConn, $query);
					oci_execute($stid_);

					$total_actividades_realizadas = oci_fetch_array($stid_, OCI_ASSOC);

					/* Actividades Pendientes */
					$query = "	SELECT COUNT(*) AS TOTAL_ACTIVIDADES
								FROM LOC_ACTIVIDADES_INCIDENTE
								WHERE ID_INCIDENTE = '$id_incidente'
								AND FECHA_FINALIZACION IS NULL";

					$stid_ = oci_parse($this->dbConn, $query);
					oci_execute($stid_);

					$total_actividades_pendientes = oci_fetch_array($stid_, OCI_ASSOC);

					if ($total_actividades["TOTAL_ACTIVIDADES"] > $total_actividades_pendientes["TOTAL_ACTIVIDADES"]) {

						$porcentaje = 100 - round(($total_actividades_pendientes["TOTAL_ACTIVIDADES"] / $total_actividades["TOTAL_ACTIVIDADES"]) * 100);

					}else if($total_actividades["TOTAL_ACTIVIDADES"] == $total_actividades_realizadas["TOTAL_ACTIVIDADES"]){

						$porcentaje = 100;

					}else if($total_actividades["TOTAL_ACTIVIDADES"] == $total_actividades_pendientes["TOTAL_ACTIVIDADES"]){

						$porcentaje = 0;

					}

					//Validar si es administrador

					$query = "	SELECT ADMINISTRADOR 
								FROM LOC_PERSONA_ORGANIZACION 
								WHERE ID_PERSONA = $userID
								AND ID_ORGANIZACION = (
									SELECT ID_ORGANIZACION 
									FROM LOC_PROTOCOLO 
									WHERE ID_PROTOCOLO = $id_protocolo
								)";

					$stid_ = oci_parse($this->dbConn, $query);
					oci_execute($stid_);

					$administrador = oci_fetch_array($stid_, OCI_ASSOC);

					$data["PORCENTAJE"] = $porcentaje;
					$data["ACTIVIDADES_PENDIENTES"] = $total_actividades_pendientes["TOTAL_ACTIVIDADES"];
					$data["TOTAL_ACTIVIDADES"] = $total_actividades["TOTAL_ACTIVIDADES"];
					$data["ACTIVIDADES_REALIZADAS"] = $total_actividades_realizadas["TOTAL_ACTIVIDADES"];

					if ($administrador) {
						
						$data["ADMINISTRADOR"] = true;

					}

					$incidentes [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $incidentes);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}


		}

		public function getIncidentsEnded(){
		}

		public function getUserActivities(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			$incidentID = $this->validateParameter('incidentID', $this->param['incidentID'], STRING);

			try {

				/* Obtener informacion del incidente */
				$query = "SELECT FECHA_FINALIZACION FROM LOC_PROTOCOLO_ACTIVADO WHERE ID_CORRELATIVO = $incidentID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$incidente = oci_fetch_array($stid, OCI_ASSOC);

				$protocolo_finalizado = false;

				if ($incidente) {

					$protocolo_finalizado = true;

				}

				$query = "	SELECT LOC_ACTIVIDADES_INCIDENTE.ID_CORRELATIVO, LOC_ACTIVIDADES_INCIDENTE.ID_INCIDENTE,
							TO_CHAR(LOC_ACTIVIDADES_INCIDENTE.FECHA_FINALIZACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_FINALIZACION, LOC_ACTIVIDADES_INCIDENTE.COMENTARIO,
							LOC_ACTIVIDADES.NOMBRE, LOC_ACTIVIDADES.DESCRIPCION
							FROM LOC_ACTIVIDADES_INCIDENTE
							INNER JOIN LOC_ACTIVIDADES
							ON LOC_ACTIVIDADES_INCIDENTE.ID_ACTIVIDAD = LOC_ACTIVIDADES.ID_ACTIVIDAD
							WHERE ID_INCIDENTE = '$incidentID'
							AND LOC_ACTIVIDADES_INCIDENTE.ID_ACTIVIDAD IN (

							    SELECT ID_ACTIVIDAD
							    FROM LOC_ACTIVIDADES
							    WHERE ID_RESPONSABLE = '$userID'

							)";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$actividades = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$actividades [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, array($actividades, $protocolo_finalizado));

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}


		}

		public function editUserActivity(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$activityID = $this->validateParameter('activityID', $this->param['activityID'], STRING);

			try {

				$query = "SELECT TO_CHAR(FECHA_FINALIZACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_FINALIZACION, COMENTARIO FROM LOC_ACTIVIDADES_INCIDENTE WHERE ID_CORRELATIVO = $activityID";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$actividad = oci_fetch_array($stid, OCI_ASSOC);

				$this->returnResponse(SUCCESS_RESPONSE, $actividad);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}


		}

		public function updateUserActivity(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$activityID = $this->validateParameter('activityID', $this->param['activityID'], STRING);

			$fecha_finalizacion = $this->param['fecha_finalizacion'];

			$comentario = $this->param['comentario'];

			try {

				if ($fecha_finalizacion) {

					$query = "UPDATE LOC_ACTIVIDADES_INCIDENTE SET COMENTARIO = '$comentario' WHERE ID_CORRELATIVO = '$activityID'";

				}else{

					$query = "UPDATE LOC_ACTIVIDADES_INCIDENTE SET COMENTARIO = '$comentario', FECHA_FINALIZACION = SYSDATE WHERE ID_CORRELATIVO = '$activityID'";

				}

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido actualizar";

					$this->throwError($err["code"], $str_error);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $activityID);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}

		}

		public function getIncidentActivities(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$incidentID = $this->validateParameter('incidentID', $this->param['incidentID'], STRING);

			try {

				$query = "	SELECT LOC_ACTIVIDADES_INCIDENTE.ID_CORRELATIVO, LOC_ACTIVIDADES_INCIDENTE.ID_INCIDENTE, LOC_ACTIVIDADES_INCIDENTE.ID_ACTIVIDAD, TO_CHAR(LOC_ACTIVIDADES_INCIDENTE.FECHA_FINALIZACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_FINALIZACION, LOC_ACTIVIDADES_INCIDENTE.COMENTARIO, LOC_ACTIVIDADES.NOMBRE, LOC_ACTIVIDADES.DESCRIPCION,
							LOC_PERSONA.NOMBRE AS PERSONA
							FROM LOC_ACTIVIDADES_INCIDENTE
							INNER JOIN LOC_ACTIVIDADES
							ON LOC_ACTIVIDADES_INCIDENTE.ID_ACTIVIDAD = LOC_ACTIVIDADES.ID_ACTIVIDAD
							INNER JOIN LOC_PERSONA
							ON LOC_ACTIVIDADES.ID_RESPONSABLE = LOC_PERSONA.ID_PERSONA
							WHERE ID_INCIDENTE = '$incidentID'";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$actividades = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$actividades [] = $data;

				}

				$this->returnResponse(SUCCESS_RESPONSE, $actividades);

			} catch (\Exception $e) {

				$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

			}


		}

		public function endProtocol(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$incidentID = $this->validateParameter('id_incidente', $this->param['id_incidente'], STRING);
			// $evacuados = $this->param['evacuados'];
			// $heridos = $this->param['heridos'];
			// $fallecidos = $this->param['fallecidos'];
			// $notas = $this->param['notas'];

			try {

				$query = "UPDATE LOC_PROTOCOLO_ACTIVADO SET FECHA_FINALIZACION = SYSDATE WHERE ID_CORRELATIVO = $incidentID";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "No se ha podido actualizar";

					$this->throwError($err["code"], $str_error);

				}else{

					$this->returnResponse(SUCCESS_RESPONSE, $incidentID);

				}

			} catch (\Exception $e) {

			}


		}

		public function simulacro_zona9(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$id_protocolos = array(401, 402, 403, 404, 405);
			$total_notificaciones = 0;

			foreach ($id_protocolos as $protocolID) {

				try {

					$query = "INSERT INTO LOC_PROTOCOLO_ACTIVADO (ID_PROTOCOLO, FECHA_ACTIVACION) VALUES ('$protocolID', SYSDATE)";

					$stid = oci_parse($this->dbConn, $query);

					if (false === oci_execute($stid)) {

						$err = oci_error($stid);

						$str_error = "No se ha podido insertar";

						$this->throwError($err["code"], $str_error);

					}else{

						/* Obtener el ID del ultimo registro */
						$query = "SELECT ID_CORRELATIVO FROM LOC_PROTOCOLO_ACTIVADO WHERE ID_PROTOCOLO = $protocolID ORDER BY ID_CORRELATIVO ASC";

						$stid = oci_parse($this->dbConn, $query);
						oci_execute($stid);

						while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

							$registro = $data;

						}

						$id_correlativo = $registro["ID_CORRELATIVO"];

						/* Obtener personas para enviar alertas */
						$query = "	SELECT LOC_PERSONA.ID_PERSONA AS ID_PERSONA, TOKEN,
									LOC_MENSAJE.MENSAJE AS MENSAJE
									FROM LOC_PERSONA
									INNER JOIN  LOC_PROTOCOLO
									ON LOC_PERSONA.ID_ORGANIZACION = LOC_PROTOCOLO.ID_ORGANIZACION
									INNER JOIN LOC_MENSAJE
									ON LOC_PERSONA.ID_PERSONA = LOC_MENSAJE.ID_PERSONA
									WHERE LOC_PROTOCOLO.ID_PROTOCOLO = $protocolID
									AND LOC_MENSAJE.ID_PROTOCOLO = $protocolID
									AND LOC_MENSAJE.ESTADO != 'I'";

						$stid = oci_parse($this->dbConn, $query);
						oci_execute($stid);

						$personas = array();

						while ($data = oci_fetch_array($stid,OCI_ASSOC)) {

							$personas [] = $data;

						}

						/* Por cada persona se envia alerta y se inserta en tabla de log */
						foreach ($personas as $persona) {

							$id_persona = $persona["ID_PERSONA"];
							$token = $persona["TOKEN"];
							$mensaje = $persona["MENSAJE"];

							/* Si tiene token enviar la notificacion */
							if ($token != '') {

								/* Enviar notificacion */
								$result = $this->send_notification($token, $mensaje);

								$success = $result["success"];

								/* Se almacena el resultado del envio de la notificacion */
								$results = $result["results"];

								if (array_key_exists("error", $results[0])) {

									$message_id = $results[0]["error"];

								}elseif (array_key_exists("message_id", $results[0])) {

									$message_id = $results[0]["message_id"];

								}

								/* Registrar en log */
								$query = "INSERT INTO LOC_ALERTA_LOG (ID_CORRELATIVO, ID_PERSONA, FECHA_ENVIO, RESULTADO, ID_MENSAJE, MENSAJE) VALUES ('$id_correlativo', '$id_persona', SYSDATE, $success, '$message_id', '$mensaje')";

							}else{

								/* Registrar en log */
								$query = "INSERT INTO LOC_ALERTA_LOG (ID_CORRELATIVO, ID_PERSONA, FECHA_ENVIO, RESULTADO) VALUES ('$id_correlativo', '$id_persona', SYSDATE, 'No tiene token o es invalido por lo que no se puede enviar notificación')";

							}

							$stid = oci_parse($this->dbConn, $query);
							oci_execute($stid);

						}

						/* Registrar las actividades del incidente */
						$query = "	INSERT INTO LOC_ACTIVIDADES_INCIDENTE (ID_INCIDENTE, ID_ACTIVIDAD)
									SELECT $id_correlativo AS ID_INCIDENTE, ID_ACTIVIDAD
									FROM LOC_ACTIVIDADES
									WHERE ID_PROTOCOLO = (

									    SELECT ID_PROTOCOLO
									    FROM LOC_PROTOCOLO_ACTIVADO
									    WHERE ID_CORRELATIVO = $id_correlativo

									)";

						$stid = oci_parse($this->dbConn, $query);

						if (false === oci_execute($stid)) {

							$err = oci_error($stid);

							$str_error = "Error al registrar las actividades";

							$this->throwError($err["code"], $str_error);

						}

						/* Obtener cantidad de personas a las que se notifico */
						$query = "	SELECT COUNT(*) AS TOTAL
									FROM LOC_ALERTA_LOG
									WHERE ID_CORRELATIVO = $id_correlativo
									AND RESULTADO = 1";

						$stid = oci_parse($this->dbConn, $query);
						oci_execute($stid);

						$total = oci_fetch_array($stid);

						$total_notificaciones = $total_notificaciones + intval($total["TOTAL"]);

						//$this->returnResponse(SUCCESS_RESPONSE, $total["TOTAL"]);

					}

				} catch (\Exception $e) {

					$this->throwError(JWT_PROCESSING_ERROR, $e->getMessage());

				}

			}

			$this->returnResponse(SUCCESS_RESPONSE, $total_notificaciones);

		}

		public function efectividadAlarma(){

			$id_correlativo = $this->validateParameter('id_correlativo', $this->param['id_correlativo'], STRING);

			$datos = array();

			$query = "	SELECT T2.*, TO_CHAR(T2.FECHA_ENVIO, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ENVIO, TO_CHAR(T2.FECHA_RECEPCION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_LECTURA, T3.NOMBRE AS PERSONA
						FROM LOC_PROTOCOLO_ACTIVADO T1
						INNER JOIN LOC_ALERTA_LOG T2
						ON T1.ID_CORRELATIVO = T2.ID_CORRELATIVO
						INNER JOIN LOC_PERSONA T3
						ON T2.ID_PERSONA = T3.ID_PERSONA
						WHERE T1.ID_CORRELATIVO = $id_correlativo";

			$stid = oci_parse($this->dbConn, $query);
			oci_execute($stid);

			$mensajes_enviados = array();
			$total_enviados = 0;
			$total_recibidos = 0;
			$total_faltantes = 0;
			$porcentaje = 0;

			while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
				
				/** Total de mensajes enviados  */
				$total_enviados++;

				/** Total de mensajes recibidos */
				if ($data["FECHA_RECEPCION"]) {
					$total_recibidos++;
				}

				/** Total de mensajes faltantes */
				if (!$data["FECHA_RECEPCION"]) {
					$total_faltantes++;
				}

				/** Porcentaje */
				$porcentaje = intval(($total_recibidos / $total_enviados) * 100);

				$mensajes_enviados [] = $data;

			}

			$datos["MENSAJES_ENVIADOS"] = $mensajes_enviados;
			$datos["TOTAL_ENVIADOS"] = $total_enviados;
			$datos["TOTAL_RECIBIDOS"] = $total_recibidos;
			$datos["TOTAL_FALTANTES"] = $total_faltantes;
			$datos["PORCENTAJE"] = $porcentaje;

			$this->returnResponse(SUCCESS_RESPONSE, $datos);

		}

		public function receptionDate(){

			$id_persona = $this->param['id_persona'];
			$fecha_envio = $this->param['fecha_envio'];

			$query = "UPDATE LOC_ALERTA_LOG SET FECHA_RECEPCION = SYSDATE WHERE ID_PERSONA = $id_persona AND TO_CHAR(FECHA_ENVIO, 'DD/MM/YYYY HH24:MI:SS') = '$fecha_envio'";

			$stid = oci_parse($this->dbConn, $query);

			if (false === oci_execute($stid)) {

				$err = oci_error($stid);

				$str_error = "Error General";

				$this->throwError($err["code"], $str_error);

			}

			$this->returnResponse(SUCCESS_RESPONSE, $id_persona);

		}

		public function reenviarAlarma(){

			

		}

		/* Alertas */

		public function getAlerts(){

			$db = new Db();
			$this->dbConn = $db->connect();

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			/* Obtener el ID de la organizacion a la que pertenece */
			$query = "SELECT ID_ORGANIZACION FROM LOC_PERSONA WHERE ID_PERSONA = '$userID'";

			$stid = oci_parse($this->dbConn, $query);
			oci_execute($stid);

			$persona = oci_fetch_array($stid, OCI_ASSOC);
			$id_organizacion = $persona["ID_ORGANIZACION"];

			try {

				$query = " SELECT * FROM LOC_ALERTA WHERE ID_ORGANIZACION = $id_organizacion ORDER BY ID_ALERTA DESC";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$alertas = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
					$alertas [] = $data;
				}

				$this->returnResponse(SUCCESS_RESPONSE, $alertas);

			} catch (\Exception $e) {

			}




		}

		public function createAlert(){

			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);
			$nameAlert = $this->validateParameter('nameAlert', $this->param['nameAlert'], STRING);
			$messageAlert = $this->validateParameter('messageAlert', $this->param['messageAlert'], STRING);
			$destinationAlert = $this->param["destinationAlert"];

			/* Obtener el ID de la organizacion a la que pertenece */
			$query = "SELECT ID_ORGANIZACION FROM LOC_PERSONA WHERE ID_PERSONA = '$userID'";

			$stid = oci_parse($this->dbConn, $query);
			oci_execute($stid);

			$persona = oci_fetch_array($stid, OCI_ASSOC);
			$id_organizacion = $persona["ID_ORGANIZACION"];

			try {

				/* Registrar Alerta */

				$query = "INSERT INTO LOC_ALERTA (NOMBRE, CREATED_AT, MENSAJE, ID_ORGANIZACION) VALUES ('$nameAlert', SYSDATE, '$messageAlert', '$id_organizacion')";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				/* Obtener ID */

				$query = "	SELECT ID_ALERTA
							FROM LOC_ALERTA
							WHERE ROWNUM = 1
							ORDER BY ID_ALERTA DESC";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$id_alerta = $result["ID_ALERTA"];

				/* Registrar destinos */

				foreach ($destinationAlert as $destination) {

					$query = "INSERT INTO LOC_DESTINO_ALERTA (ID_ALERTA, ID_PERSONA) VALUES ($id_alerta, $destination) ";

					$stid = oci_parse($this->dbConn, $query);
					oci_execute($stid);

				}

			} catch (\Exception $e) {

			}


			$this->returnResponse(SUCCESS_RESPONSE, $destinationAlert);

		}

		public function deleteAlert(){

			$alertID = $this->validateParameter('alertID', $this->param['alertID'], STRING);

			try {

				/* Eliminar primero los destinos */
				$query = "DELETE FROM LOC_DESTINO_ALERTA WHERE ID_ALERTA = $alertID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				/* Eliminar alerta */
				$query = "DELETE FROM LOC_ALERTA WHERE ID_ALERTA = $alertID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

			} catch (\Exception $e) {

			}


			$this->returnResponse(SUCCESS_RESPONSE, $alertID);

		}

		public function infoAlert(){

			$alertID = $this->validateParameter('alertID', $this->param['alertID'], STRING);

			try {

				/* Info Alerta */
				$query = "SELECT ID_ALERTA, NOMBRE, MENSAJE, ID_ORGANIZACION FROM LOC_ALERTA WHERE ID_ALERTA = $alertID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$alerta = oci_fetch_array($stid, OCI_ASSOC);

				/* Destinos */
				$id_alerta = $alerta["ID_ALERTA"];
				$query = "SELECT ID_PERSONA FROM LOC_DESTINO_ALERTA WHERE ID_ALERTA = $id_alerta";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$destinos = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$destinos [] = $data["ID_PERSONA"];

				}

				$alerta["DESTINOS"] = $destinos;

				/* Equipo */
				$id_organizacion = $alerta["ID_ORGANIZACION"];
				$query = '	SELECT ID_PERSONA AS "id", NOMBRE as "name"
							FROM LOC_PERSONA
							WHERE ID_ORGANIZACION = ' . $id_organizacion;

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = array(
								"name" => "Equipo",
								"id" => 1,
							);
				$equipo = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$equipo [] = $data;

				}

				$result["children"] = $equipo;

				$alerta["EQUIPO"] = $result;

				$this->returnResponse(SUCCESS_RESPONSE, $alerta);

			} catch (\Exception $e) {

			}


		}

		public function infoAlert2(){

			$alertID = $this->validateParameter('alertID', $this->param['alertID'], STRING);

			try {

				/* Info Alerta */
				$query = "SELECT ID_ALERTA, NOMBRE, MENSAJE, ID_ORGANIZACION FROM LOC_ALERTA WHERE ID_ALERTA = $alertID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$alerta = oci_fetch_array($stid, OCI_ASSOC);

				/* Destinos */
				$id_alerta = $alerta["ID_ALERTA"];
				$query = "SELECT ID_PERSONA FROM LOC_DESTINO_ALERTA WHERE ID_ALERTA = $id_alerta";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$destinos = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$destinos [] = $data["ID_PERSONA"];

				}

				$alerta["DESTINOS"] = $destinos;

				/* Equipo */
				$id_organizacion = $alerta["ID_ORGANIZACION"];
				$query = '	SELECT ID_PERSONA AS "id", NOMBRE as "name"
							FROM LOC_PERSONA
							WHERE ID_ORGANIZACION = ' . $id_organizacion;

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = array(
								"name" => "Equipo",
								"id" => 1,
							);
				$equipo = array();

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$equipo [] = $data;

				}

				$result["children"] = $equipo;

				$alerta["EQUIPO"] = $result;

				$this->returnResponse(SUCCESS_RESPONSE, $alerta);

			} catch (\Exception $e) {

			}

		}

		public function editAlert(){

			$alertID = $this->validateParameter('alertID', $this->param['alertID'], STRING);
			$nameAlert = $this->validateParameter('nameAlert', $this->param['nameAlert'], STRING);
			$messageAlert = $this->validateParameter('messageAlert', $this->param['messageAlert'], STRING);
			$destinationAlert = $this->param["destinationAlert"];

			try {

				/* Actualizar alerta */
				$query = "UPDATE LOC_ALERTA SET NOMBRE = '$nameAlert', UPDATED_AT = SYSDATE, MENSAJE = '$messageAlert' WHERE ID_ALERTA = $alertID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				/* Actualizar destinos */
				$query = "DELETE FROM LOC_DESTINO_ALERTA WHERE ID_ALERTA = $alertID";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				foreach ($destinationAlert as $destination) {

					$query = "INSERT INTO LOC_DESTINO_ALERTA (ID_ALERTA, ID_PERSONA) VALUES ($alertID, $destination)";
					$stid = oci_parse($this->dbConn, $query);
					oci_execute($stid);
				}

				$this->returnResponse(SUCCESS_RESPONSE, $alertID);

			} catch (\Exception $e) {

			}


		}

		/* Invitaciones */

		public function buscarPersonasInvitar(){

			$telefono = $this->validateParameter('telefono', $this->param['telefono'], STRING);
			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);

			try {
				
				$query = "	SELECT T1.ID_PERSONA, T1.NOMBRE, T1.AVATAR
							FROM LOC_PERSONA T1
							LEFT JOIN LOC_INVITACION_EQUIPO T2
							ON T1.ID_PERSONA = T2.ID_PERSONA
							WHERE T1.TELEFONO LIKE '%$telefono%' OR UPPER(T1.NOMBRE) LIKE UPPER('%$telefono%')";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);

				// $resultados = array();

				// while ($result = oci_fetch_array($stid, OCI_ASSOC)) {
					
					
				// 	$resultados [] = $result;


				// }

				if ($result) {
				
					
				if(array_key_exists('AVATAR', $result)){
	
					$avatar = $result["AVATAR"];
					$photo = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar);
					$result["AVATAR"] = $photo;

				}else{

					$result["AVATAR"] = "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";

				}

				}else {
					
					$this->throwError(GENERAL_ERROR, "No se ha encontrado a ninguna persona con los datos proporcionados.");

				}

				$this->returnResponse(SUCCESS_RESPONSE, $result);


			} catch (\Throwable $th) {
				


			}

		}

		public function invitarPersona(){

			$id_persona = $this->validateParameter('id_persona', $this->param['id_persona'], STRING);
			$id_organizacion = $this->validateParameter('id_organizacion', $this->param['id_organizacion'], STRING);
			$id_persona_invita = $this->validateParameter('id_persona_invita', $this->param['id_persona_invita'], STRING);

			try {
				
				/** Invitacion */
				// $query = "INSERT INTO LOC_INVITACION_EQUIPO (ID_PERSONA, ID_ORGANIZACION, CREATED_AT, ESTADO, ID_PERSONA_INVITA) VALUES ('$id_persona', '$id_organizacion', SYSDATE, '1', '$id_persona_invita')";

				// $stid = oci_parse($this->dbConn, $query);

				// if (false === oci_execute($stid)) {

				// 	$err = oci_error($stid);

				// 	$str_error = "Ya se ha enviado invitación a esta persona";

				// 	$this->throwError($err["code"], $str_error);

				// }

				/** Agregar de manera automatica */
				$query = "INSERT INTO LOC_PERSONA_ORGANIZACION (ID_PERSONA, ID_ORGANIZACION, UPDATED_AT) VALUES ('$id_persona', '$id_organizacion', SYSDATE)";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					if ($err["code"] == 1) {
						
						$str_error = "La persona ya forma parte del equipo";

					}else{

						$str_error = "Error al invitar";

					}

					$this->throwError($err["code"], $str_error);

				}

				/* Enviar la notificacion */
				$query = "SELECT TOKEN FROM LOC_PERSONA WHERE ID_PERSONA = $id_persona";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$token = $result["TOKEN"];

				/*Buscar la persona que invita */
				$query = "SELECT NOMBRE FROM LOC_PERSONA WHERE ID_PERSONA = $id_persona_invita";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$nombre = $result["NOMBRE"];

				/* Buscar el nombre del equipo */
				$query = "SELECT NOMBRE FROM LOC_ORGANIZACION WHERE ID_ORGANIZACION = $id_organizacion";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);
				$nombre_equipo = $result["NOMBRE"];

				$mensaje = $nombre . " lo ha agregado al equipo " . $nombre_equipo;

				$this->send_notification($token, $mensaje);

				$this->returnResponse(SUCCESS_RESPONSE, $id_persona);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

		public function buscarPersonasInvitar2(){

			$busqueda = $this->validateParameter('busqueda', $this->param['busqueda'], STRING);
			$userID = $this->validateParameter('userID', $this->param['userID'], STRING);
			$id_organizacion = $this->validateParameter('id_organizacion', $this->param['id_organizacion'], STRING);

			try {
				
				$query = "	SELECT *
							FROM LOC_PERSONA
							WHERE TELEFONO LIKE '%$busqueda%' OR UPPER(NOMBRE) LIKE UPPER('%$busqueda%')";

				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				// $result = oci_fetch_array($stid, OCI_ASSOC);

				$resultados = array();

				while ($result = oci_fetch_array($stid, OCI_ASSOC)) {
					
					$id_persona = $result["ID_PERSONA"];

					if(array_key_exists('AVATAR', $result)){
	
						$avatar = $result["AVATAR"];
						$photo = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/apps/ave_api/avatar/'.$avatar);
						$result["AVATAR"] = $photo;

					}else{

						$result["AVATAR"] = "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";

					}

					/** Buscar si ya tiene forma parte del equipo */
					$query = "SELECT * FROM LOC_PERSONA_ORGANIZACION WHERE ID_PERSONA = '$id_persona' AND ID_ORGANIZACION = '$id_organizacion'";
					$stid2 = oci_parse($this->dbConn, $query);
					oci_execute($stid2);

					$persona_organizacion = oci_fetch_array($stid2, OCI_ASSOC);

					if ($persona_organizacion) {
						
						$result["EXISTE_EQUIPO"] = true;

					}else{

						$result["EXISTE_EQUIPO"] = false;

					}

					/** Buscar si ya se le envio invitacion */
					$query = "SELECT * FROM LOC_INVITACION_EQUIPO WHERE ID_PERSONA = '$id_persona' AND ID_ORGANIZACION = '$id_organizacion' AND ESTADO = 1";
					$stid2 = oci_parse($this->dbConn, $query);
					oci_execute($stid2);

					$persona_organizacion = oci_fetch_array($stid2, OCI_ASSOC);

					if ($persona_organizacion) {
						
						$result["EXISTE_INVITACION"] = true;

					}else{

						$result["EXISTE_INVITACION"] = false;

					}

					$resultados [] = $result;


				}

				if (count($resultados) <= 0) {
					
					$this->throwError(GENERAL_ERROR, "No se ha encontrado a ninguna persona con los datos proporcionados, puede proceder a registrarla.");

				}

				$this->returnResponse(SUCCESS_RESPONSE, $resultados);


			} catch (\Throwable $th) {
				


			}

		}

		public function invitarPersona2(){
			
			$id_persona = $this->validateParameter('id_persona', $this->param['id_persona'], STRING);
			$id_organizacion = $this->validateParameter('id_organizacion', $this->param['id_organizacion'], STRING);
			$id_persona_invita = $this->validateParameter('id_persona_invita', $this->param['id_persona_invita'], STRING);

			try {
				
				/** Verificar que no exista un invitacion pendiente */
				$query = "SELECT * FROM LOC_INVITACION_EQUIPO WHERE ID_PERSONA = $id_persona AND ID_ORGANIZACION = $id_organizacion AND ESTADO = 1";
				$stid = oci_parse($this->dbConn, $query);
				oci_execute($stid);

				$result = oci_fetch_array($stid, OCI_ASSOC);

				if($result){

					$str_error = "Ya se ha enviado invitación a esta persona";

					$this->throwError(300, $str_error);

				}else{

					/** Invitacion */
					$query = "INSERT INTO LOC_INVITACION_EQUIPO (ID_PERSONA, ID_ORGANIZACION, CREATED_AT, ESTADO, ID_PERSONA_INVITA) VALUES ('$id_persona', '$id_organizacion', SYSDATE, '1', '$id_persona_invita')";

					$stid = oci_parse($this->dbConn, $query);

					if (false === oci_execute($stid)) {

						$err = oci_error($stid);

						$str_error = "Error al enviar la invitación";

						$this->throwError($err["code"], $str_error);

					}

					/* Enviar la notificacion */
					$query = "SELECT TOKEN FROM LOC_PERSONA WHERE ID_PERSONA = $id_persona";
					$stid = oci_parse($this->dbConn, $query);
					oci_execute($stid);

					$result = oci_fetch_array($stid, OCI_ASSOC);
					$token = $result["TOKEN"];

					/*Buscar la persona que invita */
					$query = "SELECT NOMBRE FROM LOC_PERSONA WHERE ID_PERSONA = $id_persona_invita";
					$stid = oci_parse($this->dbConn, $query);
					oci_execute($stid);

					$result = oci_fetch_array($stid, OCI_ASSOC);
					$nombre = $result["NOMBRE"];

					/* Buscar el nombre del equipo */
					$query = "SELECT NOMBRE FROM LOC_ORGANIZACION WHERE ID_ORGANIZACION = $id_organizacion";
					$stid = oci_parse($this->dbConn, $query);
					oci_execute($stid);

					$result = oci_fetch_array($stid, OCI_ASSOC);
					$nombre_equipo = $result["NOMBRE"];

					$mensaje = $nombre . " lo ha invitado a unirse al equipo " . $nombre_equipo;

					$this->send_notification($token, $mensaje);

				}

				

				/** Agregar de manera automatica */
				// $query = "INSERT INTO LOC_PERSONA_ORGANIZACION (ID_PERSONA, ID_ORGANIZACION, UPDATED_AT) VALUES ('$id_persona', '$id_organizacion', SYSDATE)";

				// $stid = oci_parse($this->dbConn, $query);

				// if (false === oci_execute($stid)) {

				// 	$err = oci_error($stid);

				// 	if ($err["code"] == 1) {
						
				// 		$str_error = "La persona ya forma parte del equipo";

				// 	}else{

				// 		$str_error = "Error al invitar";

				// 	}

				// 	$this->throwError($err["code"], $str_error);

				// }

				$this->returnResponse(SUCCESS_RESPONSE, $id_persona);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

		public function aceptarInvitacion(){

			$id_invitacion = $this->validateParameter('id_invitacion', $this->param['id_invitacion'], STRING);
			$id_persona = $this->validateParameter('id_persona', $this->param['id_persona'], STRING);
			$id_organizacion = $this->validateParameter('id_organizacion', $this->param['id_organizacion'], STRING);

			try {
				
				/** Actualizar el estado de la invitacion */
				$query = "UPDATE LOC_INVITACION_EQUIPO SET ESTADO = 2, UPDATED_AT = SYSDATE WHERE ID_INVITACION = $id_invitacion";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "Error al aceptar la invitación.";

					$this->throwError($err["code"], $str_error);

				}

				/** Agregar al equipo */
				$query = "INSERT INTO LOC_PERSONA_ORGANIZACION (ID_PERSONA, ID_ORGANIZACION) VALUES ($id_persona, $id_organizacion)";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "Error al aceptar la invitación.";

					$this->throwError($err["code"], $str_error);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $id_invitacion);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

		public function rechazarInvitacion(){

			$id_invitacion = $this->validateParameter('id_invitacion', $this->param['id_invitacion'], STRING);

			try {
				
				/** Actualizar el estado de la invitacion */
				$query = "UPDATE LOC_INVITACION_EQUIPO SET ESTADO = 3, UPDATED_AT = SYSDATE WHERE ID_INVITACION = $id_invitacion";

				$stid = oci_parse($this->dbConn, $query);

				if (false === oci_execute($stid)) {

					$err = oci_error($stid);

					$str_error = "Error al rechazar la invitación.";

					$this->throwError($err["code"], $str_error);

				}

				$this->returnResponse(SUCCESS_RESPONSE, $id_invitacion);

			} catch (\Throwable $th) {
				//throw $th;
			}

		}

	}

?>
