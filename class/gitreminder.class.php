<?php
require_once 'log.class.php';
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
 * @Link		https://github.com/LBeckX
 * @Link        http://UnitGreen.com
 * @Link		https://www.facebook.com/lukas.beck36
 * @link        https://github.com/ADoebeling/GitReminder
 * @link        http://xing.doebeling.de
 * @link        http://www.1601.com
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
     * The Object of Class log
     * @var object $log
     */
    private $log;

	/**
	 * @var mysqli $mySqlI The mySql-Object
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
    public function __construct() {
		$this->log = new log();
		$this->lockFile();
    	$this->createFileStructure();
    	$this->log->notice(NOTICE_START, "GitReminder start work here ---------------------------------------------------------------------");
    	$this->connectDb();
		$this->createTableInDb();
		$this->loadStoredTasksFromDb();
		$this->loadSettingsFromDB();
    	return $this;
    }

    /**
     * Create a lock-File by start and delete it at the end.
     * If the file always exist, the script die with an exception.
     * @param string $createOrUnset
     * @throws Exception
     */
    private function lockFile($createOrUnset = 'create') {
		$filename = __DIR__.'/.lock';
		if($createOrUnset == 'create'){
			if(file_exists($filename)){
				throw new Exception('This system is locked. Pls. contact admin!');
			}
			else {
				if(!touch($filename)){
					throw new Exception('Could not create lock file!');
				}
			}
		}
		else if($createOrUnset == 'unset'){
			unlink($filename);
		}
		else{
			throw new Exception(__METHOD__."($createOrUnset) is not implemented!");
		}
	}

	/**
	 * DO the db-Connection and load all Data
	 * the call is in __construct()
	 *
	 * @param string $dbHost
	 * @param string $dbUser
	 * @param string $dbPass
	 * @param string $dbName
	 * @return $this
	 * @throws Exception
	 */
    private function connectDb($dbHost = DB_HOST,$dbUser = DB_USER, $dbPass = DB_PASS, $dbName = DB_NAME) {
		$this->mySqlI = new mysqli($dbHost,$dbUser,$dbPass,$dbName);

    	if($this->mySqlI->connect_error){
			throw new Exception(EXCEPTION_NEED_DATABASE);
		}
		$this->mySqlI->set_charset('utf8');

    	return $this;
    }

	/**
	 * Close the DB-Connection
	 *
	 * @return bool;
	 */
	private function closeDb() {
		return $this->mySqlI->close();
	}

	/**
	 * Create folder and data structure
	 */
	private function createFileStructure() {
		foreach ($this->folderStructure as $folder){
			if(!file_exists($folder)){
				mkdir($folder,0777);
			}
		}
	}

	/**
	 * Create a Database
	 * need $this->mySqlI
	 * @return bool
	 * @throws Exception
	 * @todo
	 */
	private function createTableInDb() {
		if($this->mySqlI->query('select 1 from `tasks` LIMIT 1') === false){
			$sql = "CREATE TABLE IF NOT EXISTS tasks(
					`taskName` VARCHAR(255) NOT NULL PRIMARY KEY,
					`ghRepoUser` VARCHAR(100) NOT NULL ,
					`ghRepo` VARCHAR(100) NOT NULL ,
					`ghIssueId` INT (20) NOT NULL ,
					`issueLink` VARCHAR(255) NOT NULL ,
					`issueTitle` VARCHAR(255) NOT NULL ,
					`assignIssueToUser` VARCHAR(100) NOT NULL ,
					`sendMailNotificationTo` VARCHAR(100) NOT NULL,
					`commentMessage` TEXT(2000) NOT NULL,
					`sendSms` INT(50) NOT NULL,
					`sourceText` TEXT(2000) NOT NULL ,
					`author` VARCHAR(100) NOT NULL ,
					`commentCreateDate` DATETIME NOT NULL ,
					`matureDate` DATETIME NOT NULL,
					`commentAId` INT(20) NOT NULL,
					`doneDay` INT(12) NOT NULL
					)
				ENGINE = MYISAM ;";

			if(!$this->mySqlI->query($sql)) {
				throw new Exception(EXCEPTION_CANT_CREATE_TABLE . " 'tasks'");
			}
		}

		if($this->mySqlI->query('select 1 from `settings` LIMIT 1') === false){
			$sql = "
				CREATE TABLE settings(
					`name` VARCHAR( 255 ) NOT NULL PRIMARY KEY,
					`value` VARCHAR( 255 ) NOT NULL,
					`lastUpdate` INT(12) NOT NULL
					)
				ENGINE = MYISAM ;";

			if (!$this->mySqlI->query($sql)) {
				throw new Exception(EXCEPTION_CANT_CREATE_TABLE . " 'setting'");
			}
		}
		return true;
	}

	/**
	 * Load stored task from Database
	 * need $this->mySqlI
	 * @return $this
	 * @throws Exception
	 */
    private function loadStoredTasksFromDb() {
		$dbAnswer = $this->mySqlI->query("SELECT * FROM tasks WHERE matureDate < now() && doneDay = 0");
		if($dbAnswer !== false) {
			while ($dbLine = mysqli_fetch_assoc($dbAnswer)) {
				$this->tasks[$dbLine['taskName']] = $dbLine;
				$this->tasks[$dbLine['taskName']]['issueTitle'] = str_replace(['\''],['\''],$dbLine['issueTitle']);
				$this->tasks[$dbLine['taskName']]['ghIssueId'] = intval($this->tasks[$dbLine['taskName']]['ghIssueId']);
				$this->tasks[$dbLine['taskName']]['matureDate'] = strtotime($dbLine['matureDate']);
				$this->tasks[$dbLine['taskName']]['commentCreateDate'] = strtotime($dbLine['commentCreateDate']);
			}
		}
		else
			throw new Exception('Can\'t get data form db_tasks');

		return $this;
    }

	/**
	 * Checks if a task in a comment has already been processed
	 * @param $commentId
	 * @return bool
	 */
	private function checkCommentStatus($commentId) {
		$sql = "select * from tasks WHERE `commentAId` = '$commentId' && `doneDay` != 0";
		return $this->mySqlI->query($sql)->num_rows > 0 ? true : false;
	}

	/**
	 * Load the settings from database
	 */
	private function loadSettingsFromDB() {
		$dbAnswer = $this->mySqlI->query("SELECT * FROM settings");

		while ($dbLine = $dbAnswer->fetch_assoc()) {
			$this->settings[$dbLine['name']] = array('value' => $dbLine['value'], 'lastUpdate' => $dbLine['lastUpdate']);
		}
	}

	/**
	 * Stores the current $tasks-array in a database
	 * @return $this
	 * @throws Exception
	 * @todo Kill upsert and create archive of old task. target: we have a log in the database and the gr  doesn't gets in trouble if you delete the last command.
	 */
	private function storeTasksInDatabase() {
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
				doneDay = '".$task['doneDay']."';";

			if(!$this->mySqlI->query($sql)) {
				throw new Exception(EXCEPTION_CANT_INSERT_OR_UPDATE_DB . ' tasks ');
			}
		}
		return $this;
	}

	/**
	 * Store all settings in db
	 * @return $this
	 * @throws Exception
	 */
	private function storeSettings() {
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
				throw new Exception(EXCEPTION_CANT_INSERT_OR_UPDATE_DB . 'settings');
			}
		}
		return $this;
	}

	/**
	 * Store all Notifications-Info-Comments into $this->tasks[] where comment body is with "GitReminder-Name"
	 * @param $notifications
	 * @param string $nameGitReminder
	 */
	private function loadNotificationsToTasks($notifications, $nameGitReminder = GITREMINDER_NAME) {
		foreach ($notifications as $element){
			$repoOwner = $element["repository"]["owner"]["login"];
			$repo =  $element["repository"]["name"];
			$issueTitle = $element["subject"]["title"];
			$issuePath = str_replace("https://api.github.com","",$element["subject"]["url"]);
			$issueId = intval(str_replace("/repos/$repoOwner/$repo/issues/","",$issuePath));

			$issueObj = $this->getIssue($repoOwner, $repo, $issueId);//

			/*Check how many comments the Issue has.
			Calc the loop depending on comments*/
			$pages = intval($issueObj->getComments() / 30)+1;

			//Write new Notification into the logfile
			$this->log->info(INFO_NEW_NOTIFICATION,$repo." -> ".$issueTitle);

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
					$this->createComment($this->tasks[$taskIndex]['issueLink'],WARNING_CANT_FIND_GR_IN_COMMENTS);
					$this->log->warning('Can\'t find GitReminder',WARNING_CANT_FIND_GR_IN_COMMENTS,$this->tasks[$taskIndex]);
					unset($this->tasks[$taskIndex]);
				}
			}
		}
	}

	/**
	 * Load all comments from an Issue
	 * work with: storeNotificationsInThisTasks()
	 * @param string $repoOwner
	 * @param string $repo
	 * @param int $issueId
	 * @param int $loop number of pages with comments in issue
	 * @param string $nameGitReminder
	 * @param string $taskIndex
	 * @return array
	 */
	private function loadAllComments($repoOwner,$repo,$issueId,$loop,$nameGitReminder,$taskIndex) {
		for ($i=$loop;$i>=1;$i--){
			//Load all commits in the Array $comments[] from issue
			$comments = $this->githubRepo->request("/repos/".$repoOwner."/".$repo."/issues/".$issueId."/comments?page=$i", 'GET', array(), 200, 'GitHubPullComment', true);
			$this->log->notice('API-Request!','Function:"loadAllComments()" || Pls. check the follow array');
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
	 * @param GitHubPullComment $commentObject
	 * @param $nameGitReminder
	 * @param $taskIndex
	 * @return bool
	 */
	private function lookForGrInComments(GitHubPullComment $commentObject,$nameGitReminder,$taskIndex) {
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
	private function loadIssueBody($repoOwner,$repo,$issueId,$nameGitReminder,$taskIndex) {
		$issue = $this->githubRepo->request("/repos/".$repoOwner."/".$repo."/issues/".$issueId, 'GET', array(), 200, 'GitHubPullComment', true);
		$this->log->notice('API-Request!','Function:"loadIssueBody()" || Pls. check the follow array',$issue);

		if($this->lookForGrInIssue($issue,$nameGitReminder,$taskIndex)) {
			return true;
		}
		return false;
	}

	/**
	 * Look for GitReminderName in the issue-body
	 * work with: storeNotificationsInThisTasks()
	 * @param GitHubPullComment $issue
	 * @param $nameGitReminder
	 * @param $taskIndex
	 * @return bool
	 */
	private function lookForGrInIssue(GitHubPullComment $issue,$nameGitReminder,$taskIndex) {
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
	 * Create the matureDate
	 * @param $timeFormat
	 * @param $value
	 * @param $comment
	 * @return mixed
	 */
	private function createMatureDate($timeFormat,$value,$comment) {
        if(isset($value['matureDate']) && strlen($value['matureDate']) >= 10 && $timeFormat == " "){
            $comment["matureDate"] = strtotime($value['matureDate']);
            if($comment["matureDate"] === false){
                $this->log->warning(WARNING_DATE_FORMAT_IS_FALSE,$comment['ghIssueId']." -> ".$comment['ghRepo']);
                $this->createComment($comment['issueLink'], COMMENT_DATE_FORMAT_IS_FALSE);
                $comment["assignIssueToUser"] = str_replace("@","",$comment['author']);
                $comment["matureDate"] = time();
                $timeFormat = 'm';
            }
            if($comment["matureDate"] >= time()+(365*24*60*60)){
                $this->log->warning(WARNING_THE_ASSIGN_IS_IN_TOO_MUCH_DAYS,$comment['ghIssueId']." -> ".$comment['ghRepo']);
                $this->createComment($comment['issueLink'], COMMENT_NOT_ASSIGN_365);
                $comment["matureDate"] = time();
                $comment["assignIssueToUser"] = str_replace("@","",$comment['author']);
            }
        }

	    //If the sytax say stop or ... GitReminder will assign in this moment.
		if (!isset($value['matureDate']) || $value['matureDate'] == 'stop' || $value['matureDate'] == 'ignore' || $value['matureDate'] == 'end' || $value['matureDate'] == 'now'){
			$value['matureDate'] = 0;
			$timeFormat = 'm';
		}

		//Check the timeformat and create the maturedate.
		if ($timeFormat == 'h' || $timeFormat == 's'){
			$comment["matureDate"] = $value['matureDate']*60*60+$comment['commentCreateDate'];
			if ($value['matureDate'] >= 366*24){
				$this->log->warning(WARNING_THE_ASSIGN_IS_IN_TOO_MUCH_DAYS,$comment['ghIssueId']." -> ".$comment['ghRepo']);
				$this->createComment($comment['issueLink'], COMMENT_NOT_ASSIGN_365);
				$comment["matureDate"] = time();
				$comment["assignIssueToUser"] = str_replace("@","",$comment['author']);
			}
		} elseif ($timeFormat == 'm'){
			$comment["matureDate"] = $value['matureDate']*60+$comment['commentCreateDate'];
			if ($value['matureDate'] >= 366*24*60){
				$this->log->warning(WARNING_THE_ASSIGN_IS_IN_TOO_MUCH_DAYS,$comment['ghIssueId']." -> ".$comment['ghRepo']);
				$this->createComment($comment['issueLink'], COMMENT_NOT_ASSIGN_365);
				$comment["matureDate"] = time();
				$comment["assignIssueToUser"] = str_replace("@","",$comment['author']);
			}
		} elseif(!isset($comment['matureDate']) || $comment['matureDate'] == null) {
			$comment["matureDate"] = $value['matureDate']*24*60*60+$comment['commentCreateDate'];
			if ($value['matureDate'] >= 366){
				$this->createComment($comment['issueLink'], COMMENT_NOT_ASSIGN_365);
				$this->log->warning(WARNING_THE_ASSIGN_IS_IN_TOO_MUCH_DAYS,$comment['ghIssueId']." -> ".$comment['ghRepo']);
				$comment["matureDate"] = time();
				$comment["assignIssueToUser"] = str_replace("@","",$comment['author']);
			}
		}

		return $comment;
	}

	/**
	 * Create event, for e.g. the mail-notification
	 * @param $value
	 * @param $comment
	 * @return mixed
	 */
	private function createFeatureTask($value,$comment) {
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
	 * Create task with the array-data-strings in $value
	 * work with: parseSourceText()
	 * @param array $value
	 * @param array $comment
	 * @return array $comment
	 */
	private function createTask($value,$comment) {
		//If the Value of $value["assignIssueToUser"] is not empty and is set, it writes the user in $this->tasks[*]["assignIssueToUser"] else the author of the comment is the userToAssign
		if (isset($value["assignIssueToUser"]) && $value["assignIssueToUser"] != "")
			$comment["assignIssueToUser"] = str_replace("@","" , $value["assignIssueToUser"]);
		else
			$comment["assignIssueToUser"] = str_replace("@","",$comment['author']);

		//Convert the createtimeformat into timestamp
		$comment['commentCreateDate'] = strtotime($comment['commentCreateDate']);

		if (isset($value['timeFormat'])){
            $timeFormat = strtolower($value['timeFormat']);
        } else {
            $timeFormat = 'm';
        }


		$comment = $this->createMatureDate($timeFormat,$value,$comment);

		$comment = $this->createFeatureTask($value,$comment);

		return $comment;
	}

	/**
	 * With this methode you can edit an GitHub Issue.
	 * For example you can assign an issue to an user
	 * @param $issueLink
	 * @param $title
	 * @param null $body
	 * @param null $assignee
	 * @param null $state
	 * @param null $milestone
	 * @param null $labels
	 * @return mixed
	 */
	private function editAnGHIssue($issueLink, $title = null, $body = null, $assignee = null, $state = null, $milestone = null, $labels = null) {
		$data = array();
        if(!is_null($title))
            $data['title'] = $title;
		if(!is_null($body))
			$data['body'] = $body;
		if(!is_null($assignee))
			$data['assignee'] = $assignee;
		if(!is_null($state))
			$data['state'] = $state;
		if(!is_null($milestone))
			$data['milestone'] = $milestone;
		if(!is_null($labels))
			$data['labels'] = $labels;

		return $this->githubRepo->request("$issueLink", 'PATCH', json_encode($data), 200, 'GitHubIssue');

	}

	/**
	 * Process the task and feature. For example write mail etc.
	 * work with checkUserAndProcess()
	 * @param $task
	 * @return bool
	 */
	private function processTask($task) {
        $return = $this->editAnGHIssue($task['issueLink'],null,null, $task["assignIssueToUser"]);
		$this->log->info("API-Request!",'Function: processTask() || Pls. check the following array',$return);

		if (isset($task['sendMailNotificationTo']) && $task['sendMailNotificationTo'] != '0'){
			$link = str_replace("/repos", "", $task['issueLink']);
			$this->sendMailNotification($task['sendMailNotificationTo'], "newissue", "https://github.com" . $link);
		}
		elseif (isset($task['commentMessage']) && $task['commentMessage'] != '0'){
			$this->createComment($task['issueLink'], $task['commentMessage']);
		}
		elseif (isset($task['sendSms']) && $task['sendSms'] != '0'){
			//@todo implement
            $this->createComment($task['issueLink'], COMMENT_SMS_NOT_IMPLEMENTED);
		}

		$this->log->info(INFO_ASSIGN_ISSUE_TO_AN_USER,'|| ID:'.$task['ghIssueId'].' || Issue title:'.$task['issueTitle'].' || Assigned user:'.$task['assignIssueToUser']);

		return true;
	}

	/**
	 * Process if an error is in the task
	 * work with checkUserAndProcess()
	 * @param $task
	 * @param $text
	 * @return true;
	 */
	private function processErrorTask($task,$text) {
		$return = $this->editAnGHIssue($task['issueLink'],null,null, $task['author']);
		$this->log->info("API-Request!",'Function: processErrorTask() || Pls. check the following array',$return);
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
	private function checkContributorsInIssue($repoUser,$repo,$user) {
		$contributors = $this->githubRepo->request("/repos/".$repoUser."/".$repo."/collaborators", 'GET', array(), 200, 'GitHubUser', true);
		$this->log->info("API-Request!",'Function: checkContributorsInIssue() || Pls. check the following array',$contributors);

		foreach($contributors as $contributor){
			$contributorUser = $contributor->getLogin();

			if(strtolower($contributorUser) == strtolower($user)){
				return true;
			}
		}
		return false;
	}

	/**
	 * Create a Comment in GH
	 * @param $ghIssueLink
	 * @param $body
	 * @return $this
	 */
	private function createComment($ghIssueLink,$body) {
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
		$return = $this->githubRepo->request($ghIssueLink."/comments", 'POST', json_encode($data), 201, 'GitHubIssueComment');
		$this->log->info("API-Request!",'Function: createComment() || Pls. check the following array',$return);
		return true;
	}

	/**
	 * Load an Issue with all important information
	 * @param $repoOwner
	 * @param $repo
	 * @param $issueId
	 * @return GitHubIssue
	 */
	private function getIssue($repoOwner,$repo,$issueId) {
		$issue = $this->githubRepo->request("/repos/$repoOwner/$repo/issues/$issueId",'GET', array(), 200, 'GitHubIssue');
		$this->log->info("API-Request!",'Function: createComment() || Pls. check the following array',$issue);
		return $issue;
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
	private function sendMailNotification($mailAddress,$text,$link = NULL,$comments = NULL,$error = MAIL_NO_ERROR_SEND) {
		$this->log->info("[SEND MAIL]",'Function: sendMailNotification()');

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



	/************************************************************************************************************************************************
	 ************************************************************************************************************************************************
	 ************************************************************** PUBLIC **************************************************************************
	 ************************************************************************************************************************************************
	 ************************************************************************************************************************************************/



	/**
	 * Login at github.com-API
	 * @param $ghUser
	 * @param $ghPassOrToken
	 * @return $this
	 */
	public function setGitHubAccount($ghUser, $ghPassOrToken) {
		$this->githubRepo = new GitHubClient();
		$this->githubRepo->setCredentials($ghUser, $ghPassOrToken);
		return $this;
	}

    /**
     * Load unread GitHub-Notifications
     * @param string $nameGitReminder
     * @return $this
     */
    public function loadGhNotifications($nameGitReminder) {
    	//We are looking for new notifications and return them as an Array in var $notification
    	$notifications = json_decode($this->githubRepo->request("/notifications", 'GET', array('participating' => true), 200, 'string', true), true);
		$this->log->info("API-Request!",'Function: loadGhNotifications() || Pls. check the following array',$notifications);

        if(count($notifications)>=30)$this->log->warning(WARNING_GR_CALLED_TOO_OFTEN,$notifications);

		$this->loadNotificationsToTasks($notifications,$nameGitReminder);

    	return $this;
	}

    /**
     * Parses all $this->tasks[$link]['sourceText'] and tries to find out
     * what to do and stores this information back in $this->$tasks
     * @param string $nameGitReminder
     * @return $this
     */
    public function parseSourceText($nameGitReminder = GITREMINDER_NAME) {
    	foreach ($this->tasks as &$comment)
    	{
    		if ((isset($comment) && !isset($comment["assignIssueToUser"]) || $comment["assignIssueToUser"] == "") && isset($comment['sourceText']))
    		{
				//Looking for the following syntax "@nameOfGitReminder [(+|-)](Int day or hour)[timeFormat] [UserToAssign]" like "@Gitreminder +4h @userToAssign" and divide this into Array->$value[]
	    		preg_match('/(?<gitreminder>@'.$nameGitReminder.')\s(\+|-)?(?<matureDate>\d{1,2}\.\d{1,2}\.\d{1,4} \d{1,2}:\d{1,2}|\d{1,2}-\d{1,2}-\d{1,4} \d{1,2}:\d{1,2}|\d{1,2}\.\d{1,2}\.\d{1,4}|\d{1,2}-\d{1,2}-\d{1,4}|\d{1,9}|stop|ignore|end|now)(?<timeFormat>.)?(\s)?(?<assignIssueToUser>@[a-zA-Z0-9\-]*)?( )?((?<sendmail>mail (?<sendmailto>.*@.*))|(?<writeComment>comment( )?(?<commentm>.*)?)|(?<sms>sms (?<number>0\d*)))?/',$comment['sourceText'],$value);
                $comment = $this->createTask($value,$comment);
	    	}
		}
    	return $this;
    }

	/**
	 * Kill script if task amount is higher than our action limit
	 * @param int $actionLimit
	 * @return $this
	 * @throws Exception
	 */
	public function checkActionLimit($actionLimit = ACTION_LIMIT_DAY) {
		if(count($this->tasks) >= $actionLimit){
			throw new Exception("System over actionlimit ($actionLimit)");
		}
		return $this;
	}

	/**
	 * Kill script if todos are more than $actionLimitPerRun.
	 * @param int $actionLimitPerRun
	 * @return $this
	 * @throws Exception
	 */
	public function checkActionLimitPerRun($actionLimitPerRun = ACTION_LIMIT_PER_RUN) {
		$runLimitCounter = 0;
		foreach($this->tasks as $task){
			if ($task['matureDate'] < time()) {
				$runLimitCounter++;
			}
		}

		if($runLimitCounter >= $actionLimitPerRun)
		{
			throw new Exception("System over actionlimit ($actionLimitPerRun)");
		}
		return $this;
	}

    /**
     * Processes all $this->tasks and perform all planned todos
     * @return $this
     */
    public function process() {
    	foreach ($this->tasks as $taskLink => &$task)
		{
			if($this->checkCommentStatus($task['commentAId']) === false) {
				if ($task['matureDate'] < time()) {

					if ($this->checkContributorsInIssue($task['ghRepoUser'], $task['ghRepo'], $task['assignIssueToUser'])) {
						$this->processTask($task);
						$task['doneDay'] = time();
					} else {
						$this->processErrorTask($task,COMMENT_NOT_THE_USER_IN_REPO);
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
	 * @return $this
	 */
	public function markNotificationAsRead() {
		//Mark notifications as read.
		$return = $this->githubRepo->request("/notifications", 'PUT', array(1), 205, '');
		$this->log->info("API-Request!",'Function: markNotificationAsRead() || Pls. check the following array',$return);

		return $this;
	}

	/**
	 * @return $this
	 */
	public function displayTasks() {
		echo "<pre><h2>Tasks to do</h2>";
		print_r($this->tasks);
		echo "</pre><br><br>";

		return $this;
	}

	/**
	 * Safe all Tasks and show all tasks
	 */
    public function __destruct() {
    	$this->storeTasksInDatabase();
		$this->storeSettings();
		$this->closeDb();
		$this->lockFile('unset');
		$this->log->notice(NOTICE_END,'... The End is here -------------------------------------------------------------------');
    }
}