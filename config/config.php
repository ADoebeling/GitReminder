<?php 

/*************************************/
/*****    GitReminder need:   ********/
/*************************************/
const PATH_TO_FILE = '../data/';

//Where to safe tasks?
 const DB_HOST = 'mysql5.1601.com';
 const DB_USER = 'db226796_224';
 const DB_NAME = 'db226796_224';
 const DB_PASS = '';
//or
const FILE_SERIALIZED = PATH_TO_FILE.'tasks.phpserialize';
//or
const FILE_JSON = PATH_TO_FILE.'tasks.json';


//GitReminder:
const GITREMINDER_NAME = 'gh-lb1601com';
const GITREMINDER_PASSWD = '';



/**************** THE END **************/



//Endungen Const
const END_OF_SERIALIZE_FILE = 'phpserialize';
const END_OF_JASON_FILE = 'json';

//Log Messages
const NOTICE_START = 'Start';
const NOTICE_END = 'THE END';
const FILE_NOT_FOUND = 'File not found! ';
const CALLED_TOO_OFTEN = 'GitReminder has been called more than 30 times\n\n';
const NEW_NOTIFICATION = 'New notification from user and repo: ';
const ASSIGN_IN_TOO_MUCH_DAYS = 'The maturedate is in more tham 365 days.';
const ASSIGN_ISSUE_TO_USER = 'Issue has Assign to a User:';
const EDIT_MORE_THAN_20_ISSUES = 'More than 20 Issues has been edit!';
const CANT_CREATE_COMMENT = 'GitReminder cant create a Comment for ';
const CANT_LOAD_ISSUE = 'GitReminder cant load an Issue for ';


//Error Messages
const CONNECTION_FAILED_DATABASE = 'Connection to database failed! Pls. check your access.';
const WRONG = 'Something went quite wrong';

//Comment Messages
const COMMENT_NOT_ASSIGN_365 = 'It is not possible to assign a user in more than 365 days! One year is max!';
const COMMENT_BY_DO = 'Pls. do it now!';
const COMMENT_BY_WAIT = 'You\'ve to wait for new instruction';
const COMMENT_BY_ = 'Just do it, nothing is impossible :P';
const COMMENT_BY_LATER = 'Pls. do it until 3 days.';
const COMMENT_BY_LOGGASCH = 'MY CREATOR AND MASTER IS LOGGASCH';
const COMMENT_TRY_IT_AGAIN = 'There was a mistake pls. try it again.';


//Mailadress
const ADMIN_MAIL = 'admin@1601.com';


//Send Mail
const MAIL_HEADER = 'MIME-Version: 1.0' . "\r\n".'Content-type: text/html; charset=iso-8859-1' . "\r\n".'From: GitReminder <reminder@gitreminder.com>'."\r\n";

const MAIL_MESSAGE_START = "
			<html>
    		<head>
    		<title>Mail from GitReminder</title>
    		</head>
    		<body>";

const MAIL_MESSAGE_END = "
				</body>
    			</html>";

const MAIL_ISSUE_SUBJECT = "[GitReminder] Pls. check your news";
const MAIL_ISSUE_TEXT = "Hello,<br><br> Pls. check https://github.com and your notifications!<br><br>Have a nice day :)<br>GitReminder";

const MAIL_ERROR_SUBJECT = "[GitReminder] Error";
const MAIL_ERROR_TEXT = "Hello,<br><br>There is an error.<br>Pls. check this message:<br><br>";
const MAIL_NO_ERROR_SEND = 'There was no error in the mail!';
