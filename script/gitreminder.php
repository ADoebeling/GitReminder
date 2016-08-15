<?php
require_once('../bootstrap.php');

    if(date('G',time()) > 6){
        $gitReminder = new gitReminder();
        $gitReminder
            ->setGitHubAccount(GITREMINDER_NAME, GITREMINDER_PASSWD)
            ->loadGhNotifications(GITREMINDER_NAME)
            ->parseSourceText(GITREMINDER_NAME)
            ->checkActionLimitPerRun()
            ->checkActionLimit()
            ->markNotificationAsRead()
            ->process()
            ->displayTasks();
    }


