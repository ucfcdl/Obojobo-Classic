<?php
class plg_UCFCourses_UCFCourses extends nm_los_CoursePlugin
{
	protected $oDBM;
	private static $instance;
	
	static public function getInstance()
	{
		if(!isset(self::$instance))
		{
			$selfClass = __CLASS__;
			self::$instance = new $selfClass();
		}
		return self::$instance;
	}

	// security check: Ian Turgeon 2008-05-06 - PASS
	protected function defaultDBM()
	{
		if(!$this->oDBM) // if DBM isnt set use the default
		{ 
			// load this module's config
			$con = new core_db_dbConnectData(cfg_plugin_UCFCourses::DB_HOST, cfg_plugin_UCFCourses::DB_USER, cfg_plugin_UCFCourses::DB_PASS, cfg_plugin_UCFCourses::DB_NAME, cfg_plugin_UCFCourses::DB_TYPE);
			$this->oDBM = core_db_DBManager::getConnection($con);
		}
		parent::defaultDBM(); // build default dbm still for use with internal db
	}
	
	// get a course that we have an internal record for
	public function getCourse($course)
	{
		// TODO: cache this once per day
		trace($course);
		// check to make sure stored course is for the current term, if its not, we cant rely on the external data
		$CM = nm_los_CourseManager::getInstance();
		$sem = $CM->getCurrentSemester();
		if($sem->semesterID == $course->semesterID)
		{
			$this->defaultDBM();
			$q = $this->oDBM->querySafe("SELECT * FROM " . cfg_plugin_UCFCourses::COURSE_TABLE . " WHERE " . cfg_plugin_UCFCourses::ID . " = '?'", $course->regKey);
			if($r = $this->oDBM->fetch_obj($q))
			{
				$course->regKey = $r->{cfg_plugin_UCFCourses::ID};
				$course->prefix = $r->{cfg_plugin_UCFCourses::PREFIX};
				$course->number = $r->{cfg_plugin_UCFCourses::NUMBER};
				$course->section = $r->{cfg_plugin_UCFCourses::SECTION};
				$course->title = $r->{cfg_plugin_UCFCourses::TITLE};
				$course->college = $r->{cfg_plugin_UCFCourses::COLLEGE};
				$course->dept = $r->{cfg_plugin_UCFCourses::DEPT};
				$course->pluginName = 'UCFCourses';
				// get owner user obj
				$AM = core_auth_AuthManager::getInstance();
				$course->owner = $AM->fetchUserByID($$r->{cfg_plugin_UCFCourses::NID});
				
				// now see if we have a matching internal record
				
				$q = $this->DBM->querySafe("SELECT * FROM ".cfg_obo_Course::TABLE." WHERE ".cfg_obo_Course::PLUGIN." = 'UCFCourses' AND ".cfg_obo_Course::REG_KEY." = '?'", $course->regKey);
				if($r = $this->DBM->fetch_obj($q))
				{
					$course->courseID = $r->{cfg_obo_Course::ID};
				}
			}
			// couldn't find the course by the registration key, it must have been removed
			return false;
		}
		return $course;
	}
	
	public function getMyCourses()
	{
		//TODO: Cache this once per day
		$this->defaultDBM();
		
		$courses = array();
		$AM = core_auth_AuthManager::getInstance();
		$NID = $AM->getUserName($_SESSION['userID']);
		$roleMan = core_perms_RoleManager::getInstance();
		if($roleMan->isSuperUser())
		{
			$q = $this->oDBM->query("SELECT * FROM NM_COURSE");
		}
		else
		{
			$q = $this->oDBM->query("SELECT * FROM " . cfg_plugin_UCFCourses::COURSE_TABLE . " WHERE " . cfg_plugin_UCFCourses::NID . " = '$NID'");
		}
		
		while($r = $this->oDBM->fetch_obj($q))
		{
			$courses[] = new nm_los_Course($r->{cfg_plugin_UCFCourses::ID}, $r->{cfg_plugin_UCFCourses::PREFIX}, $r->{cfg_plugin_UCFCourses::NUMBER}, $r->{cfg_plugin_UCFCourses::SECTION}, $r->{cfg_plugin_UCFCourses::TITLE}, $r->{cfg_plugin_UCFCourses::COLLEGE}, $r->{cfg_plugin_UCFCourses::DEPT}, $r->{cfg_plugin_UCFCourses::NID}, 'UCFCourses');
		}
		return $courses;
	}
	
	// public function getCourseIDForInstance($instID)
	// {
	// 	if(!core_util_Validator::isPosInt($instID))
	// 	{
	// 		
	// 		
	// 		$result = core_util_Error::getError(2);
	// 	}
	// 	
	// 	$API = nm_los_API::getInstance();
	// 	if($API->getSessionValid())
	// 	{
	// 		// TODO: do something
	// 	}
	// }
	
