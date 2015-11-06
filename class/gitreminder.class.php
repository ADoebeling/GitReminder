<?php

require_once '../3rd-party/github-php-client/client/GitHubClient.php';
require_once 'log.class.php';
require_once '../config/config.php';


/**
 * Class gitReminder
 *
 * This class controlls the github.com-bot 'GitReminder'
 * that allows you to get reminded/notified about planned
 * issues
 *
 * @author      Lukas Beck <lb@1601.com>
 * @author      Andreas Doebeling <ad@1601.com>
 * @copyright   1601.communication gmbh
 * @license     CC-BY-SA | https://creativecommons.org/licenses/by-sa/3.0
 * @link        https://github.com/ADoebeling/GitReminder
 * @link        http://xing.doebeling.de
 * @link        http://www.1601.com
 * @version     0.1.151103_1lb
 */
class gitReminder
{

    /**
     * @const string NAME_OF_GITREMINDER Default name of GitHub-User
     */
    const NAME_OF_GITREMINDER = 'gitreminder';
    
    /**
     * List of all found and pared tasks
     * @var array $tasks['/ghRepoUser/ghRepo/issues/ghIssueId'] = array('ghRepoUser' => X, 'ghRepo' => X, 'issueLink' => X, 'issueTitel' => X, 'ghIssueId' => X, 'assignIssueToUser' => X, 'sourceText' => X, 'matureDate' => X, 'commentAuthor' => X, 'commentCreateDate' => X, ['sendMailNotificationTo' => X, 'commentMessage' => X, 'sms' => X])
     */
    private $tasks = array();
    
    /**
     * An Array to check if the same Task has been executed.
     * @var array $oldTasks
     */
    private $oldTasks = array();
    
    
    /**
     * The Object of Class log
     * @var object $log
     */
    protected $log;

    
    /**
     * Array of Folderstructure
     * @var array $folderStructure
     */
    private $folderStructure = array('../logs');
    
  
    
    
    /**
     * Initialize github- and logger-class
     */
    public function __construct()
    {   
    	$this->createDataStructure();
    	$this->log = new log();
    	$this->log->notice(NOTICE_START);
    	$this->dbConnection();
    	return $this;
    }

	/**
	 *
	 * @param string $dbHost
	 * @return $this
	 */
    protected function dbConnection($dbHost = DB_HOST)
    {
    	if (defined('DB_HOST') && DB_HOST != '')
    	{
    		$this->loadStoredTasksFromDatabase(DB_HOST, DB_USER, DB_NAME, DB_PASS);
    		$this->loadOldGitReminderNotification(DB_HOST, DB_USER, DB_NAME, DB_PASS);
    		$this->createDB(DB_HOST, DB_USER, DB_NAME, DB_PASS);
    		
//     		$mysql = new mysqli();
//     		$mysql->query($link, $query);
    	}
    	else
    	{
    		$this->log->error(CONNECTION_FAILED_DATABASE);
    		die(USE_DATABASE);
    	}
    	return $this;
    }

	/**
	 * Login at github.com-API
	 * @param $ghUser
	 * @param $ghPassOrToken
	 * @return $this
	 * @throws GitHubClientException
	 */
    public function setGithubAccount ($ghUser, $ghPassOrToken)
    {
    	
    	$this->githubRepo = new GitHubClient(); 
        $this->githubRepo->setCredentials($ghUser, $ghPassOrToken);
        return $this;
    }

	/**
	 * Create folder and data structure
	 */
	private function createDataStructure()
	{
		foreach ($this->folderStructure as $folder)
		{
			if(!file_exists($folder))
			{
				mkdir($folder,0777);
			}
		}
		$timestamp = time();
		$date = date("Y-m",$timestamp);
		$logFile = "../logs";
		$logFileData = $logFile."/".$date."_logfolder";
		if (file_exists($logFile))
		{
			if (!file_exists($logFileData))
			{
				mkdir($logFileData,0777);
			}
		}
		
	}

