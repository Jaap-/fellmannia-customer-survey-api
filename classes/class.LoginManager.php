<?php
/** 
 * LoginManager handles secured session-based logins
 * @author Jarmo Erola
 * @version 0.8
 */
class LoginManager{
	var $tblUsers = 'Users';
	var $tblLoginAttempts = 'UserLoginAttempts';
	/**
	 * Constructor
	 */
	public function __construct(){
	}
	/**
	 * Login function
	 * @param $email Email address of user
	 * @param $password Hashed password of user
	 * @return true or false
	 */
	public function login($email, $password, $firephp) {
		$mysqli = new mysqli(HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
		if($stmt = $mysqli->prepare("SELECT id, username, password, salt FROM ".$this->tblUsers." WHERE email = ? LIMIT 1")) { 
			$stmt->bind_param('s', $email);
			$stmt->execute();
			$stmt->store_result();
			$stmt->bind_result($user_id, $username, $db_password, $salt);
			$stmt->fetch();
			$password = hash('sha512', $password.$salt);
			if($stmt->num_rows == 1) { // If the user exists
				// Check if the account is locked from too many login attempts
				if($this->checkBrute($user_id, $mysqli) == true) { 
					// Account is locked
					$firephp->error('Account is locked');
					return false;
				} else {
					if($db_password == $password) {
						$ip_address = $_SERVER['REMOTE_ADDR']; 
						$user_browser = $_SERVER['HTTP_USER_AGENT'];
						$user_id = preg_replace("/[^0-9]+/", "", $user_id); // XSS protection
						$_SESSION['user_id'] = $user_id; 
						$username = preg_replace("/[^a-zA-Z0-9_\-]+/", "", $username); // XSS protection
						$_SESSION['username'] = $username;
						$_SESSION['login_string'] = hash('sha512', $password.$ip_address.$user_browser);
						$mysqli->query("DELETE FROM ".$this->tblLoginAttempts." WHERE user_id='$user_id'");
						// Login successful.
						return true;    
					} else {
						// Password is not correct
						// Record this attempt in the database
						$firephp->error('Password is not correct');
						$firephp->error($db_password.' '.$password);
						$now = time();
						$mysqli->query("INSERT INTO ".$this->tblLoginAttempts." (user_id, time) VALUES ('$user_id', '$now')");
						return false;
					}
				}
			} else {
				// No user exists. 
				$firephp->error('No user exists');
				return false;
			}
		}
	}
	/**
	 * Brute Force checker
	 * @param $userID User id
	 * @param $mysqli Instance of MySQLi
	 * @return true or false
	 */
	public function checkBrute($userID, $mysqli) {
		$now = time();
		$valid_attempts = $now - (2 * 60 * 60); 
		if ($stmt = $mysqli->prepare("SELECT time FROM ".$this->tblLoginAttempts." WHERE user_id = ? AND time > '$valid_attempts'")) { 
			$stmt->bind_param('i', $userID); 
			$stmt->execute();
			$stmt->store_result();
			// If there has been more than 5 failed logins
			if($stmt->num_rows > 5) {
				return true;
			} else {
				return false;
			}
		}
	}
	
	/**
	 * Opens locked account
	 * @param $userID User id
	 * @return true or false
	 */
	public function openLockedAccount($userID) {
		$mysqli = new mysqli(HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
		if($stmt = $mysqli->prepare("DELETE FROM ".$this->tblLoginAttempts." WHERE user_id = ?")) { 
			$stmt->bind_param('i', $userID); 
			$stmt->execute();
			return true;
		}
	}
	/**
	 * Login checker
	 * @return true or false
	 */
	public function loginCheck() {
		$mysqli = new mysqli(HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
		if(isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['login_string'])) {
			$user_id = $_SESSION['user_id'];
			$login_string = $_SESSION['login_string'];
			$username = $_SESSION['username'];
			$ip_address = $_SERVER['REMOTE_ADDR'];
			$user_browser = $_SERVER['HTTP_USER_AGENT'];
			if ($stmt = $mysqli->prepare("SELECT password FROM ".$this->tblUsers." WHERE id = ? LIMIT 1")) { 
				$stmt->bind_param('i', $user_id);
				$stmt->execute();
				$stmt->store_result();
				if($stmt->num_rows == 1){ // If the user exists
					$stmt->bind_result($password);
					$stmt->fetch();
					$login_check = hash('sha512', $password.$ip_address.$user_browser);
					if($login_check == $login_string) {
						// Logged In
						return true;
					} else {
						// Not logged in
						return false;
					}
				} else {
					// Not logged in
					return false;
				}
			} else {
				// Not logged in
				return false;
			}
		} else {
			// Not logged in
			return false;
		}
	}
	/**
	 * Logout function
	 */
	public function logout(){
		$_SESSION = array();
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
		session_destroy();
	}
}
?>