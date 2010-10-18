<?php

class core_auth_AuthManager extends core_db_dbEnabled
{
	protected static $instance;

	static public function getInstance()
	{
		if(!isset(self::$instance))
		{
			$selfClass = __CLASS__;
			self::$instance = new $selfClass();
		}
		return self::$instance;
	}

	/**
	 * Gets a user's information from the database
	 * @param $userID (number) user ID
	 * @return (User) the requested user object
	 **/
	public function fetchUserByID($userID = false)
	{
				
		// need to get the user from the authmodule so that we can get the login name or login from the module
		if(core_util_Validator::isPosInt($userID))
		{
			if($authMod = $this->getAuthModuleForUserID($userID))
			{
				return $authMod->fetchUserByID($userID);
			}
		}
		
		return false;
	}
	
	/**
	 * Gets a user object from the database based on their username
	 *
	 * @param string $userName 
	 * @return (User) requested user object or false if not found
	 * @author Ian Turgeon
	 */
	public function fetchUserByUserName($userName = false)
	{
		// need to get the user from the authmodule so that we can get the login name or login from the module

		if($authMod = $this->getAuthModuleForUsername($userID))
		{
			return $authMod->fetchUserByID($userID);
		}

		return false;
	}
	
	/**
	 * Deletes a user with id
	 * Only SuperUsers can delete a user
	 * @return (bool) True if succesful, False if error or no login
	 */
	public function deleteUserByID($userID = 0)
	{
	    if(!nm_los_Validator::isPosInt($userID))
		{
			
	       
	        return core_util_Error::getError(2);
		}

		$roleMan = core_perms_RoleManager::getInstance();
		if(!$roleMan->isSuperUser())
		{
			return false;
		}
		return $this->removeUser($userID);

	}

	/**
	 * Logs a user into the system
	 * @param $userName (string) The user's login name
	 * @param @pwd (string) the user's password (plaintext)
	 * @return (bool) True if login successful, False if not
	 * 
	 * @todo Change this to a better hashing scheme
	 */
	//function login($userName='', $pwd='')
	public function login($userName, $password)
	{
		$userName = trim($userName);
		$password = trim($password);
		if($this->authenticate(array('userName' => $userName, 'password' => $password)))
		{
			if($_SESSION['passed'] === true)
			{
				//TODO: add this back in
			    //$trackingMan = nm_los_TrackingManager::getInstance();
	            //$trackingMan->trackLoggedIn();
				return true;
			}
			else
			{
				
				
				return core_util_Error::getError(1004); // error: change password error
			}
		}
		
		// monitor repeat failed logins and throttle them
		
		core_util_Cache::getInstance()->doRateLimit($_SERVER['REMOTE_ADDR']);
		
		
		return core_util_Error::getError(1003); // error: incorrect password
	}

	/**
	 * Compares the old session id in the database to the new one
	 * This is for making sure someone else hasn't hijacked the session
	 * @param $userID (number) user ID
	 * @param $sessid (string) PHP session ID
	 * @return (bool) True if they are the same, False if not
	 */	 	
	public function cmpSessID($userID, $sessid){
		//TODO: check to see if the session has past the expiration time
		$qstr = "SELECT ".cfg_core_User::SID." FROM ".cfg_core_User::TABLE." WHERE ".cfg_core_User::ID."='?' LIMIT 1";
		$this->defaultDBM();
		if( !($q = $this->DBM->querySafe($qstr, $userID)) )
		{
			$this->DBM->rollback();
			return false;
		}
		
		//Compare the two session IDs
		if( $r = $this->DBM->fetch_obj($q) )
		{
			if($r->{cfg_core_User::SID} == $sessid && $r->{cfg_core_User::SID} != '') return true;
		}
		return false;
	}

	/**
	 * Starts the session, verifys that the session is still valid, and logs
	 * the user out if it is not.
	 *
	 * Also checks to see if the session id in the session variable is the same 
	 * as the one in the database.  Then it creates a new session id.
	 *
	 * Call this every time a new request is made of the server.
	 * 
	 * @param $skipRegen (bool) False to generate a new session ID on each exchange, True to skip this step
	 * @param $skipcompare (bool) False to compare the session ID to the stored one, True to skip this step
	 * @return (bool) True if login is valid, False if invalid
	 */	 

