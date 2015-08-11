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
    public function loadStoredTasks($file = FILE_TASKS_SERIALIZED)
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
    public function loadStoredTasksFromJson($jFile = FILE_TASKS_JSON)
    {
    	if (!file_exists($file))
    	{
    		throw new Exception("File '$file' not found!",404);
    	}
    	 
    	array_push($this->tasks,son_decode (file_get_contents($jFile)));
    
    	return $this;
    }

    /**
     * Load unread GitHub-Notifications and store them to
     * $this->tasks[]
     *
     * @todo Implement
     * @return $this
     */
    public function loadGhNotifications()
    {
    	//var_dump($this->githubRepo->activity);
    	$this->tasks = $this->githubRepo->activity->notifications->listYourNotifications();
    	echo "<br><br>";
    	var_dump($this->tasks[94533633]);
    	echo "<br><br>";
    	var_dump($this->tasks[94532367]);
        return $this->tasks;
        
    }

    /**
     * Parses all $this->tasks[$link]['sourceText'] and tries to find out
     * what to do and stores this information back in $this->$tasks
     *
     * @todo Implement
     * @return $this
     */
    public function parseSourceText()
    {
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
     * Stores the current $tasks-array as serialized
     * array at the given location
     * @todo Implement "Stores the current $tasks-array to Database"
     * @todo Implement "Stores the current $tasks-array to Json"
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
    			)ENGINE = MYISAM ;";
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
			mysql_query("INSERT INTO tasks ('taskName','ghRepoUser','ghRepo','ghIssueId','assignIssueToUser','sendMailNotificationTo','sourceText','matureDate') VALUES ($taskName,$ghRepoUser,$ghRepo,$ghIssueId,$assignIssueToUser,$sendMailNotificationTo,$sourceText,$matureDate)");
    	}
    	return $this;
    }
    
    
    
    /**
     * Stores the current $tasks-array in a json-file
     * @param $jFile
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