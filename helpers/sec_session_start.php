<?php
	/** 
	 * Security improvements to session_start()
	 * @author Jarmo Erola
	 */
	function sec_session_start() {
        $session_name = SESSION_NAME;
        $secure = false; // Set to true if using https.
        $httponly = true; // Prevent javascript being able to access the session id
 
        ini_set('session.use_only_cookies', 1); // Use cookies. 
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params($cookieParams["lifetime"], $cookieParams["path"], $cookieParams["domain"], $secure, $httponly); 
        session_name($session_name);
        session_start();
        session_regenerate_id(true);   
	}
	
?>