	/**
	 * Create a Database
	 * @param $dbHost
	 * @param $dbUser
	 * @param $dbName
	 * @param $dbPwd
	 */
	private function createDB($dbHost, $dbUser, $dbName, $dbPwd)
	{
		$dbLink = mysqli_connect($dbHost,$dbUser,$dbPwd,$dbName);
		
		
		$sql = "
    	CREATE TABLE tasks(
    			`id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    			`taskName` VARCHAR( 150 ) NOT NULL ,
    			`ghRepoUser` VARCHAR( 150 ) NOT NULL ,
    			`ghRepo` VARCHAR( 150 ) NOT NULL ,
    			`ghIssueId` VARCHAR( 150 ) NOT NULL ,
    			`issueLink` VARCHAR( 150 ) NOT NULL ,
    			`issueTitel` VARCHAR( 150 ) NOT NULL ,
    			`assignIssueToUser` VARCHAR( 150 ) NOT NULL ,
    			`sendMailNotificationTo` VARCHAR( 150 ) NOT NULL,
    			`commentMessage` VARCHAR( 150 ) NOT NULL,
    			`sendSms` VARCHAR( 150 ) NOT NULL,
    			`sourceText` VARCHAR( 250 ) NOT NULL ,
    			`commentAuthor` VARCHAR( 250 ) NOT NULL ,
    			`commentCreateDate` VARCHAR( 250 ) NOT NULL ,
    			`matureDateInDateform` VARCHAR( 250 ) NOT NULL ,
    			`matureDate` INT(8) NOT NULL,
    			`commentAId` VARCHAR( 150 ) NOT NULL
    			)
    		ENGINE = MYISAM ;";
		 
		$erg = mysqli_query($dbLink, $sql);
		 
		$sql = "
    		CREATE TABLE old_tasks(
    			`id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    			`commentAId` VARCHAR( 150 ) NOT NULL,
    			`timeId` VARCHAR( 150 ) NOT NULL
    			)
    		ENGINE = MYISAM ;";
		 
		$erg = mysqli_query($dbLink, $sql);
		
		
		mysqli_close($dbLink);
	}


	/**
	 * Load stored task from Database
	 * @param $dbHost
	 * @param $dbUser
	 * @param $dbName
	 * @param $dbPwd
	 * @return $this
	 */
    public function loadStoredTasksFromDatabase($dbHost, $dbUser, $dbName, $dbPwd)
    {
    	$dbLink = mysqli_connect($dbHost,$dbUser,$dbPwd,$dbName);
    	 
    	if (mysqli_connect_errno())
    	{
    		die(CONNECTION_FAILED_DATABASE);
    	}
    	 
    	$sql = "SELECT * FROM tasks";
    	
    	$dbAnswer = mysqli_query($dbLink, $sql);
    	
    	while ($dbLine = mysqli_fetch_assoc($dbAnswer))
    	{
    		$this->tasks[$dbLine['taskName']] = array(
    				'ghRepoUser' => $dbLine['ghRepoUser'],
    				'ghRepo' => 	$dbLine['ghRepo'],
    				'issueLink' => $dbLine['issueLink'],
    				'issueTitel' => $dbLine['issueTitel'],
    				'ghIssueId' => 	$dbLine['ghIssueId'],
    				'assignIssueToUser' => $dbLine['assignIssueToUser'],
    				'sendMailNotificationTo' => $dbLine['sendMailNotificationTo'],
    				'commentMessage' => $dbLine['commentMessage'],
    				'sendSms' => $dbLine['sendSms'],
    				'sourceText' => $dbLine['sourceText'],
    				'commentAuthor' => $dbLine['commentAuthor'],
    				'commentCreateDate' => $dbLine['commentCreateDate'],
    				'matureDateInDateform' => $dbLine['matureDateInDateform'],
    				'matureDate' => $dbLine['matureDate'],			
    				'commentAId' => $dbLine['commentAId'],
    				);
    		$this->tasks[$dbLine['taskName']]['ghIssueId'] = intval($this->tasks[$dbLine['taskName']]['ghIssueId']);
    	}
    	
    	mysqli_close($dbLink);
    	
    	return $this;
    }
  
    
    /**
     * 
     */
    private function loadOldGitReminderNotification($dbHost, $dbUser, $dbName, $dbPwd)
    {
    	$dbLink = mysqli_connect($dbHost,$dbUser,$dbPwd,$dbName);
    	
    	if (mysqli_connect_errno())
    	{
    		die(CONNECTION_FAILED_DATABASE);
    	}
    	
    	$check = "SELECT * FROM old_tasks";
    	$dbAnswer = mysqli_query($dbLink, $check);
    	$i = 0;
    	while ($dbLine = mysqli_fetch_assoc($dbAnswer))
    	{
    		$this->oldTasks[$dbLine['commentAId']] = intval($dbLine['timeId']);
    		$i++;
    	}
    	
    	mysqli_close($dbLink);
    	
    	return $this;
    }
    
    
    
