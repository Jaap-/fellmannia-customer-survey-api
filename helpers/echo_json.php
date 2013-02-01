<?php

	/* Echoes data as JSON format
	 * @data data-array
	 */
	function echoJSON($data){
		//global $callback;
		header("Content-type: application/json");
		//echo $callback.'('.json_encode($data).')';
		echo json_encode($data);
	}
	
?>