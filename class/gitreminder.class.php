<?php

require_once '../3rd-party/tan-tan-kanarek/github-php-client/client/GitHubClient.php';
require_once 'log.class.php';

/**
 * Class gitReminder
 *
 * This class controlls the github.com-bot 'GitReminder'
 * that allows you to get reminded/notified about planned
 * issues
 *
 * @author      Andreas Doebeling <ad@1601.com>
 * @author      Lukas Beck <lb@1601.com>
 * @copyright   1601.communication gmbh
 * @license     CC-BY-SA | https://creativecommons.org/licenses/by-sa/3.0
 * @link        https://github.com/ADoebeling/GitReminder
 * @link        http://xing.doebeling.de
 * @link        http://www.1601.com
 * @version     0.1.150718_1lb
 */
class gitReminder
{
    
	/**
     * @const string FILE_TASKS_SERIALIZED Default-path for stored, serialized tasks
     */
    const FILE_TASKS_SERIALIZED = '../data/tasks.phpserialize';
    
    /**
     * @const string FILE_TASKS_JSON Default-path for stored and encode tasks
     */
    const FILE_TASKS_JSON = '../data/tasks.json';

    /**
     * @const string NAME_OF_GITREMINDER Default name of GitHub-User
     */
    const NAME_OF_GITREMINDER = 'name';
    
    
    /**
     * List of all found and pared tasks
     * @var array $tasks['/ghRepoUser/ghRepo/issues/ghIssueId'] = array('ghRepoUser' => X, 'ghRepo' => X, 'ghIssueId' => X, 'assignIssueToUser' => X, 'sendMailNotificationTo' => X, 'sourceText' => X, 'matureDate' => X)
     */
    private $tasks = array();
    
    /**
     * The Object of Class log
     * @var object $log
     */
    protected $log;

    /**
     * Initialize github- and logger-class
     */
    public function __construct()
    {
    	$this->log = new log();
    	$this->log->notice("GitReminder starts to work..");
    	return $this;
    }

    /**
     * Login at github.com-API
     *
     * @param $ghUser
     * @param $ghApiToken
     * @return $this
     */
    public function setGithubAccount ($ghUser, $ghApiToken)
    {
    	
    	$this->githubRepo = new GitHubClient(); 
        $this->githubRepo->setCredentials($ghUser, $ghApiToken);
        return $this;
    }

    
    /**
     * Load serialized tasks from last run 
     *
     * @param $file
     * @return $this
     */
    public function loadStoredTasksSerialized($file = self::FILE_TASKS_SERIALIZED)
    {
    	if (!file_exists($file))
    	{
    		throw new Exception("File '$file' not found!",404);
            $this->log->warning("File not found: $file");
    	}
    	
    	$this->tasks = array_merge($this->tasks, unserialize(file_get_contents($file)));
    
        return $this;
    }
    
    
    /**
     * Load stored task from Database
     * @param string $dbHost
     * @param string $dbUser
     * @param string $dbName
     * @param string $dbPwd
     */
    public function loadStoredTasksFromDatabase()
    {
    	throw new \Exception ('Protocol "$type" not implemented yet', 501);
    }
    
    
    
    /**
     * Load encoded tasks from last run
     *
     * @param $jfile
     * @return $this
     */
    public function loadStoredTasksJson($jFile = self::FILE_TASKS_JSON)
    {
    	if (!file_exists($file))
    	{
    		throw new Exception("File '$jfile' not found!",404);
            $this->log->warning("File not found: $jFile");
    	}
    	 
    	array_push($this->tasks,json_decode(file_get_contents($jFile)));
    
    	return $this;
    }

    
    
    
    
