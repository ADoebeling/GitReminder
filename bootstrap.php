<?php
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
    echo $e->getMessage().' in <br>'.$e->getFile().': <br>'.$e->getLine(),' : <br>',__FUNCTION__,' : <br>',$e->getTraceAsString();
    log::error($e->getMessage().' in '.$e->getFile().':'.$e->getLine(),__FUNCTION__,$e->getTraceAsString());
    //mail(ADMIN_MAIL,'[GitReminder] System got locked',$e->getMessage().' in '.$e->getFile().':'.$e->getLine()."\n\n". __FUNCTION__.$e->getTrace());
    @trigger_error('',E_USER_ERROR);
}
set_exception_handler('exceptionHandler');