	// TODO: migrate role manager to a reusable package
	public function verifySession($roleName='')
	{
		if(!headers_sent() && !isset($_SESSION))
		{
			session_name(AppCfg::SESSION_NAME);
			session_start();
		}
		//If they had a valid session before, and if the session ID fits the one in the database, let them pass
		if (isset($_SESSION['passed']) && $_SESSION['passed'] === true )
		{
			if ( $this->cmpSessID($_SESSION['userID'], session_id()) )
			{
				$inRole = true;
				if($roleName != '')
				{
					$inRole = false; // default to false
					$roleMan = core_perms_RoleManager::getInstance();
					$inRole = $roleMan->doesUserHaveARole(array($roleName));
					if(!$inRole)
					{
						return core_util_Error::getError(4004);
					}
				}
				return $this->checkTimeOut() && $inRole;
			}
		}
        else
		{
			if(isset($_SESSION['userID']))
			{
				$this->logout($_SESSION['userID']);
			}
		}
		return false;
	}

	public function checkTimeOut()
	{
		
		// current time is past the 
		if(time() >= $_SESSION['timestamp'])
		{
			if($_SESSION['timestamp'] > 0)
			{
				core_util_Error::getError(3);
			}
			$this->logout($_SESSION['userID']);
			return false;
		}
		else
		{
			$_SESSION['timestamp'] = time() + AppCfg::AUTH_TIMEOUT; // refresh time limit
			return true;
		}
	}

	/**
	 * Logs the user out of the system, completely clearing the session variable and destroying the session.
	 * @param $userID (number) User ID to log out of the system
	 */	 	 	
	public function logout()
	{
		if(!headers_sent() && !isset($_SESSION))
		{
			session_name(AppCfg::SESSION_NAME);
			session_start();
		}
		// TODO: add tracking back in
		//$trackingMan = nm_los_TrackingManager::getInstance();
		//$trackingMan->trackLoggedOut();
		if($_SESSION['userID'] > 0)
		{
			// clear the users session id
			$qstr = "UPDATE ".cfg_core_User::TABLE." SET ".cfg_core_User::SID."='' WHERE ".cfg_core_User::ID."='?' LIMIT 1";
			$this->defaultDBM();
			if(!($q = $this->DBM->querySafe($qstr, $_SESSION['userID'])))
			{
				$this->DBM->rollback();
				trace(mysql_error(), true);
				return false;
			}
			// clear cache that may contain this user's data
			
			core_util_Cache::getInstance()->clearUserByID($_SESSION['userID']);
		}
		if(session_id())
		{
			$_SESSION = array();
			@session_destroy();
		}
	}


	/**
	 * Updates a user entry, trys to create a new one if the id is 0
	 * @param $usrObj (core_auth_User) updated user information
	 * @param $usrObj (core_auth_User) the same info you just gave it
	 * 
	 * @todo Maybe we don't need to return anything here?
	 */
	public function saveUser($usrObj, $optionalVars=0)
	{
		if(! core_util_Validator::isUserArr($usrObj) )
		{
			
			
			return core_util_Error::getError(2);
		}
		
		$roleMan = core_perms_RoleManager::getInstance();
		// current user must be superUser OR the same as the user to edit
		if(! $roleMan->isSuperUser() )
		{
			// check user is editing own info
			if( ($usrObj['userID'] == 0) || ($usrObj['userID'] != $_SESSION['userID'])) //if not current user
			{
				
				
				return core_util_Error::getError(4);
			}
		}
		// new user
		if($usrObj['userID'] == 0)
		{
			$mods = $this->getAllAuthModules();
			foreach($mods as $authMod)
			{
				$AM = call_user_func(array($authMod, 'getInstance'));

				$result = $AM ->createNewUser($usrObj['login'], $usrObj['first'], $usrObj['last'], $usrObj['mi'], $usrObj['email'], $optionalVars);
				if($result['success'])
				{
					return true;
				}
			}
			
			
			return core_util_Error::getError(0);
		}
		// edit user
		if($authMod = $this->getAuthModuleForUserID($usrObj['userID']))
		{
			$result = $authMod->updateUser($usrObj['userID'], $userObj['login'], $userObj['first'], $userObj['last'], $userObj['mi'], $userObj['email'], $optionalVars);
			if($result['success'] == true)
			{
				return true;
			}
			trace('Unable to update user.', true);
		}
		
		
		return core_util_Error::getError(0);
	}

