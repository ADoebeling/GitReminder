<?php

require_once('../class/gitreminder.class.php');

$gitReminder = new gitReminder();
$gitReminder -> setGhAccount('gitHub-User', 'API-Token')
             -> loadStoredTasks()
             -> loadGhNotifications()
             -> parseSourceText()
             -> process()
             -> storeTasks();