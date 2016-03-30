<?php
require_once('../class/gitreminder.class.php');
require_once ('../config/config.php');

    $gitReminder = new gitReminder();
    $gitReminder->setGithubAccount(GITREMINDER_NAME, GITREMINDER_PASSWD)
        ->loadGhNotifications(GITREMINDER_NAME,true)
        ->parseSourceText(GITREMINDER_NAME)
        ->process();