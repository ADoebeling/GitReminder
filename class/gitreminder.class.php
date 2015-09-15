<?php

require_once '../3rd-party/github-php-client/client/GitHubClient.php';
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
 * @version     0.1.150908_1lb
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
    const NAME_OF_GITREMINDER = 'gitreminder';
    
    
    /**
     * List of all found and pared tasks
     * @var array $tasks['/ghRepoUser/ghRepo/issues/ghIssueId'] = array('ghRepoUser' => X, 'ghRepo' => X, 'ghIssueId' => X, 'assignIssueToUser' => X, 'sendMailNotificationTo' => X, 'sourceText' => X, 'matureDate' => X, 'commentAuthor' => X, 'commentCreateDate' => X,)
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
    	$this->log->notice("START");
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
  	
    	
    	//We are looking for new notifications and return them as an Array in var $notification
    	$notifications = json_decode($this->githubRepo->request("/notifications", 'GET', array('participating' => true), 200, 'string', true), true);
    	
    	
        if(count($notifications)>=30)$this->log->warning("$name$nameGitReminder has been called more than 30 times\n\n",$notifications);
        
    	foreach ($notifications as $element)
    	{
    		$repoOwner = $element["repository"]["owner"]["login"];
    		$repo =  $element["repository"]["name"];
    		$issueTitel = $element["subject"]["title"];
    		$issuePath = str_replace("https://api.github.com","",$element["subject"]["url"]);
    		$issueId = intval(str_replace("/repos/$repoOwner/$repo/issues/","",$issuePath));

    		//Check how many comments the Issue have.
    		$issueObj = $this->getIssue($repoOwner, $repo, $issueId);
    		$intComments = $issueObj->getComments();
    		
    		//Calc the loop depending on comments
    		$loop = intval($intComments / 30)+1;

    		//Write new Notification into the logfile
    		$this->log->info("New Notification from Repo: $repo and Issue: $issueTitel");
			
    		//Create the Index of one task
    		$taskIndex = "/$repoOwner/$repo/issue/$issueId";
    		
    		//We create the Array tasks[] with [index] and subarray[values]
    		$this->tasks[$taskIndex] = array(
    				'ghRepoUser' => $repoOwner,
    				'ghRepo'	 => $repo,
    				'issueLink'  => $issuePath,
    				'issueTitel' => $issueTitel,
    				'ghIssueId'	 => $issueId,
    		);
    		
    		$comments = array();
    		
    		for ($i=1;$i<=$loop;$i++)
    		{
	    		//Load all commits in the Array $comments[] from issue
	    		$newComments = $this->githubRepo->request("/repos/".$repoOwner."/".$repo."/issues/".$issueId."/comments?page=$i", 'GET', array(), 200, 'GitHubPullComment', true);
	    		$comments += $newComments;
    		}
    		
    		//Here we sort the array from behind
    		krsort($comments);
    		
	    	//Here we are looking for the $nameGitReminder (name of bot) in the other "body"strings
	    	foreach ($comments as $commentObject)
	    	{
	    		$nextComments = $commentObject->getBody();
	    		$nextCommentAuthor = $commentObject->getuser()->getlogin();
	    		$nextCommentDate = $commentObject->getCreatedAt();
	    		$pos = strpos($nextComments, $nameGitReminder);
	    		 
	    		//If name was founded and the ['sourceText'] and is not the "body"string, we write the whole "body"string in our global Array->(tasks). The ['sourceText'] before will be overwritten
	    		if ($pos !== false)
	    		{
					$this->tasks[$taskIndex]['sourceText'] = trim($nextComments);
	    			$this->tasks[$taskIndex]['commentAuthor'] = trim($nextCommentAuthor);
	    			$this->tasks[$taskIndex]['commentCreateDate'] = trim($nextCommentDate);
	    			break;
	    		}
	    		
	    	}

			
    		
    		if (!isset($this->tasks[$taskIndex]['sourceText']))
    		{
    			//Load the first comment from Issue
    			$firstComment = $this->githubRepo->request("/repos/".$repoOwner."/".$repo."/issues/".$issueId, 'GET', array(), 200, 'GitHubPullComment', true);

    			//Here we will get the body (the message) from the first comment
    			$firstCommentBody = $firstComment->getBody();
    			
    			//Here we will get the author of the first comment
    			$firstCommentAuthor = $firstComment->getuser()->getlogin();
    			
    			//Here we will get the create date of the first comment
    			$firstCommentDate = $firstComment->getCreatedAt();
    			
    			//Look at the "body"string and searching for $nameGitReminder (name of bot) in the first comment
    			$pos = strpos($firstCommentBody, $nameGitReminder);
    				
    			//If name was found and the ['sourceText'] and is not the "body"string, we write the whole "body"string in our global Array->(tasks). The ['sourceText'] before will be overwritten
    			if ($pos !== false)
    			{
    				$this->tasks[$taskIndex]['sourceText'] = trim($firstCommentBody);
    				$this->tasks[$taskIndex]['commentAuthor'] = trim($firstCommentAuthor);
    				$this->tasks[$taskIndex]['commentCreateDate'] = trim($firstCommentDate);
    			}	
    		}
    		
    		
    	}
    	
    	//Mark notifications as read.
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
					//Looking for the following syntax "@nameOfGitReminder [(+|-)](Int day or hour)[timeFormat] [UserToAssign]" like "@Gitreminder +4h @userToAssign" and divide this into Array->$value[]
	    			preg_match("/(?<gitreminder>@$nameGitReminder)\s(\+|-)?(?<matureDate>\d{1,9}|stop|ignore|end|now)(?<timeFormat>.)?(\s)?(?<assignIssueToUser>@[a-zA-Z0-9\-]*)?( )?((?<sendmail>mail (?<sendmailto>.*@.*))|(?<writeComment>comment (?<commentm>.*))|(?<sms>sms (?<number>0\d*)))?/",$comment['sourceText'],$value);
					
	    			
	    			//If the Value of $value["assignIssueToUser"] is not empty and is set it write the user in $this->tasks[~]["assignIssueToUser"] else the author of the comment is the userToAssign
	    			if (isset($value["assignIssueToUser"]) && $value["assignIssueToUser"] != "")
	    			{
	    				$comment["assignIssueToUser"] = str_replace("@","" , $value["assignIssueToUser"]);
	    			}
	    			else
	    			{
	    				$comment["assignIssueToUser"] = str_replace("@","",$comment['commentAuthor']);
	    			}
	    			
	    			//Convert the createtimeformat into timestamp
	    			$comment['commentCreateDate'] = strtotime($comment['commentCreateDate']);
	    			
	    			if (isset($value['timeFormat']))$timeFormat = strtolower($value['timeFormat']);
	    			
	    			//If the sytax say stop or ... GitReminder will assign in this moment.
	    			if ($value['matureDate'] == 'stop' ||$value['matureDate'] == 'ignore' ||$value['matureDate'] == 'end' || $value['matureDate'] == 'now')
	    			{
	    				$value['matureDate'] = 0;
	    				$timeFormat = 'm';
	    			}
	    			
	    			
	    			
	    			//Check the timeformat and create the maturedate.
	    			if ($timeFormat == 'd' || $timeFormat == 't' || empty($timeFormat))
	    			{
	    				$comment["matureDate"] = $value['matureDate']*24*60*60+$comment['commentCreateDate'];
	    				$comment['matureDateInDateform'] = date("d.m.Y H:i",$comment["matureDate"]);
	    				
	    				if ($value['matureDate'] >= 366)
	    				{
	    					$this->createComment($comment['ghRepoUser'], $comment['ghRepo'], $comment['ghIssueId'], "It's not possible to assign \"".$comment['assignIssueToUser']."\" in \"".$value['matureDate']."\" days! One Year is max! 365Days");
	    					$this->log->warning("The maturedate from Issue ".$comment['ghIssueId']." and Repo ".$comment['ghRepo']." is in more than 365 days!! Pls. check the Comment!!");
	    					$comment["matureDate"] = time();
	    					$comment["assignIssueToUser"] = str_replace("@","",$comment['commentAuthor']);
	    				}
	    			}
	    			elseif ($timeFormat == 'h' || $timeFormat == 's')
	    			{
	    				$comment["matureDate"] = $value['matureDate']*60*60+$comment['commentCreateDate'];
	    				$comment['matureDateInDateform'] = date("d.m.Y H:i",$comment["matureDate"]);
	    				if ($value['matureDate'] >= 8761)
	    				{
	    					$this->log->info("The maturedate from Issue ".$comment['ghIssueId']." and Repo ".$comment['ghRepo']." in hours is more than 8760.");
	    					$this->createComment($comment['ghRepoUser'], $comment['ghRepo'], $comment['ghIssueId'], "It's not possible to assign \"".$comment['assignIssueToUser']."\" in \"".$value['matureDate']."\" hours! One Year is max!");
	    					$comment["matureDate"] = time();
	    					$comment["assignIssueToUser"] = str_replace("@","",$comment['commentAuthor']);
	    				}
	    			}
	    			elseif ($timeFormat == 'm')
	    			{
	    				$comment["matureDate"] = $value['matureDate']*60+$comment['commentCreateDate'];
	    				$comment['matureDateInDateform'] = date("d.m.Y H:i",$comment["matureDate"]);
	    				if ($value['matureDate'] >= 525600)
	    				{
	    					$this->log->info("The maturedate from Issue ".$comment['ghIssueId']." and Repo ".$comment['ghRepo']." in minutes is more than 525600.");
	    					$this->createComment($comment['ghRepoUser'], $comment['ghRepo'], $comment['ghIssueId'], "It's not possible to assign \"".$comment['assignIssueToUser']."\" in \"".$value['matureDate']."\" minutes! One Year is max!");
	    					$comment["matureDate"] = time();
	    					$comment["assignIssueToUser"] = str_replace("@","",$comment['commentAuthor']);
	    				}
	    			}
	    			else 
	    			{
	    				$this->log->warning("The timeformat from Repo: ".$comment['ghRepo']." and IssueID: ".$comment['ghIssueId']." is false. The maturedate is in ".$value['matureDate']." days!");
	    				$comment["matureDate"] = $value['matureDate']*24*60*60+$comment['commentCreateDate'];
	    				$comment['matureDateInDateform'] = date("d.m.Y H:i",$comment["matureDate"]);
	    			}
	    			
	    			
	    			if (isset($value['sendmail']) && $value['sendmail'] != '' && $value['sendmailto'] != '')
	    			{
	    				$comment['sendMailNotificationTo'] = $value['sendmailto'];
	    			}
	    			elseif (isset($value['writeComment']) && $value['writeComment'] != '' && $value['commentm'] != '')
	    			{
	    				$comment['commentMessage'] = $value['commentm'];
	    			}
	    			elseif (isset($value['sms']) && $value['sms'] != '' && $value['number'] != '')
	    			{
	    				$comment['sendSms'] = $value['number'];
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
     			try
     			{
     				$this->githubRepo->issues->editAnIssue($task["ghRepoUser"], $task["ghRepo"], $task["issueTitel"], $task["ghIssueId"],null,$task["assignIssueToUser"]);
     				
     				if (isset($task['sendMailNotificationTo']))
     				{
     					$this->sendMailNotification($task['sendMailNotificationTo'],"newissue");
     				}
     				elseif (isset($task['commentMessage']))
     				{
     					$this->createComment($task['issueLink'],$task['commentMessage']);
     				}
     				elseif (isset($task['sendSms']))
     				{
     					//@todo implement
     				}
     			}
     			catch (Exception $e)
     			{
     				// TODO: Implement Expeption handling
     				die("something went quite wrong in Line 375: ".$e->getMessage());		
     			}
     			$this->log->info("Issue \"".$task['ghIssueId']."\" with the title \"".$task['issueTitel']."\" has been assigned to user: \"".$task['assignIssueToUser']."\"");
     			unset($this->tasks[$taskLink]);
     		}
        }
        if($i>=21)
        {
        	$this->log->warning("More than 20 Issues has been edit!",$this->tasks);
        }
        return $this;
    }
    
    
    
    
    
    
    /**
     * Write a comment 
     * @param string or array $error
     * @param int $errorCode
     * @return $issue;
     */
	public function createComment($ghIssueLink,$body)
	{
// 		if (is_int($issueId) && is_string($repo) && is_string($repoOwner))
		if(is_string($ghIssueLink))
		{
			switch($body)
			{
				case 'do':
					$body = 'Pls. do it now';
					break;
				case 'wait':
					$body = 'You\'ve to wait for new instruction';
					break;
			}			
			$data = array();
			$data['body'] = $body;
	   		//$this->githubRepo->request("/repos/$repoOwner/$repo/issues/$issueId/comments", 'POST', json_encode($data), 201, 'GitHubIssueComment');
			$this->githubRepo->request("$ghIssueLink/comments", 'POST', json_encode($data), 201, 'GitHubIssueComment');
		}
		else
		{
			echo "DA WAR EIN FEHLER!!!";
		}
		return $this;
	}
	
	/**
	 * Load an Issue with all important informations.
	 * @param string $repoOwner
	 * @param string $repo
	 * @param integer $issueId
	 */
	public function getIssue($repoOwner,$repo,$issueId)
	{
		if (is_int($issueId) && is_string($repo) && is_string($repoOwner))
		{
			$issue = $this->githubRepo->request("/repos/$repoOwner/$repo/issues/$issueId",'GET', array(), 200, 'GitHubIssue');
		}
		else
		{
			$this->log->warning("Can not load Issue from Repo: $repo and IssueID: $issueId");
			$this->createComment($repoOwner, $repo, $issueId, "There was a mistake pls. try it again.");
			return false;
			
		}
		
		return $issue;
	}
	
	
    /**
     * @todo implement
     * @param string $methode
     * @param string|array $fileOrDb
     * @return gitReminder
     */
    public function storeTasks($methode,$fileOrDb)
    {
    	if ($methode == "database" && is_array($fileOrDb))
    	{
    		$this->storeTasksInDatabase($dbHost, $dbUser, $dbName, $dbPwd);
    	}
    	elseif ($methode == "serialize" && is_string($fileOrDb))
    	{
    		$this->loadStoredTasksSerialized($fileOrDb);
    	}
    	elseif ($methode == "" && is_string($fileOrDb))
    	{
    		$this->loadStoredTasksJson($fileOrDb);
    	}
    	else
    	{
    		$this->log->error("No Place to load or safe Tasks!",array($methode,$fileOrDb));
    		$this->sendMailNotification('admin@1601.com', 'error', 'GitReminder findet keinen Speicherplatz!');
    	}
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
    	mysql_select_db($dbName,$db_link) or die ("Keine Verbindung m�glich: ".mysql_error());
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
    public function sendMailNotification($mailadress,$text,$error = NULL)
    {    	
    	$header = 	'MIME-Version: 1.0' . "\r\n";
    	$header .=	'Content-type: text/html; charset=iso-8859-1' . "\r\n";
    	$header .=	"To: <$mailadress>" . "\r\n";
    	$header .= 	'From: GitReminder <reminder@gitreminder.com>' . "\r\n";
    	
    	
    	
    	$message = "
    		<html>
    		<head>
    		<title>New Notifications</title>
    		</head>
    		<body>";
    	
    	
    	if ($text == "newissue")
    	{
    		$subject = 	"[GitReminder] Pls. check your news";
    		$message .= "
    			Hello $mailadress,<br><br> Pls. check https://github.com and your notifications!<br><br>Have a nice day :)<br>GitReminder
    			";
    	}
    	elseif ($text == "error")
    	{
    		$subject = 	"[GitReminder] Error";
    		$message .= "
    		Hello $mailadress,<br><br>There is an error.<br>
    		Pls. check this message:<br><br>
    		\"$error\"
    		<br><br>
    		Have a nice day<br>
    		GitReminder
    		";
    	}
    	
    	$message .= "
    			</body>
    			</html>
    			";
    	
    	mail($mailadress, $subject, $message,$header);
        
    	return $this;
    }

	
    public function __destruct()
    {
    	echo "<pre>";
    	print_r($this->tasks);
    	echo "</pre>";
    	
    	
    	$this->log->notice("THE END");
    	return $this;
    }
    
    
}