	/**
	 * Generates a pretty-formatted version of a user's name
	 * @param $userID (number) user ID
	 * @return (string) The formatted name
	 * @return (bool) False if incorrect $userID
	 */
	public function getName($userID)
	{
		if(!core_util_Validator::isPosInt($userID))
		{
			
	       
	        return core_util_Error::getError(2);
		}

		$user = $this->fetchUserByID($userID);
		if($user)
		{
			$name = $user->first . ' ';
			//Put in a middle initial if it exists
			if ($user->mi != '')
			{
				$name .= $user->mi . ' ';
			}
			$name .= $user->last;
			return $name;
		}
		return false;
	}

	/**
	 * Returns an object that has each name field.
	 *
	 * @param $userID (Number) User ID
	 * @return Object
	 */
	// TODO: FIX RETURN FOR DB ABSTRACTION
	public function getNameObject($userID)
	{
		if($userID != 0)
		{
			$this->defaultDBM();
			$q = $this->DBM->querySafe("SELECT ".cfg_core_User::FIRST.", ".cfg_core_User::LAST.", ".cfg_core_User::MIDDLE." FROM ".cfg_core_User::TABLE." WHERE ".cfg_core_User::ID."='?' LIMIT 1", $userID);
			$r = $this->DBM->fetch_assoc($q);
			return $r;
		}
		return false;
	}

	public function getUserName($userID)
	{
		if($authMod = $this->getAuthModuleForUserID($userID))
		{
			return $authMod->getUserName($userID);
		}
		return false;
	}

	public function getSessionID()
	{
		return session_id();
	}

	/**
	 * Changes the password for the currently logged-in user
	 * @param $oldpass (string) old password
	 * @param $newpass (string) new password
	 * @return (bool) True if successful, False if incorrect old password
	 */
	public function changePassword($oldpass, $newpass)
	{
		// start session if it hasnt been
		if(!headers_sent() && !isset($_SESSION))
		{
			session_name(AppCfg::SESSION_NAME);
			session_start();
		}
		if($authMod = $this->getAuthModuleForUserID($_SESSION['userID']))
		{
			// does the authmod allow changing passwords?
			if(constant(get_class($authMod) . '::CAN_CHANGE_PW') == false) return false;
			// is the old pass valid
			if($authMod->validatePassword($oldpass) !== true) return false;
			// is the new pass valid
			if($authMod->validatePassword($newpass) !== true) return false;
			// are the two passwords different
			if($oldpass == $newpass) return false;
			// can we get the user
			if(! ($user = $authMod->fetchUserByID($_SESSION['userID']))) return false;
			// is the old password valid
			if(! $authMod->verifyPassword($user, $oldpass)) return false;
			// set the password
			if($authMod->dbSetPassword($_SESSION['userID'], $newpass))
			{
				$_SESSION['passed'] = true;
				return true;
			}
		}
		trace('Unable to update password.', true);
		return false;
	}

	public function requestPasswordReset($username, $email, $returnURL)
	{
		$username = trim($username);
		trace('looking to reset, finding authmod for user '. $username);
		$authMod = $this->getAuthModuleForUsername($username);
		if($authMod)
		{
			return $authMod->requestPasswordReset($username, $email, $returnURL);
		}
		return false;
	}
	/*
	public function resetPassword($username)
	{
		$username = trim($username);
		$authMod = $this->getAuthModuleForUsername($username);
		if($authMod)
		{
			return $authMod->resetPassword($username);
		}
		return false;
	}
	*/
	public function changePasswordWithKey($username, $key, $newpass)
	{
		$username = trim($username);
		$authMod = $this->getAuthModuleForUsername($username);
		if($authMod)
		{
			return $authMod->changePasswordWithKey($username, $key, $newpass);
		}
		return false;

	}
	
	
	/**
	 * Handle checking all the authentication modules in order of priority
	 *
	 * @return core_auth_User_User on success, or false on failure
	 * @author Ian Turgeon
	 **/
	// security check: Ian Turgeon 2008-05-06 - PASS
	// TODO: make protected
	public function authenticate($requestVars, $authID=0 )
	{
		// get array of authmodules in order, first to last
		$authModList = $this->getAllAuthModules();
		// loop through authmods
		if(count($authModList > 0))
		{
			foreach($authModList AS $authMod)
			{
				$authModule = call_user_func(array($authMod, 'getInstance'));
				//trace($class.' attempting to authenticate');
				// attempt to authenticate each in order
				if($authModule->authenticate($requestVars))
				{
					// keep record of the authmod used
					if($_SESSION['passed'] === true)
					{
						//now make sure their password is up to date
						$user =  $authModule->getUser();
						if(!$authModule->isPasswordCurrent($user->userID))
						{

							$_SESSION['passed'] = false;
						}
					}
					return true;
				}
				else{
					//trace('auth failed, authmod:'. $authMod->{cfg_core_AuthMan::MOD_CLASS});
				}
			}
		}
		return false;
	}

