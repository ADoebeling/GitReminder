<?php

require_once('../class/gitreminder.class.php');

$gitReminder = new gitReminder();
$gitReminder -> setGithubAccount($ghUser, $password)
             //-> loadStoredTasks()
             -> loadGhNotifications($ghUser)
             -> parseSourceText($ghUser)
             //-> process()
             //-> storeTasksSerialized();
?>