    /**
     * Load unread GitHub-Notifications and store them to $this->tasks[]
     * @param string $nameGitReminder
     * @return $this
     */
    public function loadGhNotifications($nameGitReminder = self::NAME_OF_GITREMINDER)
    {
    	// @TODO: Find methode to read current notifications
    	// @TODO: Don't load unnecessary api-urls to make the json smaller
    	// This is quite ugly, but it's the only way we found to get access to
    	// a list of all notifications including the html_url and fe_issue_id
    	// all methodes within githubRepo->activity->notifications->listYourNotifications()[x] 
    	// are protected so we could not load them from our position.
    	
    	/*We are looking for new notifications and return them as an Array in var $notification*/
    	$notifications = json_decode($this->githubRepo->request("/notifications", 'GET', array('participating' => true), 200, 'string', true), true);
    	
        if(count($notifications)>=30)$this->log->warning("$name$nameGitReminder has been called more than 30 times\n\n\n",$notifications);
        
    	foreach ($notifications as $element)
    	{
    		$repoOwner = $element["repository"]["owner"]["login"];
    		$repo =  $element["repository"]["name"];
    		$issueTitel = $element["subject"]["title"];
    		$issue_path_ok = str_replace("https://api.github.com","",$element["subject"]["url"]);
    		$issue_id = intval(str_replace("/repos/$repoOwner/$repo/issues/","",$issue_path_ok));
			
    		/*Create the Index of one task*/
    		$taskIndex = "/$repoOwner/$repo/issue/$issue_id";
    		
    		/*We create the Array tasks[] with [index] and subarray[values]*/
    		$this->tasks[$taskIndex] = array(
    				'ghRepoUser' => $repoOwner,
    				'ghRepo'	 => $repo,
    				'issueTitel' => $issueTitel,
    				'ghIssueId'	 => $issue_id,
    				'assignIssueToUser' => "",
    				'sendMailNotificationTo' => "",
    				'sourceText' => "",
    				'matureDate' => "",
    				'commentAuthor' => "",
    		);
    		
    		/*Load the first comment from Issue*/
    		$firstComment = $this->githubRepo->request("/repos/".$repoOwner."/".$repo."/issues/".$issue_id, 'GET', array(), 200, 'GitHubPullComment', true);
    		
    		/*Here we will get the body (the message) from the first comment*/
    		$firstCommentBody = $firstComment->getBody();
    		
    		/*Here we will get the author of the first comment*/
    		$firstCommentAuthor = $firstComment->getuser()->getlogin();
    		
    		
    		/*Load all other commits in an Array from issue*/
    		$comments = $this->githubRepo->request("/repos/".$repoOwner."/".$repo."/issues/".$issue_id."/comments", 'GET', array(), 200, 'GitHubPullComment', true);
    		
    		
    		/*Look at the "body"string and searching for $nameGitReminder (name of bot) in the first comment*/
    		$pos = strpos($firstCommentBody, $nameGitReminder);
			
			/*If name was found and the ['sourceText'] and is not the "body"string, we write the whole "body"string in our global Array->(tasks). The ['sourceText'] before will be overwritten*/
			if ($pos !== false && $firstCommentBody != $this->tasks[$taskIndex]['sourceText'])
    		{
    			$this->tasks[$taskIndex]['sourceText'] = trim($firstCommentBody);
    			$this->tasks[$taskIndex]['commentAuthor'] = trim($firstCommentAuthor);
    		}
    		
    		/*Here we are looking for the $nameGitReminder (name of bot) in the other "body"strings*/
    		foreach ($comments as $commentObject)
    		{
    			$nextComments = $commentObject->getBody();
    			$nextCommentAuthor = $commentObject->getuser()->getlogin();
    			$pos = strpos($nextComments, $nameGitReminder);
    			
    			/*If name was founded and the ['sourceText'] and is not the "body"string, we write the whole "body"string in our global Array->(tasks). The ['sourceText'] before will be overwritten*/
    			if ($pos !== false && $nextComments != $this->tasks[$taskIndex]['sourceText'])
    			{
    				$this->tasks[$taskIndex]['sourceText'] = trim($nextComments);
    				$this->tasks[$taskIndex]['commentAuthor'] = trim($nextCommentAuthor);
    			}
    		}
    		
    	}
    	
    	/*Mark notifications as read.*/
    	$this->githubRepo->request("/notifications", 'PUT', array(1), 205, '');
    	return $this;
	}

	
	
	
	
	
    /**
     * Parses all $this->tasks[$link]['sourceText'] and tries to find out
     * what to do and stores this information back in $this->$tasks
     * @param string $nameGitReminder
     * @return $this
     */
    public function parseSourceText($nameGitReminder = self::NAME_OF_GITREMINDER)
    {
    	
    	foreach ($this->tasks as &$comment)
    	{
    		if (isset($comment) && !isset($comment["assignIssueToUser"]) || $comment["assignIssueToUser"] == "")
    		{
				
					/*Looking for the following syntax "@nameOfGitReminder [(+|-)](Int day or hour)[timeFormat] [UserToAssign]" like "@Gitreminder +4h @userToAssign" and divide this into Array->$value[]*/
	    			preg_match("/(?<gitreminder>@$nameGitReminder)\s(\+|-)?(?<matureDate>\d{1,9})(?<timeFormat>.)(?=\s)?(?<assignIssueToUser>@[a-zA-Z0-9\-]*)?/",$comment['sourceText'],$value);
    			
    			    			
	    			/*If the Value of $value["assignIssueToUser"] is not empty and is set it write the user in $this->tasks[~]["assignIssueToUser"] else the author of the comment is the userToAssign*/
	    			if (isset($value["assignIssueToUser"]) && $value["assignIssueToUser"] != "")
	    			{
	    				$comment["assignIssueToUser"] = str_replace("@","" , $value["assignIssueToUser"]);
	    			}
	    			else if (isset($comment['commentAuthor']))
	    			{
	    				$comment["assignIssueToUser"] = str_replace("@","",$comment['commentAuthor']);
	    			}
	    				    			
	    			/*If the timeformat is "h" or "s" or "H" or "S" for "hour" or "Stunden" it will interpreted as hour an calc the unixtimestamp + Hours form $value["time"] and write this into $this->tasks[~]["matureDate"]. 
	    			Else it will calc the unixtimestamp + $value["matureDate"] as days and write this into $this->tasks[~]["matureDate"]*/
	    			if (isset($value["timeFormat"]) && ($value["timeFormat"]=="h" || $value["timeFormat"]=="s" || $value["timeFormat"]=="H" ||$value["timeFormat"]=="S"))
	    			{
	    				$time = intval($value["matureDate"]);
	    				$time = $time*60*60;
	    				$comment["matureDate"] = $time+time();
	    			}
	    			elseif(isset($value["timeFormat"]) && $value["timeFormat"]=="m")
	    			{
	    				$time = intval($value["matureDate"]);
	    				$time = $time*60;
	    				$comment["matureDate"] = $time+time();
	    			}
	    			elseif(isset($value["timeFormat"]))
	    			{
	    				$time = intval($value["matureDate"]);
	    				$time = $time*60*60*24;
	    				$comment["matureDate"] = $time+time();
	    			}
    		}	    		
    	}
    	return $this;
    }

    
    
    
    
    
    /**
     * Processes all $this->tasks and perform all planned todos
     * @param string $link
     * @return $this
     */
    public function process($link = 'ALL')
    {
        $i = 0;
    	foreach ($this->tasks as $taskLink => &$task)
    	{
     		if (!isset($task["matureDate"])) $task["matureDate"] = time();
     		
    		if ($task["matureDate"] <= time() && isset($task["ghRepoUser"]))
     		{
     		     $i++;
     			try {
     				$this->githubRepo->issues->editAnIssue($task["ghRepoUser"], $task["ghRepo"], $task["issueTitel"], $task["ghIssueId"],null,$task["assignIssueToUser"]);
     			}
     			catch (Exception $e)
     			{
     				// TODO: Implement Expeption handling
     				die("something went quite wrong in Line 288: ".$e->getMessage());		
     			}
     			unset($this->tasks[$taskLink]);
     		}
        }
        if($i>=21)$this->log->warning("!!More than 20 Issues has been edit!!",$this->tasks);
        return $this;
    }
    
    
    
    
    
    
    /**
     * @todo implement
     * Send an Errormessage to admin@1610.com and write the Error into the Logfile
     * @param string or array $error
     * @param int $errorCode
     * @return $this;
     */
	public function createComment($error,$errorCode)
	{
	   
		//$this->githubRepo->issues->issuesAssignees->createComment();
		return $this;
	}
	
	
	
	
	
