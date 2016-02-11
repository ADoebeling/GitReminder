<?php 

class log
{
	const LOG_PATH = __DIR__.'/../logs/';
	
	protected $entrys = array();
	
	protected function add($level, $desc, $array = NULL)
	{
		$this->entrys[] = array('date' => date("d.m.y H:i:s",time()), 'level' => $level, 'desc' => $desc, 'array' => $array);
	}
	
	public function debug($desc, $array = NULL)
	{
		return $this->add(__FUNCTION__, $desc, $array);
	}
	
	public function notice($desc, $array = NULL)
	{
		return $this->add(__FUNCTION__, $desc, $array);
	}
	
	public function info($desc, $array = NULL)
	{
		return $this->add(__FUNCTION__, $desc, $array);
	}
	
	public function warning($desc, $array = NULL)
	{
		return $this->add(__FUNCTION__, $desc, $array);
	}
	
	public function error($desc, $array = NULL)
	{
		return $this->add(__FUNCTION__, $desc, $array);
	}
	
	
	public function __destruct()
	{
		$text = "";
		foreach ($this->entrys as &$row)
		{
			$text .= $row["date"]."|| ".$row["level"]."|| ".$row["desc"]."|| ".print_r($row["array"],1)."\n";
		}
						
		$timestamp = time();
		$datum = date("Y-m-d",$timestamp);
		$datumMonat = date("Y-m",$timestamp);
		$file = self::LOG_PATH.$datumMonat."_logfolder/"."log-".$datum.".txt";
		
		if (!file_exists($file)) $fp = fopen($file,"x+");
				
		$fp = fopen($file,"a+");
		fwrite($fp, $text);
		fclose($fp);		
	}
}