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
     * @const string NAME_OF_GITREMINDER Default name of GitHub-User
     */
    const NAME_OF_GITREMINDER = 'gitreminder';
    
    /**
     * List of all found and pared tasks
     * @var array $tasks['/ghRepoUser/ghRepo/issues/ghIssueId'] = array('ghRepoUser' => X, 'ghRepo' => X, 'issueLink' => X, 'issueTitel' => X, 'ghIssueId' => X, 'assignIssueToUser' => X, 'sourceText' => X, 'matureDate' => X, 'author' => X, 'commentCreateDate' => X, ['sendMailNotificationTo' => X, 'commentMessage' => X, 'sms' => X])
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
	private $mySqlLink;


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
    	$this->createDataStructure();
    	$this->log = new log();
    	$this->log->notice(NOTICE_START);
    	$this->connectDb();
		$this->createDB();
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
		$this->mySqlLink = mysqli_connect($dbHost,$dbUser,$dbPass,$dbName);
		mysqli_set_charset($this->mySqlLink, 'utf8');

    	if(!empty(mysqli_error ($this->mySqlLink))){
			$this->log->error(CONNECTION_FAILED_DATABASE);
			die(USE_DATABASE);
		}

    	return $this;
    }

	/**
	 * Close the DB-Connection
	 * the call is in __destruct()
	 * need $this->mySqlLink
	 * @return bool;
	 */
	private function closeDb()
	{
		 return mysqli_close($this->mySqlLink);
	}


	/**
	 * Create folder and data structure
	 */
	private function createDataStructure()
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
	 * need $this->mySqlLink
	 * @return bool
	 */
	private function createDB()
	{
		$sql = "SHOW TABLES IN `tasks`";
		$result = mysqli_query($this->mySqlLink,$sql);

		var_dump($result);

		echo mysqli_num_rows($result);

		if(1 == 1){
			$sql = "
    		CREATE TABLE tasks(
    			`taskName` VARCHAR(255) NOT NULL PRIMARY KEY,
    			`ghRepoUser` VARCHAR(100) NOT NULL ,
    			`ghRepo` VARCHAR(100) NOT NULL ,
    			`ghIssueId` INT (20) NOT NULL ,
    			`issueLink` VARCHAR(255) NOT NULL ,
    			`issueTitel` VARCHAR(255) NOT NULL ,
    			`assignIssueToUser` VARCHAR(100) NOT NULL ,
    			`sendMailNotificationTo` VARCHAR(100) NOT NULL,
    			`commentMessage` VARCHAR(150) NOT NULL,
    			`sendSms` INT(50) NOT NULL,
    			`sourceText` VARCHAR(500) NOT NULL ,
    			`author` VARCHAR(100) NOT NULL ,
    			`commentCreateDate` INT(12) NOT NULL ,
    			`matureDateInDateform` VARCHAR(20) NOT NULL ,
    			`matureDate` INT(12) NOT NULL,
    			`commentAId` INT(20) NOT NULL,
    			`doneDay` INT(12) NOT NULL
    			)
    		ENGINE = MYISAM ;";

			if(!mysqli_query($this->mySqlLink, $sql))
				echo CANT_CREATE_TABLE." 'tasks'";
		}


		$sql = mysqli_query($this->mySqlLink,'select 1 from `settings` LIMIT 1');
		if($sql === FALSE){
			$sql = "
				CREATE TABLE settings(
					`name` VARCHAR( 255 ) NOT NULL PRIMARY KEY,
					`value` VARCHAR( 255 ) NOT NULL,
					`lastUpdate` INT(12) NOT NULL
					)
				ENGINE = MYISAM ;";

			if (!mysqli_query($this->mySqlLink, $sql))
				echo CANT_CREATE_TABLE." 'setting'";
		}

		return true;
	}

	/**
	 * Load stored task from Database
	 * need $this->mySqlLink
	 * @return $this
	 */
    private function loadStoredTasksFromDb()
    {
		$timeStamp = time();
    	$sql = "SELECT * FROM tasks WHERE `matureDate` < $timeStamp && doneDay = 0";
    	
    	$dbAnswer = mysqli_query($this->mySqlLink, $sql);

		if($dbAnswer !== false) {
			while ($dbLine = mysqli_fetch_assoc($dbAnswer)) {
				$this->tasks[$dbLine['taskName']] = $dbLine;
				$this->tasks[$dbLine['taskName']]['ghIssueId'] = intval($this->tasks[$dbLine['taskName']]['ghIssueId']);
			}
		}
		else{
			echo "No tasks in DB";
		}

		return $this;
    }


	/**
	 * Load the settings from database
	 * need $this->mySqlLink
	 */
	private function loadSettingsFromDB()
	{
		$sql = "SELECT * FROM settings";
		$dbAnswer = mysqli_query($this->mySqlLink, $sql);

		if($dbAnswer !== false) {
			while ($dbLine = mysqli_fetch_assoc($dbAnswer)) {
				$this->settings[$dbLine['name']] = array('value' => $dbLine['value'], 'lastUpdate' => intval($dbLine['lastUpdate']));
			}
		}
		else{
			echo "No settings in DB";
		}

		//Load fallback if entry "actionLimit" isn't in the array
		if(!isset($this->settings['actionLimit'])){
			echo "New init settings..";
			$this->settings['actionLimit'] = array('value' => 0, 'lastUpdate' => 0);
		}
	}











	/**
	 * @param $actionLimit
	 * @return bool
	 */
	private function checkCountigSettigs($actionLimit = ACTION_LIMIT)
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
	private function checkLimitPerRun($actionLimitPerRun = ACTION_LIMIT_PER_RUN)
	{
		if($this->runLimitInt >= $actionLimitPerRun)
		{
			$this->log->warning(EDIT_MORE_THAN_20_ISSUES,$this->tasks);
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
			if (isset($task['sendMailNotificationTo']))$sendMailNotificationTo = $task['sendMailNotificationTo'];
				else $sendMailNotificationTo = 0;

			if (isset($task['commentMessage']))$commentMessage = $task['commentMessage'];
				else $commentMessage = 0;

			if (isset($task['sms']))$sms = $task['sms'];
				else $sms = 0;

			$sql = "
			INSERT INTO tasks
			SET
				taskName = '$taskName',
				ghRepoUser = '".$task['ghRepoUser']."',
				ghRepo = '".$task['ghRepo']."',
				ghIssueId = '".$task['ghIssueId']."',
				issueLink = '".$task['issueLink']."',
				issueTitel = '".$task['issueTitel']."',
				assignIssueToUser = '".$task['assignIssueToUser']."',
				sendMailNotificationTo = '".$sendMailNotificationTo."',
				commentMessage = '".$commentMessage."',
				sendSms = '".$sms."',
				sourceText = '".$task['sourceText']."',
				author = '".$task['author']."',
				commentCreateDate = '".$task['commentCreateDate']."',
				matureDateInDateform = '".$task['matureDateInDateform']."',
				matureDate = '".$task['matureDate']."',
				commentAId = '".$task['commentAId']."',
				doneDay = '".$task['doneDay']."'

			ON DUPLICATE KEY UPDATE
				ghRepoUser = '".$task['ghRepoUser']."',
				ghRepo = '".$task['ghRepo']."',
				ghIssueId = '".$task['ghIssueId']."',
				issueLink = '".$task['issueLink']."',
				issueTitel = '".$task['issueTitel']."',
				assignIssueToUser = '".$task['assignIssueToUser']."',
				sendMailNotificationTo = '".$sendMailNotificationTo."',
				commentMessage = '".$commentMessage."',
				sendSms = '".$sms."',
				sourceText = '".$task['sourceText']."',
				author = '".$task['author']."',
				commentCreateDate = '".$task['commentCreateDate']."',
				matureDateInDateform = '".$task['matureDateInDateform']."',
				matureDate = '".$task['matureDate']."',
				commentAId = '".$task['commentAId']."',
				doneDay = '".$task['doneDay']."'
				;
			";

			if(!mysqli_query($this->mySqlLink,$sql))
				echo CANT_INSERT_OR_UPDATE_DB;
		}
		return $this;
	}


	/**
	 * Store all settings in db
	 * @return $this
	 */
	private function storeSettings()
	{
		$delete = "DELETE FROM settings";
		mysqli_query($this->mySqlLink, $delete);

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

			if(!mysqli_query($this->mySqlLink,$sql))
				echo CANT_INSERT_OR_UPDATE_DB;
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

			if($this->lookForGrInComments(array_reverse($comments),$nameGitReminder,$taskIndex)){
				return true;
			}
		}
		return false;
	}

	/**
	 * look for GitReminderName in comment-body
	 * work with: loadAllComments($repoOwner,$repo,$issueId,$loop,$nameGitReminder,$taskIndex)
	 * @param $comments
	 * @param $nameGitReminder
	 * @param $taskIndex
	 * @return bool
	 */
	private function lookForGrInComments($comments,$nameGitReminder,$taskIndex)
	{
		//Here we are looking for the $nameGitReminder (name of bot) in the other "body"strings
		foreach ($comments as $commentObject){
			$nextComments = $commentObject->getBody();

			//If name was founded and the ['sourceText'] and is not the "body"string, we write the whole "body"string in our global Array->(tasks). The ['sourceText'] before will be overwritten
			if (strpos($nextComments, $nameGitReminder) !== false){
				$this->tasks[$taskIndex]['sourceText'] = trim($nextComments);
				$this->tasks[$taskIndex]['author'] = $commentObject->getuser()->getlogin();
				$this->tasks[$taskIndex]['commentCreateDate'] = $commentObject->getCreatedAt();
				$this->tasks[$taskIndex]['commentAId'] =  $commentObject->getId();
				return true;
			}
		}
		return false;
	}

	/**
	 * Load all comments from an Issue
	 * work with: storeNotificationsInThisTasks()
	 * @param $repoOwner
	 * @param $repo
	 * @param $issueId
	 * @return bool
	 */
	private function loadIssueBody($repoOwner,$repo,$issueId,$nameGitReminder, $taskIndex)
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
			$issueTitel = $element["subject"]["title"];
			$issuePath = str_replace("https://api.github.com","",$element["subject"]["url"]);
			$issueId = intval(str_replace("/repos/$repoOwner/$repo/issues/","",$issuePath));

			//Check how many comments the Issue have.
			$issueObj = $this->getIssue($repoOwner, $repo, $issueId);
			//Calc the loop depending on comments
			$pages = intval($issueObj->getComments() / 30)+1;

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
				'doneDay' => 0,
			);

			// Load all comments an look
			// for task in issue-body instead of issue-comment
			if ($this->loadAllComments($repoOwner,$repo,$issueId,$pages,$nameGitReminder,$taskIndex)) {
				// Load the issue-bodys
				$issue = $this->loadIssueBody($repoOwner,$repo,$issueId,$nameGitReminder,$taskIndex);
				//Check, if the GitReminder-Name is in the issue-body...
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
			$comment['matureDateInDateform'] = date("d.m.Y H:i",$comment["matureDate"]);

			if ($value['matureDate'] >= 366){
				$this->createComment($comment['issueLink'], COMMENT_NOT_ASSIGN_365);
				$this->log->warning(ASSIGN_IN_TOO_MUCH_DAYS,$comment['ghIssueId'].$comment['ghRepo']);
				$comment["matureDate"] = time();
				$comment["assignIssueToUser"] = str_replace("@","",$comment['author']);
			}
		}
		elseif ($timeFormat == 'h' || $timeFormat == 's'){
			$comment["matureDate"] = $value['matureDate']*60*60+$comment['commentCreateDate'];
			$comment['matureDateInDateform'] = date("d.m.Y H:i",$comment["matureDate"]);
			if ($value['matureDate'] >= 8761){
				$this->log->warning(ASSIGN_IN_TOO_MUCH_DAYS,$comment['ghIssueId'].$comment['ghRepo']);
				$this->createComment($comment['issueLink'], COMMENT_NOT_ASSIGN_365);
				$comment["matureDate"] = time();
				$comment["assignIssueToUser"] = str_replace("@","",$comment['author']);
			}
		}
		elseif ($timeFormat == 'm'){
			$comment["matureDate"] = $value['matureDate']*60+$comment['commentCreateDate'];
			$comment['matureDateInDateform'] = date("d.m.Y H:i",$comment["matureDate"]);

			if ($value['matureDate'] >= 525600){
				$this->log->warning(ASSIGN_IN_TOO_MUCH_DAYS,$comment['ghIssueId'].$comment['ghRepo']);
				$this->createComment($comment['issueLink'], COMMENT_NOT_ASSIGN_365);
				$comment["matureDate"] = time();
				$comment["assignIssueToUser"] = str_replace("@","",$comment['author']);
			}
		}
		elseif ($timeFormat == ' '){
			$comment['matureDate'] = strtotime($value['matureDate']);
			$comment['matureDateInDateform'] = date("d.m.Y H:i",$comment["matureDate"]);
		}
		else{
			$comment["matureDate"] = $value['matureDate']*24*60*60+$comment['commentCreateDate'];
			$comment['matureDateInDateform'] = date("d.m.Y H:i",$comment["matureDate"]);
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
		$this->githubRepo->issues->editAnIssue($task["ghRepoUser"], $task["ghRepo"], $task["issueTitel"], $task["ghIssueId"], null, $task["assignIssueToUser"]);

		if (isset($task['sendMailNotificationTo']) && $task['sendMailNotificationTo'] != '0'){
			$link = str_replace("/repos", "", $task['issueLink']);
			$link = "https://github.com" . $link;
			$this->sendMailNotification($task['sendMailNotificationTo'], "newissue", $link);
		}
		elseif (isset($task['commentMessage']) && $task['commentMessage'] != '0'){
			$this->createComment($task['issueLink'], $task['commentMessage']);
		}
		elseif (isset($task['sendSms']) && $task['sendSms'] != '0'){
			//@todo implement
		}

		$this->log->info(ASSIGN_ISSUE_TO_USER,$task['ghIssueId'].$task['issueTitel'].$task['assignIssueToUser']);

		return true;
	}

	/**
	 * Process if an error is in the task
	 * work with checkUserAndProcess()
	 * @param $task
	 */
	private function processErrorTask($task)
	{
		$this->githubRepo->issues->editAnIssue($task["ghRepoUser"], $task["ghRepo"], $task["issueTitel"], $task["ghIssueId"], null, $task["author"]);
		$this->createComment($task['issueLink'],NOT_THE_USER_IN_REPO);
	}

	/**
	 * Check if the User is in the Repo
	 * @param $repoUser
	 * @param $repo
	 * @param $user
	 * @return bool
	 */
	private function checkContributorsForUserName($repoUser,$repo,$user)
	{
		$bool = false;
		$contributors = $this->githubRepo->request("/repos/".$repoUser."/".$repo."/collaborators", 'GET', array(), 200, 'GitHubUser', true);

		foreach($contributors as $contrie){
			$contributorUser = $contrie->getLogin();
			if($contributorUser == $user){
				$bool = true;
			}
		}
		return $bool;
	}










	/**********************************************************************
	 **********************************************************************
	 ****************************Here come public...***********************
	 **********************************************************************
	 **********************************************************************/













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
     * @return $this
     */
    public function loadGhNotifications($nameGitReminder = self::NAME_OF_GITREMINDER)
    {
    	//We are looking for new notifications and return them as an Array in var $notification
    	$notifications = json_decode($this->githubRepo->request("/notifications", 'GET', array('participating' => true), 200, 'string', true), true);

        if(count($notifications)>=30)$this->log->warning(CALLED_TOO_OFTEN,$notifications);

    	//Mark notifications as read.
    	$this->githubRepo->request("/notifications", 'PUT', array(1), 205, '');

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
    public function checkUserAndProcess()
	{
    	foreach ($this->tasks as $taskLink => &$task)
		{
    		if ($task["matureDate"] <= time() && isset($task["ghRepoUser"]))
     		{
				if($this->checkLimitPerRun() === false)
					die(EDIT_MORE_THAN_05_ISSUES);

				$bool = $this->checkContributorsForUserName($task['ghRepoUser'],$task['ghRepo'],$task['assignIssueToUser']);

				if(!$this->checkCountigSettigs())
					die(ACTION_LIMIT_OVER);

				if($bool == true){
					$this->processTask($task);
					$task['doneDay'] = time();
				}
				else{
					$this->processErrorTask($task);
					$task['doneDay'] = time();
				}
    		}
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
	 * Load an Issue with all important information
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
	 * @param string $error
	 * @return $this
	 */
    public function sendMailNotification($mailAddress,$text,$link = NULL,$error = MAIL_NO_ERROR_SEND)
    {    	
    	$header = MAIL_HEADER;
    	$header .= 'To: <'.$mailAddress.'>' . "\r\n";
    	
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
    	
    	mail($mailAddress, $subject, $message,$header);
        
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