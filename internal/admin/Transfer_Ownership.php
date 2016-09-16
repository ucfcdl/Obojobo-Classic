<?php
if(!$_REQUEST['from_user'] || !$_REQUEST['to_user'])
{

	?>
	<h1>Transfer Ownership of from user A to user B:</h1>
	<form action="<?php $_SERVER['PHP_SELF']?>" method="get" accept-charset="utf-8">
	
		<label for="from_user">From User A:</label><input type="text" name="from_user" value="" id="from_user">(userID)<br/>
		<label for="to_user">To User B :</label><input type="text" name="to_user" value="" id="to_user">(userID)<br/>
		<label for="instances">Instances</label><input type="checkbox" name="instances" value="instances" id="instances"><br/>
		<label for="learning_objects">Learning Objects</label><input type="checkbox" name="learning_objects" value="los" id="learning_objects"><br/>
		<label for="media">Media</label><input type="checkbox" name="media" value="media" id="media"><br/><br/>
		
		<p><input type="submit" value="Continue &rarr;"></p>
	</form>


	<?php
}
else
{

	$DBM = \rocketD\db\DBManager::getConnection(new \rocketD\db\DBConnectData(\AppCfg::DB_HOST, \AppCfg::DB_USER, \AppCfg::DB_PASS, \AppCfg::DB_NAME, \AppCfg::DB_TYPE));

	if($_REQUEST['doit'] != 1) $DBM->startTransaction();
	
	
	// instances
	if($_REQUEST['instances'])
	{
		// change ownership
		$qstr = "UPDATE ".\cfg_obo_Instance::TABLE." SET ".\cfg_core_User::ID." = '?' WHERE ".\cfg_core_User::ID." = '?' ";
		$DBM->querySafe($qstr, $_REQUEST['to_user'], $_REQUEST['from_user']);
		$numInstances = $DBM->affected_rows();
	
		// change sharing options
		$qstr = "UPDATE IGNORE ".\cfg_obo_Perm::TABLE." SET ".\cfg_core_User::ID." = '?' WHERE ".\cfg_core_User::ID." = '?' AND ".\cfg_core_Perm::TYPE." =  '".\cfg_obo_Perm::TYPE_INSTANCE."'";
		$DBM->querySafe($qstr, $_REQUEST['to_user'], $_REQUEST['from_user']);
		$numInstances += $DBM->affected_rows();
	}

	// LOs
	if($_REQUEST['learning_objects'])
	{
		// update all ownership/sharing permissions for los
		$qstr = "UPDATE IGNORE ".\cfg_obo_Perm::TABLE." SET ".\cfg_core_User::ID." = '?' WHERE ".\cfg_core_User::ID." = '?' AND ".\cfg_core_Perm::TYPE." =  '".\cfg_obo_Perm::TYPE_LO."'";
		$DBM->querySafe($qstr, $_REQUEST['to_user'], $_REQUEST['from_user']);
		$numLOs = $DBM->affected_rows();
	}

	// Media
	if($_REQUEST['media'])
	{
		// change ownership
		$qstr = "UPDATE ".\cfg_obo_Media::TABLE." SET ".\cfg_core_User::ID." = '?' WHERE ".\cfg_core_User::ID." = '?' ";
		$DBM->querySafe($qstr, $_REQUEST['to_user'], $_REQUEST['from_user']);
		$numMedia = $DBM->affected_rows();
		
		// update all ownership/sharing permissions for los
		$qstr = "UPDATE IGNORE ".\cfg_obo_Perm::TABLE." SET ".\cfg_core_User::ID." = '?' WHERE ".\cfg_core_User::ID." = '?' AND ".\cfg_core_Perm::TYPE." =  '".\cfg_obo_Perm::TYPE_MEDIA."'";
		$DBM->querySafe($qstr, $_REQUEST['to_user'], $_REQUEST['from_user']);
		$numMedia += $DBM->affected_rows();
	}

	if($_REQUEST['doit'] != 1)
	{
		echo "Instances: $numInstances, LOs: $numLOs, Media: $numMedia";
		echo '<br/><a href="'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'].'&doit=1\">Ok, Do it</a>';
		$DBM->rollBack();
	}
	
}
