<?php
/*****    GitReminder need:   ********/

// db connection
const DB_HOST = ''; // db-host (domain / ip)
const DB_USER = ''; // db-user
const DB_NAME = ''; // db-name
const DB_PASS = ''; // db-password

// admin email address
const ADMIN_MAIL = ''; // (admin@domain.tld)

// GitReminder (gitHub user):
const GITREMINDER_NAME = ''; // GitHub user
const GITREMINDER_PASSWD = ''; // GitHub user password

//How many issues can be edited
const ACTION_LIMIT_DAY = 50;
const ACTION_LIMIT_PER_RUN = 20;

/**************** THE END **************/

//Log Messages
const NOTICE_START = 'START';
const NOTICE_END = 'END';
const WARNING_GR_CALLED_TOO_OFTEN = 'GitReminder has been called more than 30 times.';
const WARNING_CANT_FIND_GR_IN_COMMENTS = 'Can\'t find GitReminder in Comments.';
const WARNING_THE_ASSIGN_IS_IN_TOO_MUCH_DAYS = 'Can\' assign in more than 365 days.';
const WARNING_DATE_FORMAT_IS_FALSE = 'The date format is false:';
const INFO_ASSIGN_ISSUE_TO_AN_USER = 'Issue is assigned to a user';
const INFO_NEW_NOTIFICATION = 'New notification from user and repo:';

//Error Messages
const EXCEPTION_NEED_DATABASE = 'Pls. check Config and use a database!';
const EXCEPTION_CANT_CREATE_TABLE = 'There is an error, can\'t create a table';
const EXCEPTION_CANT_INSERT_OR_UPDATE_DB = 'There is an error, can\'t inset or update table';

//Comment Messages
const COMMENT_BY_DO = 'Pls. do it now. :/';
const COMMENT_BY_WAIT = 'You\'ve to wait for new instruction :|';
const COMMENT_BY_ = 'Just do it, nothing is impossible :P';
const COMMENT_BY_LATER = 'You can do it later :)';
const COMMENT_BY_LOGGASCH = ' -- MY CREATOR AND MASTER IS LOGGASCH -- ';

const COMMENT_NOT_THE_USER_IN_REPO = 'Can\'t assign this user, cause I did not find it this repo :(';
const COMMENT_NOT_ASSIGN_365 = 'It is not possible to assign a user in more than 365 days! One year is max! :(';
const COMMENT_DATE_FORMAT_IS_FALSE = 'Sorry, but you use the wrong time format';
const COMMENT_SMS_NOT_IMPLEMENTED = 'Sorry, SMS is not implemented yet.';

//Send Mail
const MAIL_HEADER = 'MIME-Version: 1.0' . "\r\n".
    'Content-type: text/html; charset=UTF-8'."\r\n".
    'From: GitReminder <reminder@gitreminder.com>'."\r\n";

const MAIL_STANDARD_SUBJECT = '[GitReminder]';

const MAIL_MESSAGE_START = "
			<html>
    		<head>
    		<title>Mail from GitReminder</title>
    		</head>
    		<body>";

const MAIL_MESSAGE_END = "
				</body>
    			</html>";

const MAIL_ISSUE_SUBJECT = "Check your notifications";
const MAIL_ISSUE_TEXT = 'Hello,<br><br>You have new notifications!<br>Pls. check: ';
const MAIL_ISSUE_TEXT_END = "<br><br>Have a nice day :)<br>GitReminder";

const MAIL_ERROR_SUBJECT = "Error";
const MAIL_ERROR_TEXT = "Hello,<br><br>There is an error.<br>Pls. check this message:<br><br>";
const MAIL_NO_ERROR_SEND = 'There was no error in the mail!';

const MAIL_FOOTER = '
<br><br>
Freundliche Grüße
Der GitReminder
<br><br>
--
<br><br>
1601.communication gmbh<br>
am weichselgarten 5 · 91058 erlangen · germany
<br><br>
fon +49 9131.50677.0<br>
fax +49 9131.50677.40
<br><br>
admin@1601.com<br>
www.1601.com
<br><br>
Geschäftsführer: Patrick Siegler, Christoph Thümmler<br>
Sitz Erlangen · AG Fürth/Bay · HR B 11223
';