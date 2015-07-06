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
     * List of all found and pared tasks
     * @var array $tasks['/ghRepoUser/ghRepo/issues/ghIssueId'] = array('ghRepoUser' => X, 'ghRepo' => X, 'ghIssueId' => X, 'assignIssueToUser' => X, 'sendMailNotificationTo' => X, 'sourceText' => X)
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
        $this->github->setCredentials($user, $pwd);
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
     * Load unread GitHub-Notifications and store them to
     * $this->tasks[]
     *
     * @todo Implement
     * @return $this
     */
    public function loadGhNotifications()
    {
        return $this;
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
     *
     * @param $file
     * @return $this
     */
    public function storeTasks($file = FILE_TASKS_SERIALIZED)
    {
    	if (!file_exists($file))
    	{
    		throw new Exception("File '$file' not found!",404);
    	}
    	
    	file_put_contents($file, serialize($this->tasks));
    	
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