    /**
     * Load unread GitHub-Notifications and store them to $this->tasks[]
     * @param string $nameGitReminder
     * @return $this
     */
    public function loadGhNotifications($nameGitReminder = self::NAME_OF_GITREMINDER)
    {
    	//We are looking for new notifications and return them as an Array in var $notification
    	$notifications = json_decode($this->githubRepo->request("/notifications", 'GET', array('participating' => true), 200, 'string', true), true);

        if(count($notifications)>=30)$this->log->warning(CALLED_TOO_OFTEN,$notifications);
        
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
    		$this->log->info(NEW_NOTIFICATION,$repo.$issueTitel);
			
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
	    		$nextCommentAId = $commentObject->getId();
	    		$pos = strpos($nextComments, $nameGitReminder);
	    		 
	    		//If name was founded and the ['sourceText'] and is not the "body"string, we write the whole "body"string in our global Array->(tasks). The ['sourceText'] before will be overwritten
	    		if ($pos !== false)
	    		{
					$this->tasks[$taskIndex]['sourceText'] = trim($nextComments);
	    			$this->tasks[$taskIndex]['commentAuthor'] = trim($nextCommentAuthor);
	    			$this->tasks[$taskIndex]['commentCreateDate'] = trim($nextCommentDate);
	    			$this->tasks[$taskIndex]['commentAId'] =  trim($nextCommentAId);
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
    			
    			//Get the Absolute Comment ID
    			$firstCommentAId = $firstComment->getId();
    			
    			//Look at the "body"string and searching for $nameGitReminder (name of bot) in the first comment
    			$pos = strpos($firstCommentBody, $nameGitReminder);
    				
    			//If name was found and the ['sourceText'] and is not the "body"string, we write the whole "body"string in our global Array->(tasks). The ['sourceText'] before will be overwritten
    			if ($pos !== false)
    			{
    				$this->tasks[$taskIndex]['sourceText'] = trim($firstCommentBody);
    				$this->tasks[$taskIndex]['commentAuthor'] = trim($firstCommentAuthor);
    				$this->tasks[$taskIndex]['commentCreateDate'] = trim($firstCommentDate);
    				$this->tasks[$taskIndex]['commentAId'] =  trim($firstCommentAId);
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
    		if ((isset($comment) && !isset($comment["assignIssueToUser"]) || $comment["assignIssueToUser"] == "") && isset($comment['sourceText']))
    		{
				//Looking for the following syntax "@nameOfGitReminder [(+|-)](Int day or hour)[timeFormat] [UserToAssign]" like "@Gitreminder +4h @userToAssign" and divide this into Array->$value[]
	    		preg_match("/(?<gitreminder>@$nameGitReminder)\s(\+|-)?(?<matureDate>\d{1,2}\.\d{1,2}\.\d{1,4}|\d{1,2}-\d{1,2}-\d{1,4}|\d{1,9}|stop|ignore|end|now)(?<timeFormat>.)?(\s)?(?<assignIssueToUser>@[a-zA-Z0-9\-]*)?( )?((?<sendmail>mail (?<sendmailto>.*@.*))|(?<writeComment>comment( )?(?<commentm>.*)?)|(?<sms>sms (?<number>0\d*)))?/",$comment['sourceText'],$value);

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
	    				$this->createComment($comment['ghRepoUser'], $comment['ghRepo'], $comment['ghIssueId'], COMMENT_NOT_ASSIGN_365);
	    				$this->log->warning(ASSIGN_IN_TOO_MUCH_DAYS,$comment['ghIssueId'].$comment['ghRepo']);
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
	    				$this->log->warning(ASSIGN_IN_TOO_MUCH_DAYS,$comment['ghIssueId'].$comment['ghRepo']);
	    				$this->createComment($comment['ghRepoUser'], $comment['ghRepo'], $comment['ghIssueId'], COMMENT_NOT_ASSIGN_365);
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
	    				$this->log->warning(CONNECTION_FAILED_DATABASE,$comment['ghIssueId'].$comment['ghRepo']);
	    				$this->createComment($comment['ghRepoUser'], $comment['ghRepo'], $comment['ghIssueId'], COMMENT_NOT_ASSIGN_365);
	    				$comment["matureDate"] = time();
	    				$comment["assignIssueToUser"] = str_replace("@","",$comment['commentAuthor']);
	    			}
	    		}
	    		elseif ($timeFormat == ' ')
	    		{
	    			$comment['matureDate'] = strtotime($value['matureDate']);
	    			$comment['matureDateInDateform'] = date("d.m.Y H:i",$comment["matureDate"]);
	    		}
	    		else
	    		{
	    			$comment["matureDate"] = $value['matureDate']*24*60*60+$comment['commentCreateDate'];
	    			$comment['matureDateInDateform'] = date("d.m.Y H:i",$comment["matureDate"]);
	    		}


	    		if (isset($value['sendmail']) && $value['sendmail'] != '' && $value['sendmailto'] != '')
	    		{
	    			$comment['sendMailNotificationTo'] = $value['sendmailto'];
	    			$comment['commentMessage'] = '0';
	    			$comment['sendSms'] = '0';
	    		}
	    		elseif (isset($value['writeComment']) && $value['writeComment'] != '')
	    		{
	    			$comment['commentMessage'] = $value['commentm'];
	    			$comment['sendMailNotificationTo'] = '0';
	    			$comment['sendSms'] = '0';
	    		}
	    		elseif (isset($value['sms']) && $value['sms'] != '' && $value['number'] != '')
	    		{
	    			$comment['sendSms'] = $value['number'];
	    			$comment['sendMailNotificationTo'] = '0';
	    			$comment['commentMessage'] = '0';
	    		}
	    	}    	}
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
    		if ($task["matureDate"] <= time() && isset($task["ghRepoUser"]))
     		{
     		    $i++;
     			try
     			{
     				if (array_key_exists($task['commentAId'], $this->oldTasks))
     				{
     					echo USED_BEFORE ." ".$taskLink;
     					$this->log->notice(USED_BEFORE,$task);
     				}
     				else
     				{
     					$this->githubRepo->issues->editAnIssue($task["ghRepoUser"], $task["ghRepo"], $task["issueTitel"], $task["ghIssueId"],null,$task["assignIssueToUser"]);

	     				if (isset($task['sendMailNotificationTo']) && $task['sendMailNotificationTo'] != '0')
	     				{
							$link = str_replace("/repos","",$task['issueLink']);
							$link = "https://github.com".$link;
	     					$this->sendMailNotification($task['sendMailNotificationTo'],"newissue",$link);
	     				}
	     				elseif (isset($task['commentMessage']) && $task['commentMessage'] != '0')
	     				{
	     					$this->createComment($task['issueLink'],$task['commentMessage']);
	     				}
	     				elseif (isset($task['sendSms']) && $task['sendSms'] != '0')
	     				{
	     					//@todo implement
	     					//if (!isset($task["matureDate"])) $task["matureDate"] = time();
	     				}
	     				$this->oldTasks[$task['commentAId']] = time();
						foreach($this->oldTasks as $key => $value)
						{
							if ($value <= time() - 60*60*24*150) {
								echo "Comment ID: " . $key . " ist geloescht!<br>";
								unset($this->oldTasks[$key]);
							}
						}
     				}
     			}
     			catch (Exception $e)
     			{
     				// TODO: Implement Expeption handling
     				die(WRONG.$e->getMessage());		
     			}
     			$this->log->info(ASSIGN_ISSUE_TO_USER,$task['ghIssueId'].$task['issueTitel'].$task['assignIssueToUser']);
     			unset($this->tasks[$taskLink]);
     		}
        }
        if($i>=21)
        {
        	$this->log->warning(EDIT_MORE_THAN_20_ISSUES,$this->tasks);
        }
        return $this;
    }


	/**
	 * Create a Comment in GH
	 * @param $ghIssueLink
	 * @param $body
	 * @return $this
	 */
	public function createComment($ghIssueLink,$body)
	{
		if(is_string($ghIssueLink) && isset($body))
		{
			switch($body)
			{
				case 'do':
					$body = COMMENT_BY_DO;
					break;
				case 'wait':
					$body = COMMENT_BY_WAIT;
					break;
				case '':
					$body = COMMENT_BY_;
					break;
				case 'later':
					$body = COMMENT_BY_LATER;
					break;
				case 'loggasch':
					$body = COMMENT_BY_LOGGASCH;
					break;
			}			
			$data = array();
			$data['body'] = $body;
			$this->githubRepo->request("$ghIssueLink/comments", 'POST', json_encode($data), 201, 'GitHubIssueComment');
		}
		else
		{
			$this->log->warning(CANT_CREATE_COMMENT,array($ghIssueLink,$body));
		}
		return $this;
	}





	/**
	 * Load an Issue with all important informations.
	 * @param $repoOwner
	 * @param $repo
	 * @param $issueId
	 * @return bool
	 */
	public function getIssue($repoOwner,$repo,$issueId)
	{
		if (is_int($issueId) && is_string($repo) && is_string($repoOwner))
		{
			$issue = $this->githubRepo->request("/repos/$repoOwner/$repo/issues/$issueId",'GET', array(), 200, 'GitHubIssue');
		}
		else
		{
			$this->log->warning(CANT_LOAD_ISSUE,array($repo,$issueId));
			$this->createComment($repoOwner, $repo, $issueId, COMMENT_TRY_IT_AGAIN);
			return false;
			
		}
		
		return $issue;
	}




	/**
	 * Stores the current $tasks-array in a database
	 * @param $dbHost
	 * @param $dbUser
	 * @param $dbName
	 * @param $dbPwd
	 * @return $this
	 */
    public function storeTasksInDatabase($dbHost,$dbUser,$dbName,$dbPwd)
    {
    	$dbLink = mysqli_connect($dbHost,$dbUser,$dbPwd,$dbName);
    	
    	if (mysqli_connect_errno())
    	{
    		die(CONNECTION_FAILED_DATABASE);
    	}
    	
    	
    	mysqli_set_charset($dbLink, 'utf8');
    	
    	$delete = "DELETE FROM tasks";
    	
    	$erg = mysqli_query($dbLink, $delete);
    	
    	
    	
    			
    	foreach ($this->tasks as $taskName=>$task)
    	{
    		$ghRepoUser = $task['ghRepoUser'];
    		$ghRepo = $task['ghRepo'];
    		$ghIssueId = $task['ghIssueId'];
    		$assignIssueToUser = $task['assignIssueToUser'];
    		$ghIssueLink = $task['issueLink'];
    		$ghIssueTitel = $task['issueTitel'];
    		$sourceText = $task['sourceText'];
    		$matureDate = $task['matureDate'];
    		$commentAuthor = $task['commentAuthor'];
    		$commentCreateDate = $task['commentCreateDate'];
    		$matureDateInDateform = $task['matureDateInDateform'];
    		$commentAId = $task['commentAId'];
    		
    		
    		if (isset($task['sendMailNotificationTo']))
    		{
    			$sendMailNotificationTo = $task['sendMailNotificationTo'];
    		}else $sendMailNotificationTo = 0;
    		
    		if (isset($task['commentMessage']))
    		{
    			$commentMessage = $task['commentMessage'];
    		}else $commentMessage = 0;

			if (isset($task['sms']))
    		{
    			$sms = $task['sms'];
    		}else $sms = 0;
    		
    		
    		// mysql_query("Insert into 'tasks' set name='$name', wert='$wert', letzterwert=22")
			
			$insert = "
			INSERT INTO tasks 
			(
				taskName,
				ghRepoUser,
				ghRepo,
				ghIssueId,
				issueLink,
				issueTitel,
				assignIssueToUser,
				sendMailNotificationTo,
				commentMessage,
				sendSms,
				sourceText,
				commentAuthor,
				commentCreateDate,
				matureDateInDateform,
				matureDate,
				commentAId
			) 
			VALUES 
			(
				'$taskName',
				'$ghRepoUser',
				'$ghRepo',
				'$ghIssueId',
				'$ghIssueLink',
				'$ghIssueTitel',
				'$assignIssueToUser',
				'$sendMailNotificationTo',
				'$commentMessage',
				'$sms',
				'$sourceText',
				'$commentAuthor',
				'$commentCreateDate',
				'$matureDateInDateform',
				'$matureDate',
				'$commentAId'
			)";
    		
    		mysqli_query($dbLink,$insert);
    		
    	}
    	mysqli_close($dbLink);
    	return $this;
    }

    
    private function storeOldGitReminderNotification($dbHost,$dbUser,$dbName,$dbPwd)
    {
    	$dbLink = mysqli_connect($dbHost,$dbUser,$dbPwd,$dbName);
    	
    	$delete = "DELETE FROM old_tasks";
    	mysqli_query($dbLink, $delete);
    	
    	foreach ($this->oldTasks as $oldTask => $time)
    	{
	    	$insertTwo = "INSERT INTO old_tasks (
	    	commentAId,
	    	timeId
	    	) VALUES (
	    	'$oldTask',
	    	'$time'
	    	)";
	    	mysqli_query($dbLink,$insertTwo);
    	}
    	mysqli_close($dbLink);
		unset($this->oldTasks);
    	return $this;
    }


	/**
	 * Send a mail-notification
	 * @param $mailadress
	 * @param $text
	 * @param string $error
	 * @return $this
	 */
    public function sendMailNotification($mailadress,$text,$link = NULL,$error = MAIL_NO_ERROR_SEND)
    {    	
    	$header = MAIL_HEADER;
    	$header .= 'To: <'.$mailadress.'>' . "\r\n";
    	
    	
    	$message = MAIL_MESSAGE_START;
    	
    	
    	if ($text == "newissue")
    	{
    		$subject = 	MAIL_ISSUE_SUBJECT;
    		$message .= MAIL_ISSUE_TEXT;
			$message .= " ".$link." ";
			$message .= MAIL_ISSUE_TEXT_END;
    	}
    	elseif ($text == "error")
    	{
    		$subject = 	MAIL_ERROR_SUBJECT;
    		$message .= MAIL_ERROR_TEXT;
    		$message .= $error;
    	}
    	
    	$message .= MAIL_MESSAGE_END;
    	
    	mail($mailadress, $subject, $message,$header);
        
    	return $this;
    }


	/**
	 * Safe all Tasks
	 */
    public function __destruct()
    {
    	echo "<pre><h2>Tasks to do</h2>";
		print_r($this->tasks);
    	echo "</pre><br><br>";
		echo "<pre><h2>Old Tasks</h2>";
		print_r($this->oldTasks);
		echo "</pre>";
    	$this->log->notice(NOTICE_END);
    	$this->storeTasksInDatabase(DB_HOST, DB_USER, DB_NAME, DB_PASS);
    	$this->storeOldGitReminderNotification(DB_HOST, DB_USER, DB_NAME, DB_PASS);
    	return $this;
    }
    
    
}