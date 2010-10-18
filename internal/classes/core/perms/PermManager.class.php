<?php
/*

	ACL Model
	The perms system is additive, meaning perms can only be added, not removed by any given permisssion.  by default no permissions exist
	
	Items: Each Item of a specific item type has permissions, this would allow items to essentially have permissions for every user
	
	Groups: Each User Group or Role is given blanket permissions.  this allows certain roles to gain access to items
	
	Users Mapped to Groups: Each user can be given ther permissions from a group.
	
	Users Mapped to Items: Each user can have permissions for specific items.  This allows multiple people to own an item

*/
abstract class core_perms_PermManager extends core_db_dbEnabled
{
	private static $instance; // singleton instance reference
	
	static public function getInstance()
	{
		if(!isset(self::$instance))
		{
			$selfClass = __CLASS__;
			self::$instance = new $selfClass();
		}
		return self::$instance;
	}
	
	public function __construct()
	{
		$this->defaultDBM();
	}
	
	abstract public function getAllItemIDs($itemType);
	
	public function getAllGroupsForUser($userID = 0)
	{
		$groupIDs = array();
		$qstr = 	"SELECT R.".cfg_core_Role::ID.", R.".cfg_core_Role::ROLE." 
			 FROM ".cfg_core_Role::MAP_USER_TABLE." AS M, ".cfg_core_Role::TABLE." AS R
			WHERE M.".cfg_core_User::ID."='?' AND M.".cfg_core_Role::ID." = R.".cfg_core_Role::ID."";
		// return logged in user's roles if id is 0 or less, non su users can only use this method
		if($userID <= 0 || $userID == $_SESSION['userID'])
		{
			if(!($q = $this->DBM->querySafe($qstr, $_SESSION['userID'])))
			{
				return false;
			}
			
			while($r = $this->DBM->fetch_obj($q))
			{
				$groupIDs[] = $r;
			}
		}
		// su can return a anyone's roles
		else
		{
			// TODO: add this back
			// if($this->isSuperUser())
			// {	
			// 	if(!($q = $this->DBM->querySafe($qstr, $userID)))
			// 	{
			// 		trace(mysql_error(), true);
			// 		return false;
			// 	}
			// 	
			// 	while($r = $this->DBM->fetch_obj($q))
			// 	{
			// 		$groupIDs[] = $r;
			// 	}
			// }
			// else
			// {
				trace(' not super user.', true);
				return false;
			//}
		}
		return $groupIDs;
	}
		
	public function getAllItemsForUser($userID, $itemType, $includeGroupRights=true)
	{
		/** Validate Input **/
		
		if(!core_util_Validator::isPosInt($userID))
		{
			
			return core_util_Error::getError(2);
		}
		
		if(!core_util_Validator::isPermItemType($itemType))
		{
			
			return core_util_Error::getError(2);
		}
		$allItems = array();
		
		if($includeGroupRights)
		{
			// first get user's roles, they may have rights based on their roles
			$groups = $this->getAllGroupsForUser($userID);
			$groupIDs = array();
			foreach($groups AS $value)
			{
				$groupIDs[] = $value->{cfg_core_Role::ID};
			}
			$groupPerms = $this->getGlobalPermsForGroups($groupIDs);
			// if they have global perms that arent group perms, retreive all items of type because the perms apply to everything
			if(count($groupPerms) > 0 && $groupPerms[0] < cfg_core_Perm::MIN_GROUP_VALUE)
			{
				$idArray = $this->getAllItemIDs($itemType);
				foreach($idArray AS $id)
				{
					$allItems[$id] = $groupPerms;
				}
			}
		}
		
		// get explicitly set perms
		$query = "SELECT ".cfg_core_Perm::PERM.", ".cfg_core_Perm::ITEM." FROM ".cfg_core_Perm::TABLE." WHERE ".cfg_core_Perm::TYPE." = '?' AND ".cfg_core_Perm::ITEM." > 0 AND ".cfg_core_User::ID." = '?' ";
		$q = $this->DBM->querySafe($query, $itemType, $userID);
		while($r = $this->DBM->fetch_obj($q))
		{
			$itemID = $r->{cfg_core_Perm::ITEM};
			if(!isset($allItems[$itemID]))
			{
				$allItems[$itemID] = array();
			}
			$allItems[$itemID][] = $r->{cfg_core_Perm::PERM};
		}
		return $allItems;
	}
	

