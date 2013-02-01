<?php
	require 'libraries/Slim/Slim.php';
	include_once('libraries/meekrodb.2.1.class.php');
	include_once('libraries/FirePHPCore/FirePHP.class.php');
	include_once('config/config.php');
	include_once('helpers/sec_session_start.php');
	include_once('helpers/echo_json.php');
	include_once('classes/class.LoginManager.php');
	
	sec_session_start();
	
	// For debugging
	$firephp = FirePHP::getInstance(true);
	$firephp->setEnabled(true);
	
	\Slim\Slim::registerAutoloader();
	$app = new \Slim\Slim();

	DB::$user = DB_USER;
	DB::$password = DB_PASSWORD;
	DB::$dbName = DB_DATABASE;
	DB::$encoding = 'utf8';
	
	/**
	 * Get categories
	 */
	$app->get('/categories', function(){
		$result = DB::query("SELECT * FROM Categories");
		
		$arr = array();
		foreach($result as $row){
			$arr[] = array('id'=>$row['id'], 'name'=>$row['name'], 'instructiontext'=>$row['instructionText']);
		}

		echoJSON(array('status'=>1, 'categories'=>$arr));
	});

	/**
	 * Add category
	 * @param {string} name
	 * @param {string} description
	 */
	$app->post('/categories/:name/:description', function($name, $description){
		DB::insert('Categories', array(
			'name' => $name,
			'description' => $description
		));
		
		echoJSON(array('status'=>1));
	});

	/**
	 * Update category
	 * @param {int} id
	 * @param {string} name
	 * @param {string} description
	 */
	$app->put('/categories/:id/:name/:description', function($id, $name, $description){
		DB::update('tbl', array(
			'name' => $name,
			'description' => $description
		), "id=%i", $id);
		
		echoJSON(array('status'=>1));
	});

	/**
	 * Delete category
	 * @param {int} id
	 */
	$app->delete('/categories/:id', function($id){
		DB::delete('Categories', "id=%i", $id);
		
		echoJSON(array('status'=>1));
	});

	/**
	 * Get comments
	 * @param {int} catid
	 * @param {int} limit optional
	 */
	$app->get('/comments/:catid/:limit', function($catid, $limit){
		if($catid != ''){
			if(is_numeric($limit)){
				$arr = array();
				if($catid == 1){
					$result = DB::query('SELECT * FROM Comments ORDER BY Categories_id DESC, id DESC');
					$arrCount = array();
					foreach($result as $row){
						if(!isset($arrCount[$row['Categories_id']])){
							$arrCount[$row['Categories_id']] = 0;
						}
						if($arrCount[$row['Categories_id']] <= 1){
							$arrCount[$row['Categories_id']]++;
							$arr[] = array('id'=>$row['id'], 
										   'categoryid'=>$row['Categories_id'], 
										   //'type'=>$row['type'], 
										   'status'=>$row['status'], 
										   'timestamp'=>$row['timestamp'], 
										   'thumbcountplus'=>$row['thumbCountPlus'], 
										   'thumbcountminus'=>$row['thumbCountMinus'], 
										   'text'=>$row['text'], 
										   //'contactinfo'=>$row['contactInfo'], 
										   //'contact'=>$row['contact'], 
										   'checked'=>$row['checked']);
						}
					}
				}
				else if($catid == 2){ // monthly question
					$result = DB::query('SELECT * FROM Questions ORDER BY id DESC LIMIT 1');
					foreach($result as $row){
						$arr[] = array('id'=>$row['id'], 
									   'categoryid'=>$row['Categories_id'], 
									   //'type'=>$row['type'], 
									   'status'=>$row['status'], 
									   'timestamp'=>$row['timestamp'], 
									   'thumbcountplus'=>$row['thumbCountPlus'], 
									   'thumbcountminus'=>$row['thumbCountMinus'], 
									   'text'=>$row['text'], 
									   //'contactinfo'=>$row['contactInfo'], 
									   //'contact'=>$row['contact'], 
									   'checked'=>$row['checked']);
					}
				}else{
					if($limit==0){
						$result = DB::query('SELECT * FROM Comments WHERE Categories_id=%i', $catid);
					}else{
						$result = DB::query('SELECT * FROM Comments WHERE Categories_id=%i LIMIT %i', $catid, $limit);
					}
					foreach($result as $row){
						$arr[] = array('id'=>$row['id'], 
									   'categoryid'=>$row['Categories_id'], 
									   //'type'=>$row['type'], 
									   'status'=>$row['status'], 
									   'timestamp'=>$row['timestamp'], 
									   'thumbcountplus'=>$row['thumbCountPlus'], 
									   'thumbcountminus'=>$row['thumbCountMinus'], 
									   'text'=>$row['text'], 
									   //'contactinfo'=>$row['contactInfo'], 
									   //'contact'=>$row['contact'], 
									   'checked'=>$row['checked']);
					}
				}
				
				echoJSON(array('status'=>1, 'comments'=>$arr));
				
			}else{
				echoJSON(array('status'=>0, 'msg'=>'Incorrect limit'));
			}
		}else{
			echoJSON(array('status'=>0, 'msg'=>'Category ID missing'));
		}
	});

	/**
	 * Add comment
	 * @param {string} name
	 * @param {string} description
	 */
	$app->post('/comments/:catid/:questionid/:status/:text/:name/:email/:phone', function($catid, $questionid, $status, $text, $name, $email, $phone){
		DB::insert('Comments', array(
			'Categories_id'=>$catid,
			'questionID'=>$questionid,
			//'type'=>$type,
			'status'=>$status,
			'timestamp'=>time(),
			'text'=>$text,
			'name'=>($name == '_' ? '' : $name),
			'email'=>($email == '_' ? '' : $email),
			'phone'=>($phone == '_' ? '' : $phone)
		));
		
		echoJSON(array('status'=>1));
	});

	/**
	 * Delete comment
	 * @param {int} id
	 */
	$app->delete('/comments/:id', function($id){
		DB::delete('Comments', "id=%i", $id);
		
		echoJSON(array('status'=>1));
	});
	
	/**
	 * Like comment
	 * @param {int} comment id
	 */
	$app->post('/comments/like/:id', function($id){
		$userHash = md5($_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR']);
		$resultThumbs = DB::queryFirstRow("SELECT * FROM Thumbs WHERE Comments_id=%i AND userHash=%s", $id, $userHash);
		
		if(!isset($resultThumbs['userHash'])){
			$result = DB::query("SELECT thumbCountPlus FROM Comments WHERE id=%i", $id);
			DB::update('Comments', array(
			  'thumbCountPlus' => $result[0]['thumbCountPlus']+1
			), "id=%i", $id);
			
			DB::insert('Thumbs', array(
				'Comments_id'=>$id,
				'status'=>0,
				'timestamp'=>time(),
				'userHash'=>$userHash
			));
			
			echoJSON(array('status'=>1));
		}else{
			echoJSON(array('status'=>0, 'msg'=>'Olet jo arvioinut tämän palautteen!'));
		}
	});
	
	/**
	 * Disike comment
	 * @param {int} comment id
	 */
	$app->post('/comments/dislike/:id', function($id){
		$userHash = md5($_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR']);
		$resultThumbs = DB::queryFirstRow("SELECT * FROM Thumbs WHERE Comments_id=%i AND userHash=%s", $id, $userHash);
		
		if(!isset($resultThumbs['userHash'])){
			$resultComments = DB::query("SELECT thumbCountMinus FROM Comments WHERE id=%i", $id);
			DB::update('Comments', array(
			  'thumbCountMinus' => $resultComments[0]['thumbCountMinus']+1
			), "id=%i", $id);
			
			DB::insert('Thumbs', array(
				'Comments_id'=>$id,
				'status'=>1,
				'timestamp'=>time(),
				'userHash'=>$userHash
			));
			
			echoJSON(array('status'=>1));
		}else{
			echoJSON(array('status'=>0, 'msg'=>'Olet jo arvioinut tämän palautteen!'));
		}
	});

	
	/**
	 * Login
	 * @param {string} email
	 * @param {string} hashed password
	 */
	$app->post('/login/:email/:password', function($email, $password){
		if(isset($email, $password)){ 
			
			if($l->login($email, $password, $firephp) == true) {
				echoJSON(array('status'=>1));
			} else {
				echoJSON(array('status'=>0, 'msq'=>'Login failed'));
			}
		}else{ 
			echoJSON(array('status'=>0, 'msq'=>'Login credentials missing'));
		}
	});
	
	/**
	 * Login check
	 */
	$app->post('/logincheck', function(){
		$l = new LoginManager();
		if(!$l->loginCheck()){
			echoJSON(array('status'=>0));
		}else{
			echoJSON(array('status'=>1));
		}
	});
	
	/**
	 * Logout
	 */
	$app->post('/logout', function(){
		$l = new LoginManager();
		$l->logout();
		echoJSON(array('status'=>1));
	});
	
	/**
	 * Open locked account
	 * @param {string} email
	 * @param {string} hashed password
	 */
	$app->post('/openlockedaccount/:userid', function($userid){
		if($userid != ''){
			$l = new LoginManager();
			$affectedRows = $l->openLockedAccount($userid);
			echoJSON(array('status'=>1));
		}else{
			echoJSON(array('status'=>0, 'msg'=>'Userid missing'));
		}
	});
	
	/**
	 * Add user
	 * @param {string} username
	 * @param {string} email
	 * @param {string} hashed password
	 */
	$app->post('/adduser/:username/:email/:password', function($username, $email, $password){
		if($username != '' && $email != '' && $password != ''){
			$salt = hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), true)); // Create a random salt
			
			DB::insert('Users', array(
				'username' => $username,
				'email' => $email,
				'password' => hash('sha512', $password.$salt), // Create salted password
				'salt' => $salt
			));
			$affectedRows = DB::affectedRows();
			
			echoJSON(array('status'=>$affectedRows));
		}else{
			echoJSON(array('status'=>0, 'msg'=>'Missing information'));
		}
	});
	

	$app->run();
