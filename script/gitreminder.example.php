<?php

require_once('../class/gitreminder.class.php');

$gitReminder = new gitReminder();
$gitReminder -> setGithubAccount('gh-lb1601com', 'ichbineinpassword')
             //-> loadStoredTasks()
             -> loadGhNotifications()
             //-> parseSourceText()
             //-> process()
             //-> storeTasksSerialized();
?>