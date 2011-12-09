<?php
/**
 * @package RocketD
 * @version 1
 */
/*
Plugin Name: RocketD Integration Module
Description: Log in using authentication from a rocketD implimentation
Author: Ian Turgeon
Version: 1
*/

// add_filter('redirect_canonical', rocketD_auth_inspect_url, 1, 2);

// function rocketD_auth_inspect_url($rewrite)
// {
// 	trace2($rewrite);
// 
// }
// 
// add_action('generate_rewrite_rules', 'rocketD_auth_inspect_url');


add_filter('rewrite_rules_array', 'rocketD_modify_rewrite_rules');
add_filter('query_vars', 'rocketD_add_query_vars');
add_action('wp_loaded', 'rocketD_flush_rewrite_rules');

function rocketD_modify_rewrite_rules($rules)
{
	$newrules = array();
	// add rule for viewing instances
	$newrules['view/(\d+?)/?$'] = 'index.php?pagename=view&instID=$matches[1]';
	// add rule for previewing los
	$newrules['preview/(\d+?)/?$'] = 'index.php?pagename=view&loID=$matches[1]';
	// add rule for previewing previous draft los
	$newrules['preview/(\d+)/history/(\d+?)/?$'] = 'index.php?pagename=view&loID=$matches[2]';
	$rules =  $newrules + $rules;
	
	/*
	https://obojobo.ucf.edu/view/3921
	
	https://obojobo.ucf.edu/lo/evaluating-web-sites/2.342
	
	https://obojobo.ucf.edu/inst/evaluating-web-sites/2.342
	
	https://obojobo.ucf.edu/view/evaluating-web-sites/2.342
	
	https://obojobo.ucf.edu/evaluating-web-sites/preview/3.23
	
	https://obojobo.ucf.edu/evaluating-web-sites/11Spring/AML3930H-0001
	
	https://obojobo.ucf.edu/11Spring/AML3930H-0001/evaluating-web-sites
	
	https://obojobo.ucf.edu/view/evaluating-web-sites/3234/
	
	https://obojobo.ucf.edu/view/3123/evaluationg-web-sites/
	
	https://obojobo.ucf.edu/inst/3123/evaluationg-web-sites/
	
	https://obojobo.ucf.edu/evaluationg-web-sites/AML3930H-0001-11Spring
	
	https://obojobo.ucf.edu/view/evaluationg-web-sites/in/idv-essentials-11Summer(3)
	
	https://obojobo.ucf.edu/evaluationg-web-sites/idv-essentials-11Summer

	https://obojobo.ucf.edu/lo/3123/evaluationg-web-sites/
	
	https://obojobo.ucf.edu/preview/3123/evaluationg-web-sites/
	
	*/
	return $rules;
}

function rocketD_flush_rewrite_rules(){
	$rules = get_option('rewrite_rules');
	if(!isset( $rules['view/(.+?)/?$'] ) ) 
	{
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}
}

function rocketD_add_query_vars($vars)
{
	array_push($vars, 'instID');
	array_push($vars, 'loID');
	return $vars;
}


// LISTEN TO THE AUTHENTICATE FILTER - USE Obojobo to see if the user's crudentials are right
// If they are - give them the proper role, create a wordpress user, and log em in to both systems


add_filter('authenticate', 'rocketD_auth_check_password', 1, 3);
function rocketD_auth_check_password($user, $username, $password)
{
	require_once(dirname(__FILE__)."/../../../../internal/app.php");
	$API = \obo\API::getInstance();
	$result = $API->doLogin($username, $password);
	if($result === true)
	{
		$user = $API->getUser();
		
		// look for an existing user
		$sanitizedUsername = sanitize_user(esc_sql($username), true);
		$wp_user_id = username_exists($sanitizedUsername);

		// create one if it doesnt exist
		if(!$wp_user_id)
		{
			$random_password = wp_generate_password(100,false);
			$wp_user_id = wp_create_user($user->login, $random_password, $user->email);
		}
		
		// update the user info
		wp_update_user(array('ID' => $wp_user_id, 'display_name' => $user->first . ' ' . $user->last));
		// add_user_meta($wp_user_id, 'first_name', $user->first, true);
		// add_user_meta($wp_user_id, 'last_name', $user->last, true);
		$wpUser = new WP_User($wp_user_id);

		$roles = $API->getUserRoles();
		
		$groups = array();
		foreach($roles as $role)
		{
			$groups[] = $role->name;
		}
		
		if(in_array('Administrator', $groups))
		{
			$wpUser->set_role('administrator');
		}
		else
		{
			$wpUser->set_role('');
		}
		
		return $wpUser;
	}
	else
	{
		remove_action('authenticate', 'wp_authenticate_username_password', 20); // prevent any other authentication from working
		return new WP_Error('invalid_username', __('<strong>Obojobo Login Failure</strong> Your NID and NID password did not authenticate.'));
	}
}

// Log the user out of the RocketD application
add_filter('wp_logout', 'rocketD_auth_logout');
function rocketD_auth_logout()
{
	require_once(dirname(__FILE__)."/../../../../internal/app.php");
	$API = \obo\API::getInstance();
	$API->doLogout();
}


/*********************** DEBUGGING CODE ****************************/


function trace2($traceText)
{
	
	@$dt = debug_backtrace();
	// if traceText is an object, print_r it
	if(is_object($traceText) || is_array($traceText))
	{
		$traceText = print_r($traceText, true);
	}
	
	if(is_array($dt))
	{
		writeLog(basename($dt[0]['file']).'#'.$dt[0]['line'].': '.$traceText, false);
		return; // exit here if either of these methods wrote to the log
		
	}
	// couldnt get backtrace, just export what we have
	if(is_object($traceText) || is_array($traceText))
	{
		writeLog('printr: ' .print_r($traceText, true));
	}
	else
	{
		writeLog('trace: ' .$traceText);
	}
}

function writeLog($output, $fileName=false)
{	
	// create the log directory if it doesnt exist
	$fileName = dirname(__FILE__) . '/trace.txt';

	$fh = fopen($fileName, 'a');
	fwrite($fh, $output . "\n");
	fclose($fh);
	
}



?>