    /**
     * @todo implement
     * @param string $methode
     * @param string|array $fileOrDb
     * @return gitReminder
     */
    public function storeTasks($location,$fileOrDb)
    {
    	return $this;
    }
    
   
    
    
    /**
     * Stores the current $tasks-array as serialized
     * array at the given location
     * @todo implement file put contents exeption.
     * 
     * @param $file
     * @return $this
     */
    public function storeTasksSerialized($file = self::FILE_TASKS_SERIALIZED)
    {
    	if (!file_exists($file))
    	{
    	    $this->log->warning("File not found: $file");
    		throw new Exception("File '$file' not found!",404);
    	}
    	
    	file_put_contents($file, serialize($this->tasks));
    	
        return $this;
    }
    
    
    
    
    
    
    /**
     * Stores the current $tasks-array in a database
     * @param $dbHost
     * @param $dbUser
     * @param $dbName
     * @param $dbPwd
     * 
     */
    public function storeTasksInDatabase($dbHost,$dbUser,$dbName,$dbPwd)
    {
    	$db_link = mysqli_connect (
    			$dbHost,
    			$dbUser,
    			$dbPwd,
    			$dbName
    	) or die ("Keine Verbindung möglich: ".mysql_error());
    	mysql_select_db($dbName,$db_link) or die ("Keine Verbindung möglich: ".mysql_error());
    	mysqli_set_charset($db_link, 'utf8');
    	
    	$sql = "
    	CREATE TABLE 'tasks'(
    			`id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    			`taskName` VARCHAR( 150 ) NOT NULL ,
    			`ghRepoUser` VARCHAR( 150 ) NOT NULL ,
    			`ghRepo` VARCHAR( 150 ) NOT NULL ,
    			`ghIssueId` VARCHAR( 150 ) NOT NULL ,
    			`assignIssueToUser` VARCHAR( 150 ) NOT NULL ,
    			`sendMailNotificationTo` INT(1) NOT NULL ,
    			`sourceText` VARCHAR( 250 ) NOT NULL ,
    			`matureDate` INT(8) NOT NULL ,
    			)
    		ENGINE = MYISAM ;";
    	$db_erg = mysqli_query($db_link, $sql);
    			
    	foreach ($this->tasks as $taskName=>$task)
    	{
    		$ghRepoUser = $task['ghRepoUser'];
    		$ghRepo = $task['ghRepo'];
    		$ghIssueId = $task['ghIssueId'];
    		$assignIssueToUser = $task['assignIssueToUser'];
    		$sendMailNotificationTo = $task['sendMailNotificationTo'];
    		$sourceText = $task['sourceText'];
    		$matureDate = $task['matureDate'];
    		// mysql_query("Insert into 'tasks' set name='$name', wert='$wert', letzterwert=22")
			mysql_query("INSERT INTO tasks ('taskName','ghRepoUser','ghRepo','ghIssueId','assignIssueToUser','sendMailNotificationTo','sourceText','matureDate') VALUES ($taskName,$ghRepoUser,$ghRepo,$ghIssueId,$assignIssueToUser,$sendMailNotificationTo,$sourceText,$matureDate)");
    	}
    	return $this;
    }
    
    
    
    
    
    /**
     * Stores the current $tasks-array in a json-file
	 * @param $jFile
	 * @todo implement file put contents exeption.
     */
    public function storeTasksInJson($jFile = FILE_TASKS_JSON)
    {
    	if (!file_exists($jFile))
    	{
    	    $this->log->warning("File not found: $jFile");
    		throw new Exception("File '$jFile' not found!",404);
    	}
    	file_put_contents($jFile, json_encode($this->tasks,JSON_UNESCAPED_UNICODE));
    	
    	return $this;
    }

    
    
    
    /**
     * (Reopen and) assign a issue to the given user
     *
     * @todo Implement;
     * @param $link
     * @param $reopenGhIssue = true
     * @return $this
     */
    public function assignGhIssue($link, $reopenGhIssue = true)
    {
        return $this;
    }

    

    
    /**
     * Send a mail-notification
     *
     * @todo Implement
     * @param $link
     * @return $this
     */
    public function sendMailNotification($link)
    {
        return $this;
    }

	
    public function __destruct()
    {
    	echo "<pre>";
    	print_r($this->tasks);
    	echo "</pre>";
    	
    	$this->log->notice(".. now it finished to work!");
    	
    	return $this;
    }
    
    
}