	public function getAllUsersIDsForItem($itemType, $itemID, $includeGroupRights=false)
	{
		/** Validate Input **/
		
		if(!core_util_Validator::isPosInt($itemID))
		{
			
			return core_util_Error::getError(2);
		}
		
		if(!core_util_Validator::isPermItemType($itemType))
		{
			
			return core_util_Error::getError(2);
		}
		// TODO: enable group rights option, this would need to find users that are in groups that have rights to this item
		if($includeGroupRights == true)
		{
			// we may not need to do this
		}
		$users = array();
		$query = "SELECT ".cfg_core_User::ID.", ".cfg_core_Perm::PERM." FROM ".cfg_core_Perm::TABLE." WHERE ".cfg_core_Role::ID." ='0' AND ".cfg_core_Perm::TYPE." = '?' AND ".cfg_core_Perm::ITEM." = '?'";
		$q = $this->DBM->querySafe($query, $itemType, $itemID);
		while($r = $this->DBM->fetch_obj($q))
		{
			if(!isset($users[$r->{cfg_core_User::ID}]))
			{
				$users[$r->{cfg_core_User::ID}] = array();
			}
			// note, the perms may be redundant if there is more then one entry in the database with the same perms
			$users[$r->{cfg_core_User::ID}][] = $r->{cfg_core_Perm::PERM};
		}
		
		return $users;
	}
	
	public function getPermsForUserToItem($userID, $itemType, $itemID)
	{
		/** Validate Input **/
		
		if(!core_util_Validator::isPosInt($userID))
		{
			
			return core_util_Error::getError(2);
		}
		
		if(!core_util_Validator::isPermItemType($itemType))
		{
			
			return core_util_Error::getError(2);
		}
		
		if(!core_util_Validator::isPosInt($itemID))
		{
			
			return core_util_Error::getError(2);
		}
		
		if($perms = core_util_Cache::getInstance()->getPermsForUserToItem($userID, $itemType, $itemID))
		{
			return $perms;
		}
		
		$perms = array();
		$query = "SELECT ".cfg_core_Perm::PERM." FROM ".cfg_core_Perm::TABLE." WHERE ".cfg_core_Role::ID." ='0' AND ".cfg_core_Perm::TYPE." = '?' AND ".cfg_core_Perm::ITEM." = '?' AND ".cfg_core_User::ID." = '?'";
		$q = $this->DBM->querySafe($query, $itemType, $itemID, $userID);
		while($r = $this->DBM->fetch_assoc($q))
		{
			$perms[] = $r[cfg_core_Perm::PERM];
		}
		
		core_util_Cache::getInstance()->setPermsFOrUserToItem($userID, $itemType, $itemID, $perms);
		
		return $perms;
		
	}
	public function setPermsForUserToItem($userID, $itemType, $itemID, $addPerms, $remPerms)
	{
		/** Validate Input **/
		
		if(!core_util_Validator::isPosInt($userID))
		{
			
			return core_util_Error::getError(2);
		}
		
		if(!core_util_Validator::isPermItemType($itemType))
		{
			
			return core_util_Error::getError(2);
		}
		
		if(!core_util_Validator::isPosInt($itemID))
		{
			
			return core_util_Error::getError(2);
		}
		trace($addPerms);
		// allow non array input, but convert it to an array so we can deal with it easily
		if($this->_validatePermArray($addPerms) == false)
		{
			
			return core_util_Error::getError(2);
		}
		
		// allow non array input, but convert it to an array so we can deal with it easily
		if($this->_validatePermArray($remPerms) == false)
		{
			
			return core_util_Error::getError(2);
		}
		
		$query = "INSERT IGNORE INTO ".cfg_core_Perm::TABLE." SET ".cfg_core_Role::ID." ='0', ".cfg_core_Perm::PERM." = '?', ".cfg_core_User::ID." = '?', ".cfg_core_Perm::ITEM." = '?', ".cfg_core_Perm::TYPE." = '?'";
		foreach($addPerms AS $perm)
		{
			$q = $this->DBM->querySafe($query, $perm, $userID, $itemID, $itemType);
		}
		
		$query = "DELETE FROM ".cfg_core_Perm::TABLE." WHERE ".cfg_core_Role::ID." = 0 AND ".cfg_core_Perm::PERM." = '?' AND ".cfg_core_User::ID." = '?' AND ".cfg_core_Perm::ITEM." = '?' AND ".cfg_core_Perm::TYPE." = '?'";
		foreach($remPerms AS $perm)
		{
			$q = $this->DBM->querySafe($query, $perm, $userID, $itemID, $itemType);
		}
		
		core_util_Cache::getInstance()->clearPermsFOrUserToItem($userID, $itemType, $itemID);
		return true;
	}
	