	// public function setCourseIDForInstance($instID, $courseID)
	// {
	// 	if(!core_util_Validator::isPosInt($instID))
	// 	{
	// 		
	// 		
	// 		$result = core_util_Error::getError(2);
	// 	}
	// 	if(!core_util_Validator::isPosInt($courseID, true))
	// 	{
	// 		
	// 		
	// 		$result = core_util_Error::getError(2);
	// 	}
	// 	
	// 	$API = nm_los_API::getInstance();
	// 	if($API->getSessionValid())
	// 	{
	// 		// TODO: do something
	// 	}
	// }
	
	// public function getCourseStudents($regKey)
	// {
	// 	if(!core_util_Validator::isPosInt($regKey))
	// 	{
	// 		
	// 		
	// 		$result = core_util_Error::getError(2);
	// 	}
	// 	
	// 	$API = nm_los_API::getInstance();
	// 	if($API->getSessionValid())
	// 	{
	// 		$roleMan = nm_los_RoleManager::getInstance();
	// 		if(!$roleMan->isSuperUser()) // if the current user is not SuperUser
	// 		{
	// 			if(!$roleMan->isLibraryUser())
	// 			{
	// 				
	// 				
	// 				return core_util_Error::getError(4);
	// 			}
	// 
	// 		}
	// 		// make sure this is my class
	// 		
	// 		
	// 		if($students = core_util_Cache::getInstance()->getCourseStudents($regKey))
	// 		{
	// 			return $students;
	// 		}
	// 		
	// 		// passed perm requirements
	// 		$con = new core_db_dbConnectData(cfg_plugin_UCFCourses::DB_HOST, cfg_plugin_UCFCourses::DB_USER, cfg_plugin_UCFCourses::DB_PASS, cfg_plugin_UCFCourses::DB_NAME, cfg_plugin_UCFCourses::DB_TYPE);
	// 		$DBM = core_db_DBManager::getConnection($con);
	// 		
	// 		$students = array();
	// 		$UCFAuthMod = plg_UCFAuth_UCFAuthModule::getInstance();
	// 		
	// 		$q = $DBM->querySafe("SELECT * FROM " . cfg_plugin_UCFCourses::ROLL_TABLE . "  WHERE ". cfg_plugin_UCFCourses::ID." = '?'", $regKey);
	// 		while($r = $DBM->fetch_obj($q))
	// 		{
	// 			$user = $UCFAuthMod->syncExternalUser($r->{cfg_plugin_UCFCourses::NID});
	// 			trace($user);
	// 			if($user instanceof core_auth_User)
	// 			{
	// 				$students[] = $user;
	// 			}
	// 		}
	// 		core_util_Cache::getInstance()->setCourseStudents($regKey, $students);
	// 		return $students;
	// 	}
	// 	else
	// 	{
	// 		
	// 		
	// 		return core_util_Error::getError(1);
	// 	}
	// 
	// }
	
	// public function updateCourse($courseID)
	// {
	// 	if(!core_util_Validator::isPosInt($courseID))
	// 	{
	// 		
	// 		
	// 		$result = core_util_Error::getError(2);
	// 	}
	// 	
	// 	$API = nm_los_API::getInstance();
	// 	if($API->getSessionValid())
	// 	{
	// 		$con = new core_db_dbConnectData(cfg_plugin_UCFCourses::DB_HOST, cfg_plugin_UCFCourses::DB_USER, cfg_plugin_UCFCourses::DB_PASS, cfg_plugin_UCFCourses::DB_NAME, cfg_plugin_UCFCourses::DB_TYPE);
	// 		$DBM = core_db_DBManager::getConnection($con);
	// 		$q = $DBM->querySafe("SELECT * FROM NM_COURSE WHERE ".cfg_plugin_UCFCourses::ID." = '?'", $courseID);
	// 	
	// 		if($r = $DBM->fetch_obj($q))
	// 		{
	// 			$AM = core_auth_AuthManager::getInstance();
	// 			$owner = $AM->fetchUserByID($r->{cfg_plugin_UCFCourses::NID});
	// 			
	// 			$CM = nm_los_CourseManager::getInstance();
	// 			$curSemester = $CM->getCurrentSemester();
	// 			$course = new nm_los_Course($r->{cfg_plugin_UCFCourses::ID}, $r->{cfg_plugin_UCFCourses::PREFIX}, $r->{cfg_plugin_UCFCourses::NUMBER}, $r->{cfg_plugin_UCFCourses::SECTION}, $r->{cfg_plugin_UCFCourses::TITLE}, $r->{cfg_plugin_UCFCourses::COLLEGE}, $r->{cfg_plugin_UCFCourses::DEPT}, $owner, $curSemester->SemesterID, 'UCFCourses');
	// 			return $course;
	// 		}
	// 	}
	// 	return false;
	// }
	
	// protected function isMyCourse($regKey)
	// {
	// 	$roleMan = nm_los_RoleManager::getInstance();
	// 	// just allow super user to see everything
	// 	if($roleMan->isSuperUser())
	// 	{
	// 		return true;
	// 	}
	// 	
	// 	$courses = $this->getMyCourses();
	// 	foreach($courses AS $course)
	// 	{
	// 		if($course->{cfg_plugin_UCFCourses::ID} == $regKey)
	// 		{
	// 			return true;
	// 		}
	// 	}
	// 
	// 	return false;
	// }
	
}
?>