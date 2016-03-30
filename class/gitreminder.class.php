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
 * @version     0.1.160322_1lb
 */
class gitReminder
{

	/**
	 * The clientobject
	 * @var object
	 */
	private $githubRepo;


    /**
     * List of all found and pared tasks
     * @var array $tasks['/ghRepoUser/ghRepo/issues/ghIssueId'] = array('ghRepoUser' => X, 'ghRepo' => X, 'issueLink' => X, 'issueTitle' => X, 'ghIssueId' => X, 'assignIssueToUser' => X, 'sourceText' => X, 'matureDate' => X, 'author' => X, 'commentCreateDate' => X, ['sendMailNotificationTo' => X, 'commentMessage' => X, 'sms' => X])
     */
    private $tasks = array();


	/**
	 * In this global-var are all settings
	 * @var array
	 */
	private $settings = array();


	/**
	 * Run int
	 * @var int $runLimitInt
	 */
	private $runLimitInt = 0;

    /**
     * The Object of Class log
     * @var object $log
     */
    private $log;


	/**
	 * The mySql-Object
	 */
	private $mySqlI;


    /**
     * Array of Folderstructure
     * @var array $folderStructure
     */
    private $folderStructure = array('../logs');
    

    /**
     * Initialize github- and logger-class
     */
    function __construct()
    {   
    	$this->createFileStructure();
    	$this->log = new log();
    	$this->log->notice(NOTICE_START);
    	$this->connectDb();
		$this->createTableinDb();
		$this->loadStoredTasksFromDb();
		$this->loadSettingsFromDB();
    	return $this;
    }

	/**
	 * DO the db-Connection and load all Data
	 * the call is in __construct()
	 * @param string $dbHost
	 * @param string $dbUser
	 * @param string $dbPass
	 * @param string $dbName
	 * @return $this
	 */
    private function connectDb($dbHost = DB_HOST,$dbUser = DB_USER, $dbPass = DB_PASS, $dbName = DB_NAME)
    {
		$this->mySqlI = new mysqli($dbHost,$dbUser,$dbPass,$dbName);

    	if($this->mySqlI->connect_error){
			$this->log->error(CONNECTION_FAILED_DATABASE);
			die(USE_DATABASE);
		}
		$this->mySqlI->set_charset('utf8');

    	return $this;
    }

	/**
	 * Close the DB-Connection
	 * the call is in __destruct()
	 * need $this->mySqlI
	 * @return bool;
	 */
	private function closeDb()
	{
		 return $this->mySqlI->close();
	}



	/**
	 * Create folder and data structure
	 */
	private function createFileStructure()
	{
		foreach ($this->folderStructure as $folder){
			if(!file_exists($folder)){
				mkdir($folder,0777);
			}
		}
		$date = date("Y-m",time());
		$logDir = "../logs";
		$logFileDirDate = $logDir."/".$date."_logfolder";
		if (file_exists($logDir)){
			if (!file_exists($logFileDirDate)){
				mkdir($logFileDirDate,0777);
			}
		}
	}


	/**
	 * Create a Database
	 * need $this->mySqlI
	 * @return bool
	 */
	private function createTableinDb()
	{
		if($this->mySqlI->query('select 1 from `tasks` LIMIT 1') === false){
			$sql = "
				CREATE TABLE IF NOT EXISTS tasks(
					`taskName` VARCHAR(255) NOT NULL PRIMARY KEY,
					`ghRepoUser` VARCHAR(100) NOT NULL ,
					`ghRepo` VARCHAR(100) NOT NULL ,
					`ghIssueId` INT (20) NOT NULL ,
					`issueLink` VARCHAR(255) NOT NULL ,
					`issueTitle` VARCHAR(255) NOT NULL ,
					`assignIssueToUser` VARCHAR(100) NOT NULL ,
					`sendMailNotificationTo` VARCHAR(100) NOT NULL,
					`commentMessage` VARCHAR(150) NOT NULL,
					`sendSms` INT(50) NOT NULL,
					`sourceText` VARCHAR(500) NOT NULL ,
					`author` VARCHAR(100) NOT NULL ,
					`commentCreateDate` DATETIME NOT NULL ,
					`matureDate` DATETIME NOT NULL,
					`commentAId` INT(20) NOT NULL,
					`doneDay` INT(12) NOT NULL
					)
				ENGINE = MYISAM ;";

			if(!$this->mySqlI->query($sql))
				echo CANT_CREATE_TABLE." 'tasks'";
		}


		if($this->mySqlI->query('select 1 from `settings` LIMIT 1') === false){
			$sql = "
				CREATE TABLE settings(
					`name` VARCHAR( 255 ) NOT NULL PRIMARY KEY,
					`value` VARCHAR( 255 ) NOT NULL,
					`lastUpdate` INT(12) NOT NULL
					)
				ENGINE = MYISAM ;";

			if (!$this->mySqlI->query($sql))
				echo CANT_CREATE_TABLE." 'setting'";
		}

		return true;
	}