	// security check: Ian Turgeon 	2008-05-12 - FAIL ish (need to make sure this isnt used directly by the api)
	public function getAllUsers(){
		// check memcache
		
		if($allUsers = core_util_Cache::getInstance()->getAllUsers)
		{
				return $allUsers;
		}
	
		// get array of authmodules in order, first to last
		$authMods = $this->getAllAuthModules();
		$allUsers = array();
		// loop through authmods
		foreach($authMods AS $authMod)
		{
			$authModule = call_user_func(array($authMod, 'getInstance'));
			if($modUsers = $authModule->getAllUsers())
			{
				// keep record of the authmod used
				// return user
				$allUsers = array_merge($allUsers, $modUsers);
			}
		}
		core_util_Cache::getInstance()->setAllUsers($allUsers); // store in memcache
		return $allUsers;
	}

	// remove all records for this user 
	public function removeUser($userID)
	{
		if(!core_util_Validator::isPosInt($userID))
		{
			
			
			return core_util_Error::getError(2);
		}
		
		$roleMan = core_perms_RoleManager::getInstance();
		if(!$roleMan->isSuperUser())
		{
			
			
			return core_util_Error::getError(4);
		}
		
		if($userID == $_SESSION['userID'])
		{
			
			
			return core_util_Error::getError(0);
		}
		
		$result = false;
		$authMods = $this->getAllAuthModules();
		foreach($authMods AS $authMod)
		{
		//	trace('removing from am '. $authMod->cfg_core_AuthMan::MOD_CLASS);

			$authModule = call_user_func(array($authMod, 'getInstance'));
			$thisResult =  $authModule->removeRecord($userID);
			$result = $result || $thisResult;
		}
		return $result;

	}

	/**
	 * Gets all active authentication modules ordered by their priority from first [0] to last [length-1]
	 *
	 * @return array of authmodules
	 **/
	// TODO: FIX RETURN FOR DB ABSTRACTION
	public function getAllAuthModules()
	{
		$authMods = explode(',', AppCfg::AUTH_PLUGINS);
		return $authMods;
	}

	public function getAuthModuleForUserID($userID=false)
	{
		if($userID !== false)
		{
			// check memcache
			
			if($authModClass = core_util_Cache::getInstance()->getAuthModClassForUser($userID))
			{
				return new $authModClass();
			}
			$authMods = $this->getAllAuthModules();
			foreach($authMods AS $authData)
			{

			// use Reflection to create a new instance, using the $args 
				$authMod = call_user_func(array($authData, 'getInstance'));
				if($authMod->recordExistsForID($userID))
				{
					// store in memcache
					core_util_Cache::getInstance()->setAuthModClassForUser($userID, $authData);
					
					return $authMod;
				}
			}
		}
		trace('couldnt fetch user authmod: ' . $userID, true);
		return false;
	}

	public function getAuthModuleForUsername($username=false)
	{
		if($username !== false)
		{
			// check memcache
			
			if($authModClass = core_util_Cache::getInstance()->getAuthModForUser($username))
			{
				return call_user_func(array($authModClass, 'getInstance'));
			}

			$authMods = $this->getAllAuthModules();

			foreach($authMods AS $authData)
			{
				$authMod = call_user_func(array($authData, 'getInstance'));
				if($authMod->getUIDforUsername($username))
				{
					// store in memcache
					core_util_Cache::getInstance()->setAuthModForUser($username, $authData);
					return $authMod;
				}
			}
		}
		return false;
	}

}
?>