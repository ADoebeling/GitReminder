<?php
error_reporting (1);
error_reporting(E_ALL);

require_once '3rd-party/github-php-client/client/GitHubClient.php';
require_once 'class/log.class.php';
require_once 'config/config.php';
require_once 'class/gitreminder.class.php';

/**
 * @param \Exception $e
 * @return bool
 */
function exceptionHandler(\Exception $e)
{
    echo '<h1>Error</h1><p>Sorry, the script died with a exception</p>';
    log2::error($e->getMessage().' in '.$e->getFile().':'.$e->getLine(), __FUNCTION__,$e->getTrace());
    mail(ADMIN_MAIL,'[GitReminder] System got locked',$e->getMessage().' in '.$e->getFile().':'.$e->getLine()."\n\n". __FUNCTION__.$e->getTrace());
    @trigger_error('',E_USER_ERROR);
}
set_exception_handler('exceptionHandler');