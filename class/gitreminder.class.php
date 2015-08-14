<?php

require_once '../3rd-party/tan-tan-kanarek/github-php-client/client/GitHubClient.php';


/**
 * Class gitReminder
 *
 * This class controlls the github.com-bot 'GitReminder'
 * that allows you to get reminded/notified about planned
 * issues
 *
 * @author      Andreas Doebeling <ad@1601.com>
 * @copyright   1601.communication gmbh
 * @license     CC-BY-SA | https://creativecommons.org/licenses/by-sa/3.0
 * @link        https://github.com/ADoebeling/GitReminder
 * @link        http://xing.doebeling.de
 * @link        http://www.1601.com
 * @version     0.1.150704_1lb
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
     * List of all found and pared tasks
     * @var array $tasks['/ghRepoUser/ghRepo/issues/ghIssueId'] = array('ghRepoUser' => X, 'ghRepo' => X, 'ghIssueId' => X, 'assignIssueToUser' => X, 'sendMailNotificationTo' => X, 'sourceText' => X, 'matureDate' => X)
     */
    private $tasks = array();

    /**
     * Initialize github- and logger-class
     */
    public function __construct()
    {
		
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
    public function loadStoredTasksSerialized($file = FILE_TASKS_SERIALIZED)
    {
    	if (!file_exists($file))
    	{
    		throw new Exception("File '$file' not found!",404);
    	}
    	
    	array_push($this->tasks, unserialize(file_get_contents($file)));
    	    	
        return $this;
    }
    
    
    /**
     * Load stored task from Database
     * @param $dbHost
     * @param $dbUser
     * @param $dbName
     * @param $dbPwd
     */
    public function loadStoredTasksFromDatabase()
    {
    	
    }
    
    /**
     * Load encoded tasks from last run
     *
     * @param $jfile
     * @return $this
     */
    public function loadStoredTasksJson($jFile = FILE_TASKS_JSON)
    {
    	if (!file_exists($file))
    	{
    		throw new Exception("File '$jfile' not found!",404);
    	}
    	 
    	array_push($this->tasks,json_decode(file_get_contents($jFile)));
    
    	return $this;
    }

    /**
     * Load unread GitHub-Notifications and store them to
     * $this->tasks[]
     * $this->$tasks['/ghRepoUser/ghRepo/issues/ghIssueId'] = array('ghRepoUser' => X, 'ghRepo' => X, 'ghIssueId' => X, 'assignIssueToUser' => X, 'sendMailNotificationTo' => X, 'sourceText' => X, 'matureDate' => X)
     * @todo Implement
     * @return $this
     */
    public function loadGhNotifications($nameGitReminder)
    {
			
    	// @TODO: Find methode to read current notifications
    	// @TODO: Don't load unnecessary api-urls to make the json smaller
    	// ad@1601.com | 150811
    	// This is quite ugly, but it's the only way we found to get access to
    	// a list of all notifications including the html_url and fe_issue_id
    	// all methodes within githubRepo->activity->notifications->listYourNotifications()[x] 
    	// are protected so we could not load them from our position.
    	
    	/*We are looking for new notifications and return the Array*/
    	$notifications = json_decode($this->githubRepo->request("/notifications", 'GET', array(), 200, 'string', true), true);
    	
    	
    	foreach ($notifications as $elements)
    	{
    		//$comments = $this->githubRepo->issues->comments->listCommentsOnAnIssue($task["repository"]["owner"]["login"],$task["repository"]["name"],$task["id"]);echo"<br><br><br><br>";
    		$repoOwner = $elements["repository"]["owner"]["login"];
    		$repo =  $elements["repository"]["name"];
    		$issue_name = $elements["subject"]["title"];
    		$issue_path_ok = str_replace("https://api.github.com","",$elements["subject"]["url"]);
    		$issue_id = intval(str_replace("/repos/$repoOwner/$repo/issues/","",$issue_path_ok));
			
    		$taskIndex = "/$repoOwner/$repo/$issue_name/$issue_id";
    		
    		/*We create the Array tasks[] with [index] and subarray[values]*/
    		$this->tasks[$taskIndex] = array(
    				'ghRepoUser' => $repoOwner,
    				'ghRepo'	 => $repo,
    				'ghIssueId'	 => $issue_id,
    				'assignIssueToUser' => "",
    				'sendMailNotificationTo' => "",
    				'sourceText' => "",
    				'matureDate' => "",
    		);
    		
    		/*Load the first comment from Issue*/
    		$firstComment = $this->githubRepo->request("/repos/".$repoOwner."/".$repo."/issues/".$issue_id, 'GET', array(), 200, 'GitHubPullComment', true);
    		
    		/*Load all other commits in an Array*/
    		$comments = $this->githubRepo->request("/repos/".$repoOwner."/".$repo."/issues/".$issue_id."/comments", 'GET', array(), 200, 'GitHubPullComment', true);
    		
    		$firstCommentBody = $firstComment->getBody();
    		
    		/*Look at the "body"string and search $nameGitReminder (name of bot) in the first comment*/
    		$pos = strpos($firstCommentBody, $nameGitReminder);
			
			/*If name was found and the ['sourceText'] and is not the "body"string, we write the whole "body"string in our global Array->(tasks). The ['sourceText'] before will be overwritten*/
			if ($pos !== false && $firstCommentBody != $this->tasks[$taskIndex]['sourceText'])
    		{
    			$this->tasks[$taskIndex]['sourceText'] = trim($firstCommentBody);
    		}
    		
    		/*Here we are looking for the $nameGitReminder (name of bot) in the other "body"strings*/
    		foreach ($comments as $commentObject)
    		{
    			$nextComments = $commentObject->getBody();
    			$pos = strpos($nextComments, $nameGitReminder);
    			
    			/*If name was founded and the ['sourceText'] and is not the "body"string, we write the whole "body"string in our global Array->(tasks). The ['sourceText'] before will be overwritten*/
    			if ($pos !== false && $nextComments != $this->tasks[$taskIndex]['sourceText'])
    			{
    				$this->tasks[$taskIndex]['sourceText'] = trim($nextComments);
    			}
    		}
    		
    	}
    	
    	return $this;
	}

    /**
     * Parses all $this->tasks[$link]['sourceText'] and tries to find out
     * what to do and stores this information back in $this->$tasks
     *
     * @todo Implement
     * @return $this
     */
    public function parseSourceText($nameGitReminder)
    {
    	foreach ($this->tasks as $comment)
    	{
    		if ($comment['matureDate'] == "")
    		{
    			$commentArray = array();
    		
    			$commentArray = explode("\n",$comment['sourceText']);
    		
    			foreach ($commentArray as &$oneLine)
    			{
    				$oneLine = trim($oneLine);
    				$pos = strpos($oneLine, $nameGitReminder);
    			
    				if ($pos !== false)
    				{
    					// ([\+])?[1-9]{1,9}[\h|d]			 is looking for +2h
    					// (\+)?\d{1,9}						 is searching for "'+'int" like +242
    					// (\+)?\d{1,9}[h|d] 				 is looking for "'+'int'h|d'" like +242h
    					$value = preg_split("/[\s,;]+/", $oneLine);
    					
    					preg_match("/(?<=\+)?\d{1,9}(?=d|h|t|s)?/", $value[1], $value["intTime"], PREG_OFFSET_CAPTURE);
    					
    					var_dump($value["intTime"]);
    				}
    			}
    		}
    		
    		
    	}
    
    	return $this;
    }

    /**
     * Processes all $this->tasks and perform all planned todos
     *
     * @todo Implement
     * @param string $link
     * @return $this
     */
    public function process($link = 'ALL')
    {
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
    public function storeTasksSerialized($file = FILE_TASKS_SERIALIZED)
    {
    	if (!file_exists($file))
    	{
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


}