	/**
	 * Load stored task from Database
	 * need $this->mySqlI
	 * @return $this
	 */
    private function loadStoredTasksFromDb()
    {
		$dbAnswer = $this->mySqlI->query("SELECT * FROM tasks WHERE UNIX_TIMESTAMP(matureDate) < ".time()." && doneDay = 0");
		if($dbAnswer !== false) {
			while ($dbLine = mysqli_fetch_assoc($dbAnswer)) {
				$this->tasks[$dbLine['taskName']] = $dbLine;
				$this->tasks[$dbLine['taskName']]['issueTitle'] = str_replace(['\''],['\''],$dbLine['issueTitle']);
				$this->tasks[$dbLine['taskName']]['ghIssueId'] = intval($this->tasks[$dbLine['taskName']]['ghIssueId']);
				$this->tasks[$dbLine['taskName']]['matureDate'] = strtotime($dbLine['matureDate']);
				$this->tasks[$dbLine['taskName']]['commentCreateDate'] = strtotime($dbLine['commentCreateDate']);
			}
		}

		return $this;
    }

	/**
	 * Check if the Comment has been edited before
	 * @param $commentId
	 * @return bool
	 */
	private function checkCommentIdInDb($commentId)
	{
		$oldTask = false;

		$sql = "select * from tasks WHERE `commentAId` = '$commentId' && `doneDay` != 0";

		$dbAnswer = $this->mySqlI->query($sql);

		if($dbAnswer !== false) {
			while ($dbLine = mysqli_fetch_assoc($dbAnswer)) {
				$oldTask[$dbLine['taskName']] = $dbLine;
			}
		}

		if($oldTask === false)
			return false;
		else
			return true;


	}


	/**
	 * Load the settings from database
	 * need $this->mySqlI
	 */
	private function loadSettingsFromDB()
	{
		$dbAnswer = $this->mySqlI->query("SELECT * FROM settings");

		if($dbAnswer !== false) {
			while ($dbLine = mysqli_fetch_assoc($dbAnswer)) {
				$this->settings[$dbLine['name']] = array('value' => $dbLine['value'], 'lastUpdate' => intval($dbLine['lastUpdate']));
			}
		}

		//Load fallback if entry "actionLimit" isn't in the array
		if(!isset($this->settings['actionLimit'])){
			$this->settings['actionLimit'] = array('value' => 0, 'lastUpdate' => 0);
		}
	}


	/**
	 * Delete a File
	 * It works with: checkUserAndProcess()
	 * @param $filePath
	 * @return bool
	 */
	private function deleteFile($filePath)
	{
		return unlink($filePath);
	}

	/**
	 * Check the actual counting-number and the limit.
	 * If the number is over limit it return false
	 * It works with: checkUserAndProcess()
	 * @param $actionLimit
	 * @return bool
	 */
	private function checkActionLimit($actionLimit = ACTION_LIMIT)
	{
		$count = &$this->settings['actionLimit']['value'];
		$lastUpdate = &$this->settings['actionLimit']['lastUpdate'];

		$actualDate = date("Yz",time());
		$lastUpdateInDate = date("Yz",$lastUpdate);


		if($actualDate != $lastUpdateInDate){
			$this->settings['actionLimit']['lastUpdate'] = time();
			$count = 0;
		}

		if($count >= $actionLimit){
			return false;
		}
		else{
			$this->settings['actionLimit']['value'] = $count + 1;
			return true;
		}
	}

	/**
	 * Check the Limit
	 * @param $actionLimitPerRun
	 * @return bool || int
	 */
	private function checkActionLimitPerRun($actionLimitPerRun = ACTION_LIMIT_PER_RUN)
	{
		if($this->runLimitInt >= $actionLimitPerRun)
		{
			$this->log->warning(EDIT_MORE_THAN_05_ISSUES,$this->tasks);
			return false;
		}
		$this->runLimitInt++;

		return true;
	}