	public function getGlobalPermsForGroups($groupIDs)
	{
		
		if($this->_validateGroupArray($groupIDs) == false)
		{
			
			
			return core_util_Error::getError(2);
		}
		$perms = array();
		
		foreach($groupIDs AS $groupID)
		{
			/** Get From Cache **/
			if($cachedPerms = core_util_Cache::getInstance()->getPermsForGroup($groupID))
			{
				$perms = array_merge($perms, $cachedPerms);
			}
			else
			{
				/** Get From DB **/
				$dbPerms = array();
				$query = "SELECT ".cfg_core_Perm::PERM." FROM ".cfg_core_Perm::TABLE." WHERE ".cfg_core_Role::ID." = '?' AND ".cfg_core_Perm::TYPE." ='0' AND ".cfg_core_Perm::ITEM."='0' AND ".cfg_core_User::ID." = '0' ";
				$q = $this->DBM->querySafe($query, $groupID);
				while($r = $this->DBM->fetch_obj($q))
				{
					$dbPerms[] = $r->{cfg_core_Perm::PERM};
				}
				core_util_Cache::getInstance()->setPermsForGroup($groupID, $dbPerms);
				$perms = array_merge($perms, $dbPerms);
			}
		}
		if(count($perms) > 1)
		{
			return sort(array_unique($perms), SORT_NUMERIC); // only sort if there are more then 1
		}
		else
		{
			return $perms; 
		}
	}
	
	public function setGlobalPermsForGroup($groupID, $addPerms, $remPerms)
	{
		
		if(!core_util_Validator::isPosInt($groupID))
		{
			
			return core_util_Error::getError(2);
		}
		
		// allow non array input, but convert it to an array so we can deal with it easily
		if($this->_validatePermArray($addPerms, true) == false)
		{
			
			return core_util_Error::getError(2);
		}
		
		// allow non array input, but convert it to an array so we can deal with it easily
		if($this->_validatePermArray($remPerms, true) == false)
		{
			
			return core_util_Error::getError(2);
		}
		
		$query = "INSERT IGNORE INTO ".cfg_core_Perm::TABLE." SET ".cfg_core_Role::ID." = '?', ".cfg_core_Perm::PERM." = '?', ".cfg_core_User::ID." ='0', ".cfg_core_Perm::ITEM." ='0', ".cfg_core_Perm::TYPE." ='0'";
		foreach($addPerms AS $perm)
		{
			$q = $this->DBM->querySafe($query, $groupID, $perm);
		}
		
		$query = "DELETE FROM ".cfg_core_Perm::TABLE." WHERE ".cfg_core_Role::ID." = '?' AND ".cfg_core_Perm::PERM." = '?' AND ".cfg_core_User::ID." ='0' AND ".cfg_core_Perm::ITEM." ='0' AND ".cfg_core_Perm::TYPE." ='0'";
		foreach($remPerms AS $perm)
		{
			$q = $this->DBM->querySafe($query, $groupID, $perm);
		}
		
		core_util_Cache::getInstance()->clearPermsForGroup($groupID);
		return true;
	}
	
