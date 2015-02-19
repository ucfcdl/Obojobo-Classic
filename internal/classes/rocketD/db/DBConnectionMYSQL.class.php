<?php
namespace rocketD\db;
class DBConnectionMYSQL extends DBConnection
{
	
	public function db_connect()
	{
		// dont connect if already connected
		if($this->connected == false)
		{
			$connID = @mysql_connect($this->connData->host, $this->connData->user, $this->connData->pass, true);
			if($connID)
			{
				$this->connected = true;
				$this->connData->connID = $connID;
				$this->db_select($this->connData->db);
				$this->connData->host = 0;
				$this->connData->user = 0;
				$this->connData->pass = 0;
				return true;
			}
		}
		return false;
	}
		
	public function db_select($db_name='')
	{
		$return = @mysql_select_db($db_name, $this->connData->connID);
		return $return;
	}

	public function query($query)
	{
		if( !($return = @mysql_query($query, $this->connData->connID)))
		{
			trace('query error  :'.$query, true);
			trace(mysql_error(), true);
			trace(array_slice(debug_backtrace(), 0, 3));
		}
		$this->insertID = @mysql_insert_id($this->connData->connID);
		return $return;
	}
	
	public function queryTrace($query)
	{
		trace($query);
		return $this->query($query);
	}

	public function startTransaction()
	{
		$this->query('START TRANSACTION');
	}
	
	public function rollBack()
	{
		$this->query('ROLLBACK');
	}

	public function commit()
	{
		$this->query('COMMIT');

	}

	protected function smartQuote($value)
	{
	   // Stripslashes
		 if(!is_object($value))
		{

			if (get_magic_quotes_gpc())
			{
				$value = stripslashes($value);
			}
			// Quote if not integer
			if (!is_numeric($value) || $value[0] == '0')
			{
				$newvalue = mysql_real_escape_string($value);
				$value = $newvalue;
			}
		}
		return $value;
	}

	public function querySafe($query)
	{
		$args  = func_get_args(); 
		if(count($args) > 1)
		{
		  $query = array_shift($args); // remove first argument and save it as the query
		  $query = str_replace("?", "%s", $query);
		  $args = array_map( array($this, 'smartQuote'), $args);
		  array_unshift($args, $query);
		  $query = call_user_func_array('sprintf',$args);
		}
		return $this->query($query);
	}
	
	public function querySafeTrace($query)
	{
		$args  = func_get_args(); 
		if(count($args) > 1)
		{
		  $query = array_shift($args); // remove first argument and save it as the query
		  $query = str_replace("?", "%s", $query);
		  $args = array_map( array($this, 'smartQuote'), $args);
		  array_unshift($args, $query);
		  $query = call_user_func_array('sprintf',$args);
		}
		trace($query);
		return $this->query($query);
	}
	
	
	public function affected_rows()
	{
		return @mysql_affected_rows($this->connData->connID);
	}

	public function fetch_array($res)
	{
		return @mysql_fetch_array($res);
	}

	public function qfetch_array($query)
	{
		$res = $this->query($query);
		return $this->fetch_array($res);
	}

	public function fetch_assoc($res)
	{
		return @mysql_fetch_assoc($res);
	}

	public function qfetch_assoc($query)
	{
		$res = $this->query($query);
		return $this->fetch_assoc($res);
	}

	public function fetch_num($res)
	{
		return @mysql_num_rows($res);
	}

	public function fetch_obj($res, $objName=null)
	{
		if(isset($objName)) return @mysql_fetch_object($res, $objName);
		else return @mysql_fetch_object($res);

	}

	public function qfetch_obj($query, $objName=null)
	{
		$res = $this->query($query);
		return $this->fetch_obj($res, $objName);
	}

	public function getAllRows($res, $returnType='object')
	{
		$rows = array();
		switch($returnType)
		{
			case 'array':
				$method = 'fetch_array';
				break;
			case 'object':
				$method = 'fetch_obj';
				break;
			case 'assoc':
				$method = 'fetch_assoc';
				break;
		}
		$returnType == 'obj';
		while($r = $this->$method($res))
		{
			$rows[] = $r;
		}
		return $rows;
	}
}
?>