	/**
	 * Stores the current $tasks-array in a database
	 * @return $this
	 */
	private function storeTasksInDatabase()
	{
		foreach ($this->tasks as $taskName=>$task)
		{
			if (!isset($task['sendMailNotificationTo']))$task['sendMailNotificationTo'] = 0;

			if (!isset($task['commentMessage']))$task['commentMessage'] = 0;

			if (!isset($task['sms']))$task['sms'] = 0;

			$dateTime = date("Y-m-d H:i:s",$task['matureDate']);
			$commentCreateDate = date("Y-m-d H:i:s",$task['commentCreateDate']);

			$sql = "
			INSERT INTO tasks
			SET
				taskName = '$taskName',
				ghRepoUser = '".$task['ghRepoUser']."',
				ghRepo = '".$task['ghRepo']."',
				ghIssueId = '".$task['ghIssueId']."',
				issueLink = '".$task['issueLink']."',
				issueTitle = '".str_replace(['\''],['\\\''],$task['issueTitle'])."',
				assignIssueToUser = '".$task['assignIssueToUser']."',
				sendMailNotificationTo = '".$task['sendMailNotificationTo']."',
				commentMessage = '".$task['commentMessage']."',
				sendSms = '".$task['sms']."',
				sourceText = '".$task['sourceText']."',
				author = '".$task['author']."',
				commentCreateDate = '".$commentCreateDate."',
				matureDate = '".$dateTime."',
				commentAId = '".$task['commentAId']."',
				doneDay = '".$task['doneDay']."'