	public function getPermsForItem($itemType, $itemID)
	{
		
		/** Validate Input **/
		if(!core_util_Validator::isPermItemType($itemType))
		{
			
			return core_util_Error::getError(2);
		}
		
		if(!core_util_Validator::isPosInt($itemID))
		{
			
			return core_util_Error::getError(2);
		}
		
		/** Get From Cache **/
		if($perms = core_util_Cache::getInstance()->getPermsForItem($itemType, $itemID))
		{
			return $perms;
		}
		
		/** Get From DB **/
		$perms = array();
		$query = "SELECT ".cfg_core_Perm::PERM." FROM ".cfg_core_Perm::TABLE." WHERE ".cfg_core_Perm::TYPE." = '?' AND ".cfg_core_Perm::ITEM." = '?' AND ".cfg_core_User::ID." ='0' AND ".cfg_core_Role::ID." ='0'";
		$q = $this->DBM->querySafe($query, $itemType, $itemID);
		while($r = $this->DBM->fetch_assoc($q))
		{
			$perms[] = $r[cfg_core_Perm::PERM];
		}
		
		core_util_Cache::getInstance()->setPermsForItem($itemType, $itemID, $perms);
		return $perms;
	}
	public function setPermsForItem($itemType, $itemID, $addPerms, $remPerms)
	{
		
		/** Validate Input **/
		if(!core_util_Validator::isPermItemType($itemType))
		{
			
			return core_util_Error::getError(2);
		}
		
		if(!core_util_Validator::isPosInt($itemID))
		{
			
			return core_util_Error::getError(2);
		}
		
		// allow non array input, but convert it to an array so we can deal with it easily
		if($this->_validatePermArray($addPerms) == false)
		{
			
			return core_util_Error::getError(2);
		}
		
		// allow non array input, but convert it to an array so we can deal with it easily
		if($this->_validatePermArray($remPerms) == false)
		{
			
			return core_util_Error::getError(2);
		}
		
		// add perms
		$query = "INSERT IGNORE INTO ".cfg_core_Perm::TABLE." SET ".cfg_core_Perm::TYPE." = '?', ".cfg_core_Perm::ITEM." = '?', ".cfg_core_Perm::PERM." = '?', ".cfg_core_Role::ID." ='0'";
		foreach($addPerms AS $perm)
		{
			$this->DBM->querySafe($query, $itemType, $itemID, $perm);
		}
		
		// remove perms
		$query = "DELETE FROM ".cfg_core_Perm::TABLE." WHERE ".cfg_core_Perm::TYPE." = '?' AND ".cfg_core_Perm::ITEM." = '?' AND ".cfg_core_Perm::PERM." = '?' AND ".cfg_core_Role::ID." ='0' ";
		foreach($remPerms AS $perm)
		{
			$this->DBM->querySafe($query, $itemType, $itemID, $perm);
		}
		
		core_util_Cache::getInstance()->clearPermsForItem($itemType, $itemID);
		return true;
	}
	
	protected function _validatePermArray(&$perms, $allowGroupPerms=false)
	{
		// allow non array input, but convert it to an array so we can deal with it easily
		if(!is_array($perms) && !empty($perms))
		{
			$perms = array($perms);
			trace($perms);
		}
		// search for invalid input, die if invalid
		if(count($perms) > 0)
		{
			foreach($perms AS $perm)
			{
				if(!core_util_Validator::isPerm2($perm) || ($allowGroupPerms == false && $perm > cfg_core_Perm::MIN_GROUP_VALUE) )
				{
					return false;
				}
			}
		}
		return true;
	}
	
	protected function _validateGroupArray(&$groups)
	{
		// allow non array input, but convert it to an array so we can deal with it easily
		if(!is_array($groups) && !empty($groups))
		{
			$groups = array($groups);
		}
		// search for invalid input, die if invalid
		if(count($groups) > 0)
		{
			foreach($groups AS $group)
			{
				// if its not a valid groupID
				if(!core_util_Validator::isPosInt($group))
				{
					return false;
				}
			}
			return true;
		}
	}
	
	public function getPermsForUserToItemCombined($userID, $itemType, $itemID){}
	
	public function clearPermsForItem($itemType, $itemID)
	{
		// TODO: user must be logged in and be someone who can delete things, maybe user should have delete rights to the item?
		// remove perms
		$query = "DELETE FROM ".cfg_core_Perm::TABLE." WHERE ".cfg_core_Perm::TYPE." = '?' AND ".cfg_core_Perm::ITEM." = '?' ";
		$this->DBM->querySafe($query, $itemType, $itemID);

		
		core_util_Cache::getInstance()->clearPermsForItem($itemType, $itemID);
		
	}
	public function clearPermsForGroup($groupID){}
	public function clearPermsForUser($userID){}
}
?>