			ON DUPLICATE KEY UPDATE
				ghRepoUser = '".$task['ghRepoUser']."',
				ghRepo = '".$task['ghRepo']."',
				ghIssueId = '".$task['ghIssueId']."',
				issueLink = '".$task['issueLink']."',
				issueTitle = '".str_replace(['\''],['\\\''],$task['issueTitle'])."',
				assignIssueToUser = '".$task['assignIssueToUser']."',
				sendMailNotificationTo = '".$task['sendMailNotificationTo']."',
				commentMessage = '".$task['commentMessage']."',
				sendSms = '".$task['sms']."',
				sourceText = '".$task['sourceText']."',
				author = '".$task['author']."',
				commentCreateDate = '".$commentCreateDate."',
				matureDate = '".$dateTime."',
				commentAId = '".$task['commentAId']."',
				doneDay = '".$task['doneDay']."'
				;
			";

			if(!$this->mySqlI->query($sql)) {
				echo CANT_INSERT_OR_UPDATE_DB . ' tasks ';
				$this->log->warning(CANT_INSERT_OR_UPDATE_DB . ' tasks ',$task);
			}
		}
		return $this;
	}


	/**
	 * Store all settings in db
	 * @return $this
	 */
	private function storeSettings()
	{
		foreach ($this->settings as $name => $setting)
		{
			$sql = "
			INSERT INTO settings
			SET
	    	`name` = '$name',
	    	`value` = '".$setting['value']."',
	    	`lastUpdate` = '".$setting['lastUpdate']."'

	    	ON DUPLICATE KEY UPDATE
				`value` = '".$setting['value']."',
				`lastUpdate` = '".$setting['lastUpdate']."'
				;
	    	";

			if(!$this->mySqlI->query($sql)) {
				echo CANT_INSERT_OR_UPDATE_DB . 'settings';
				$this->log->warning(CANT_INSERT_OR_UPDATE_DB . ' settings ',$setting);
			}
		}
		return $this;
	}



	/**
	 * Load all comments from an Issue
	 * work with: storeNotificationsInThisTasks()
	 * @param $repoOwner
	 * @param $repo
	 * @param $issueId
	 * @param $loop
	 * @param $nameGitReminder
	 * @param $taskIndex
	 * @return array
	 */
	private function loadAllComments($repoOwner,$repo,$issueId,$loop,$nameGitReminder,$taskIndex)
	{
		for ($i=$loop;$i>=1;$i--){
			//Load all commits in the Array $comments[] from issue
			$comments = $this->githubRepo->request("/repos/".$repoOwner."/".$repo."/issues/".$issueId."/comments?page=$i", 'GET', array(), 200, 'GitHubPullComment', true);

			foreach (array_reverse($comments) as $commentObject) {
				if ($this->lookForGrInComments($commentObject,$nameGitReminder, $taskIndex)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * look for GitReminderName in comment-body
	 * work with: loadAllComments($repoOwner,$repo,$issueId,$loop,$nameGitReminder,$taskIndex)
	 * @param $commentObject
	 * @param $nameGitReminder
	 * @param $taskIndex
	 * @return bool
	 */
	private function lookForGrInComments($commentObject,$nameGitReminder,$taskIndex)
	{
		//Here we are looking for the $nameGitReminder (name of bot) in the other "body"strings
		$nextComments = $commentObject->getBody();

			//If name was founded and the ['sourceText'] and is not the "body"string, we write the whole "body"string in our global Array->(tasks). The ['sourceText'] before will be overwritten
		if (strpos($nextComments, $nameGitReminder) !== false){
			$this->tasks[$taskIndex]['sourceText'] = trim($nextComments);
			$this->tasks[$taskIndex]['author'] = $commentObject->getuser()->getlogin();
			$this->tasks[$taskIndex]['commentCreateDate'] = $commentObject->getCreatedAt();
			$this->tasks[$taskIndex]['commentAId'] =  $commentObject->getId();
			return true;
		}
		return false;
	}


	/**
	 * Load all comments from an Issue
	 * work with: storeNotificationsInThisTasks()
	 * @param $repoOwner
	 * @param $repo
	 * @param $issueId
	 * @param $nameGitReminder
	 * @param $taskIndex
	 * @return bool
	 */
	private function loadIssueBody($repoOwner,$repo,$issueId,$nameGitReminder,$taskIndex)
	{
		$issue = $this->githubRepo->request("/repos/".$repoOwner."/".$repo."/issues/".$issueId, 'GET', array(), 200, 'GitHubPullComment', true);

		if($this->lookForGrInIssue($issue,$nameGitReminder,$taskIndex))
			return true;

		return false;
	}

	/**
	 * Look for GitReminderName in the issue-body
	 * work with: storeNotificationsInThisTasks()
	 * @param $issue
	 * @param $nameGitReminder
	 * @param $taskIndex
	 * @return bool
	 */
	private function lookForGrInIssue($issue,$nameGitReminder,$taskIndex)
	{
		//Look at the "body"string and searching for $nameGitReminder (name of bot) in the first comment
		//Here we will get the body (the message) from the issue
		$issueBody = $issue->getBody();

		//If name was found and the ['sourceText'] and is not the "body"string, we write the whole "body"string in our global Array->(tasks). The ['sourceText'] before will be overwritten
		if (strpos($issueBody, $nameGitReminder) !== false){
			$this->tasks[$taskIndex]['sourceText'] = trim($issueBody);
			$this->tasks[$taskIndex]['author'] = $issue->getuser()->getlogin();
			$this->tasks[$taskIndex]['commentCreateDate'] = $issue->getCreatedAt();
			$this->tasks[$taskIndex]['commentAId'] =  $issue->getId();
			return true;
		}
		return false;
	}

	/**
	 * Store all Notifications-Info-Comments into $this->tasks[] where comment body is with "GitReminder-Name"
	 * @param $notifications
	 * @param string $nameGitReminder
	 */
	private function storeNotificationsInThisTasks($notifications,$nameGitReminder = self::NAME_OF_GITREMINDER)
	{
		foreach ($notifications as $element){
			$repoOwner = $element["repository"]["owner"]["login"];
			$repo =  $element["repository"]["name"];
			$issueTitle = $element["subject"]["title"];
			$issuePath = str_replace("https://api.github.com","",$element["subject"]["url"]);
			$issueId = intval(str_replace("/repos/$repoOwner/$repo/issues/","",$issuePath));

			//Check how many comments the Issue have.
			$issueObj = $this->getIssue($repoOwner, $repo, $issueId);
			//Calc the loop depending on comments
			$pages = intval($issueObj->getComments() / 30)+1;
			$status = $issueObj->getState();
			//Write new Notification into the logfile
			$this->log->info(NEW_NOTIFICATION,$repo.$issueTitle);

			//Create the Index of one task
			$taskIndex = "/$repoOwner/$repo/issue/$issueId";

			//We create the Array tasks[] with [index] and subarray[values]
			$this->tasks[$taskIndex] = array(
				'ghRepoUser' => $repoOwner,
				'ghRepo'	 => $repo,
				'issueLink'  => $issuePath,
				'issueTitle' => $issueTitle,
				'ghIssueId'	 => $issueId,
				'doneDay' => 0,
			);

			// Load all comments and look for task in issue-body instead of issue-comment
			if (!$this->loadAllComments($repoOwner,$repo,$issueId,$pages,$nameGitReminder,$taskIndex)) {

				//Load the issue-body; Check, if the GitReminder-Name is in the issue-body...
				if(!$this->loadIssueBody($repoOwner,$repo,$issueId,$nameGitReminder,$taskIndex)){

					//If GR-Name is not in the comments or issue, we create a comment, if the issue is open.
					if($status == 'open'){
						$this->createComment($this->tasks[$taskIndex]['issueLink'] , CANT_FIND_GR_IN_COMMENTS);
						$this->log->warning(CANT_FIND_GR_IN_COMMENTS,$this->tasks[$taskIndex]);
						unset($this->tasks[$taskIndex]);
					}
					$this->log->warning(CANT_FIND_GR_IN_COMMENTS .  "Issue is closed",$this->tasks[$taskIndex]);
					unset($this->tasks[$taskIndex]);
				}
			}
		}
	}

	/**
	 * Create the matureDate and need:
	 * @param $timeFormat
	 * @param $value
	 * @param $comment
	 * @return mixed
	 */
	private function createMatureDate($timeFormat,$value,$comment)
	{
		//If the sytax say stop or ... GitReminder will assign in this moment.
		if ($value['matureDate'] == 'stop' || $value['matureDate'] == 'ignore' || $value['matureDate'] == 'end' || $value['matureDate'] == 'now'){
			$value['matureDate'] = 0;
			$timeFormat = 'm';
		}

		//Check the timeformat and create the maturedate.
		if ($timeFormat == 'd' || $timeFormat == 't'){
			$comment["matureDate"] = $value['matureDate']*24*60*60+$comment['commentCreateDate'];

			if ($value['matureDate'] >= 366){
				$this->createComment($comment['issueLink'], COMMENT_NOT_ASSIGN_365);
				$this->log->warning(ASSIGN_IN_TOO_MUCH_DAYS,$comment['ghIssueId'].$comment['ghRepo']);
				$comment["matureDate"] = time();
				$comment["assignIssueToUser"] = str_replace("@","",$comment['author']);
			}
		}
		elseif ($timeFormat == 'h' || $timeFormat == 's'){
			$comment["matureDate"] = $value['matureDate']*60*60+$comment['commentCreateDate'];
			if ($value['matureDate'] >= 8761){
				$this->log->warning(ASSIGN_IN_TOO_MUCH_DAYS,$comment['ghIssueId'].$comment['ghRepo']);
				$this->createComment($comment['issueLink'], COMMENT_NOT_ASSIGN_365);
				$comment["matureDate"] = time();
				$comment["assignIssueToUser"] = str_replace("@","",$comment['author']);
			}
		}
		elseif ($timeFormat == 'm'){
			$comment["matureDate"] = $value['matureDate']*60+$comment['commentCreateDate'];

			if ($value['matureDate'] >= 525600){
				$this->log->warning(ASSIGN_IN_TOO_MUCH_DAYS,$comment['ghIssueId'].$comment['ghRepo']);
				$this->createComment($comment['issueLink'], COMMENT_NOT_ASSIGN_365);
				$comment["matureDate"] = time();
				$comment["assignIssueToUser"] = str_replace("@","",$comment['author']);
			}
		}
		elseif ($timeFormat == ' '){
			$comment['matureDate'] = $value['matureDate']*24*60*60+$comment['commentCreateDate'];
		}
		else{
			$comment["matureDate"] = $value['matureDate']*24*60*60+$comment['commentCreateDate'];
		}

		return $comment;
	}

	/**
	 * Create for example the mail-notification
	 * @param $value
	 * @param $comment
	 * @return mixed
	 */
	private function createFeatureTask($value,$comment)
	{
		if (isset($value['sendmail']) && $value['sendmail'] != '' && $value['sendmailto'] != ''){
			$comment['sendMailNotificationTo'] = $value['sendmailto'];
		}
		elseif (isset($value['writeComment']) && $value['writeComment'] != ''){
			$comment['commentMessage'] = $value['commentm'];
		}
		elseif (isset($value['sms']) && $value['sms'] != '' && $value['number'] != ''){
			$comment['sendSms'] = $value['number'];
		}
		return $comment;
	}

	/**
	 * Create task with the array-data-strings in value
	 * work with: parseSourceText()
	 * @param array $value
	 * @param array $comment
	 * @return array $comment
	 */
	private function createTask($value,$comment)
	{
		//If the Value of $value["assignIssueToUser"] is not empty and is set it write the user in $this->tasks[~]["assignIssueToUser"] else the author of the comment is the userToAssign
		if (isset($value["assignIssueToUser"]) && $value["assignIssueToUser"] != "")
			$comment["assignIssueToUser"] = str_replace("@","" , $value["assignIssueToUser"]);
		else
			$comment["assignIssueToUser"] = str_replace("@","",$comment['author']);

		//Convert the createtimeformat into timestamp
		$comment['commentCreateDate'] = strtotime($comment['commentCreateDate']);

		if (isset($value['timeFormat']))
			$timeFormat = strtolower($value['timeFormat']);
		else
			$timeFormat = 'm';

		$comment = $this->createMatureDate($timeFormat,$value,$comment);

		$comment = $this->createFeatureTask($value,$comment);

		return $comment;
	}

	/**
	 * Process the task and feature. For example write mail etc.
	 * work with checkUserAndProcess()
	 * @param $task
	 * @return bool
	 */
	private function processTask($task)
	{
		$this->githubRepo->issues->editAnIssue($task["ghRepoUser"], $task["ghRepo"], $task["issueTitle"], $task["ghIssueId"], null, $task["assignIssueToUser"]);

		if (isset($task['sendMailNotificationTo']) && $task['sendMailNotificationTo'] != '0'){
			$link = str_replace("/repos", "", $task['issueLink']);
			$this->sendMailNotification($task['sendMailNotificationTo'], "newissue", "https://github.com" . $link);
		}
		elseif (isset($task['commentMessage']) && $task['commentMessage'] != '0'){
			$this->createComment($task['issueLink'], $task['commentMessage']);
		}
		elseif (isset($task['sendSms']) && $task['sendSms'] != '0'){
			//@todo implement
		}

		$this->log->info(ASSIGN_ISSUE_TO_USER,$task['ghIssueId'].$task['issueTitle'].$task['assignIssueToUser']);

		return true;
	}

	/**
	 * Process if an error is in the task
	 * work with checkUserAndProcess()
	 * @param $task
	 * @param $text
	 * @return true;
	 */
	private function processErrorTask($task,$text)
	{
		$this->githubRepo->issues->editAnIssue($task["ghRepoUser"], $task["ghRepo"], $task["issueTitle"], $task["ghIssueId"], null, $task["author"]);
		$this->createComment($task['issueLink'],$text);
		return true;
	}

	/**
	 * Check if the User is in the Repo
	 * @param $repoUser
	 * @param $repo
	 * @param $user
	 * @return bool
	 */
	private function checkContributorsInIssue($repoUser,$repo,$user)
	{
		$contributors = $this->githubRepo->request("/repos/".$repoUser."/".$repo."/collaborators", 'GET', array(), 200, 'GitHubUser', true);

		foreach($contributors as $contributor){
			$contributorUser = $contributor->getLogin();

			if($contributorUser == $user)
				return true;
		}
		return false;
	}

	/**
	 * Create a Comment in GH
	 * @param $ghIssueLink
	 * @param $body
	 * @return $this
	 */
	private function createComment($ghIssueLink,$body)
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
			$this->githubRepo->request($ghIssueLink."/comments", 'POST', json_encode($data), 201, 'GitHubIssueComment');
			return true;
		}
		else
		{
			$this->log->warning(CANT_CREATE_COMMENT,array($ghIssueLink,$body));#
			return false;
		}
	}

	/**
	 * Load an Issue with all important information
	 * @param $repoOwner
	 * @param $repo
	 * @param $issueId
	 * @return bool
	 */
	private function getIssue($repoOwner,$repo,$issueId)
	{
		if (is_int($issueId) && is_string($repo) && is_string($repoOwner))
		{
			$issue = $this->githubRepo->request("/repos/$repoOwner/$repo/issues/$issueId",'GET', array(), 200, 'GitHubIssue');
			return $issue;
		}
		else
		{
			$this->log->warning(CANT_LOAD_ISSUE,array($repo,$issueId));
			return false;
		}
	}

	/**
	 * Send mail-notification
	 * @param $mailAddress
	 * @param $text
	 * @param $link
	 * @param $comments
	 * @param string $error
	 * @return bool
	 */
	private function sendMailNotification($mailAddress,$text,$link = NULL,$comments = NULL,$error = MAIL_NO_ERROR_SEND)
	{
		$header = MAIL_HEADER;
		$header .= 'To: <'.$mailAddress.'>' . "\r\n";
		$subject = MAIL_STANDARD_SUBJECT;
		$message = MAIL_MESSAGE_START;

		if ($text == "newissue")
		{
			$subject .= " ".MAIL_ISSUE_SUBJECT;
			$message .= MAIL_ISSUE_TEXT;
			$message .= " ".$link." ";

			if($comments != NULL)
				$message .= "<br><br>".$comments."<br><br>";

			$message .= MAIL_ISSUE_TEXT_END;
			$message .= MAIL_FOOTER;
		}
		elseif ($text == "error")
		{
			$subject .= " ".MAIL_ERROR_SUBJECT;
			$message .= MAIL_ERROR_TEXT;
			$message .= $error;
			$message .= MAIL_FOOTER;
		}

		$message .= MAIL_MESSAGE_END;

		return mail($mailAddress, $subject, $message,$header);
	}


	/******************************************************************************************************************************************************************************************************************
	 ******************************************************************************************************************************************************************************************************************
	 ***********************************************************************************************Here it comes public...********************************************************************************************
	 ******************************************************************************************************************************************************************************************************************
	 ******************************************************************************************************************************************************************************************************************/


	/**
	 * Login at github.com-API
	 * @param $ghUser
	 * @param $ghPassOrToken
	 * @return $this
	 */
	public function setGithubAccount($ghUser, $ghPassOrToken)
	{
		$this->githubRepo = new GitHubClient();
		$this->githubRepo->setCredentials($ghUser, $ghPassOrToken);
		return $this;
	}

    /**
     * Load unread GitHub-Notifications
     * @param string $nameGitReminder
	 * @param bool $markAsRead
     * @return $this
     */
    public function loadGhNotifications($nameGitReminder = self::NAME_OF_GITREMINDER,$markAsRead)
    {
    	//We are looking for new notifications and return them as an Array in var $notification
    	$notifications = json_decode($this->githubRepo->request("/notifications", 'GET', array('participating' => true), 200, 'string', true), true);

        if(count($notifications)>=30)$this->log->warning(CALLED_TOO_OFTEN,$notifications);

		if($markAsRead) {
			//Mark notifications as read.
			$this->githubRepo->request("/notifications", 'PUT', array(1), 205, '');
		}

		$this->storeNotificationsInThisTasks($notifications,$nameGitReminder);

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

				$comment = $this->createTask($value,$comment);
	    	}
		}
    	return $this;
    }

    /**
     * Processes all $this->tasks and perform all planned todos
     * @return $this
     */
    public function process()
	{
    	foreach ($this->tasks as $taskLink => &$task)
		{
			if($this->checkCommentIdInDb($task['commentAId']) === false) {
				if ($task['matureDate'] < time()) {

					if ((!$this->checkActionLimitPerRun() || !$this->checkActionLimit()) && DELETE_FILE_FOR_SAFE == true) {
						$this->deleteFile('../htdocs/index_2.php');
						die(EDIT_MORE_THAN_05_ISSUES . ACTION_LIMIT_OVER);
					}

					if ($this->checkContributorsInIssue($task['ghRepoUser'], $task['ghRepo'], $task['assignIssueToUser'])) {
						$this->processTask($task);
						$task['doneDay'] = time();
					} else {
						$this->processErrorTask($task, NOT_THE_USER_IN_REPO);
						$task['doneDay'] = time();
					}
				}
			}
			else {
				unset($this->tasks[$taskLink]);
			}
		}
        return $this;
    }



	/**
	 * Safe all Tasks and show all tasks
	 */
    public function __destruct()
    {
    	echo "<pre><h2>Tasks to do</h2>";
		print_r($this->tasks);
    	echo "</pre><br><br>";
    	$this->log->notice(NOTICE_END);
    	$this->storeTasksInDatabase();
		$this->storeSettings();
		$this->closeDb();
    	return $this;
    }
}