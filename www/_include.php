<?php

/* Remove magic quotes. */
if (get_magic_quotes_gpc()) {
    foreach (array('_GET', '_POST', '_COOKIE', '_REQUEST') as $a) {
        if (isset($$a) && is_array($$a)) {
            foreach ($$a as &$v) {
                /* We don't use array-parameters anywhere.
                 * Ignore any that may appear.
                 */
                if (is_array($v)) {
                    continue;
                }
                /* Unescape the string. */
                $v = stripslashes($v);
            }
        }
    }
}
if (get_magic_quotes_runtime()) {
    set_magic_quotes_runtime(FALSE);
}


/* Initialize the autoloader. */
require_once(dirname(dirname(__FILE__)) . '/lib/_autoload.php');

/* Enable assertion handler for all pages. */
SimpleSAML_Error_Assertion::installHandler();


function _getJoomlaApp($type = "site", $howmanybacks = 4) {
    $phpself = $_SERVER['PHP_SELF'];
    $scriptname = $_SERVER['SCRIPT_NAME'];
    $cleanPathRulesUntilMarker = "/components/com_samlogin/";
    try {
        $phpself = @substr($phpself, 0, stripos($phpself, $cleanPathRulesUntilMarker));
        $scriptname = @substr($scriptname, 0, stripos($scriptname, $cleanPathRulesUntilMarker));
    } catch (Exception $strlen) {
        $phpself = "";
        $scriptname = "";
    }
    $_SERVER['PHP_SELF'] = $phpself;
    $_SERVER['SCRIPT_NAME'] = $scriptname;

    define('DS', DIRECTORY_SEPARATOR);
    $joomla_base = dirname(__FILE__);
    $joomla_urlbacks = "";
    for ($i = 0; $i < $howmanybacks; $i++) {
        $joomla_base = substr($joomla_base, 0, strrpos($joomla_base, DS));
        $joomla_urlbacks .= "/../";
    }

    define('JPATH_BASE', $joomla_base);
    //   die (JPATH_BASE);
    define('_JEXEC', 1);
    require_once ( JPATH_BASE . DS . 'includes' . DS . 'defines.php' );
    require_once ( JPATH_BASE . DS . 'includes' . DS . 'framework.php' );
    $app = JFactory::getApplication($type);
    return $app;
}

function samloginJoomlaExceptionHandler($e) {
    $mess = $e->getMessage();

    error_reporting(0);
    ob_start();

    $app = _getJoomlaApp();
    $app->logout(); //do joomla logout

    jimport('joomla.methods');
    $langHandle = "SAMLOGIN_GENERIC_LOGIN_ERROR_USER_MESSAGE";
    $usermess = JText::_($langHandle);
    if ($usermess == $langHandle) {
        $usermess = $mess;
    }
    $app->enqueueMessage("SAML Error: " . $usermess, "error");
    // die($usermess);
    jimport('joomla.application.component.helper');
    $samloginParams = JComponentHelper::getParams('com_samlogin');

    $rediBase = $samloginParams->get('samlErrorRedirect', 0);
    if ($rediBase) {
        $link = 'index.php?Itemid=' . $rediBase;
        if (!empty($return)) {
            $link .= "&return=" . $return;
        }
    } else {
        $link = JURI::root();
    }
    $rediBase = JRoute::_($link);

    $rediURL = JRoute::_($rediBase, false);
    //die($rediURL);
    $suppressedNotices = ob_get_clean();
    // die(JURI::root());
    $app->redirect($rediURL . "#ssperror=" . urlencode($mess));
    die("<a href='$rediURL'>click here if not automatically redirected</a>");
}


/* Show error page on unhandled exceptions. */

function SimpleSAML_exception_handler(Exception $exception) {

    if ($exception instanceof SimpleSAML_Error_Error) {
        $exception->show();
    } else {
        samloginJoomlaExceptionHandler($e); //Just unhandled please
        $e = new SimpleSAML_Error_Error('UNHANDLEDEXCEPTION', $exception);
        $e->show();
    }
}

set_exception_handler('SimpleSAML_exception_handler');

/* Log full backtrace on errors and warnings. */

function SimpleSAML_error_handler($errno, $errstr, $errfile = NULL, $errline = 0, $errcontext = NULL) {

    if (!class_exists('SimpleSAML_Logger')) {
        /* We are probably logging a deprecation-warning during parsing.
         * Unfortunately, the autoloader is disabled at this point,
         * so we should stop here.
         *
         * See PHP bug: https://bugs.php.net/bug.php?id=47987
         */
        return FALSE;
    }


    if ($errno & SimpleSAML_Utilities::$logMask) {
        /* Masked error. */
        return FALSE;
    }

    static $limit = 5;
    $limit -= 1;
    if ($limit < 0) {
        /* We have reached the limit in the number of backtraces we will log. */
        return FALSE;
    }

    /* Show an error with a full backtrace. */
    $e = new SimpleSAML_Error_Exception('Error ' . $errno . ' - ' . $errstr);
    $e->logError();

    /* Resume normal error processing. */
    return FALSE;
}

set_error_handler('SimpleSAML_error_handler');

/**
 * Class which should print a warning every time a reference to $SIMPLESAML_INCPREFIX is made.
 */
class SimpleSAML_IncPrefixWarn {

    /**
     * Print a warning, as a call to this function means that $SIMPLESAML_INCPREFIX is referenced.
     *
     * @return A blank string.
     */
    function __toString() {
        $backtrace = debug_backtrace();
        $where = $backtrace[0]['file'] . ':' . $backtrace[0]['line'];
        error_log('Deprecated $SIMPLESAML_INCPREFIX still in use at ' . $where .
                '. The simpleSAMLphp library now uses an autoloader.');
        return '';
    }

}

/* Set the $SIMPLESAML_INCPREFIX to a reference to the class. */
$SIMPLESAML_INCPREFIX = new SimpleSAML_IncPrefixWarn();


$configdir = dirname(dirname(__FILE__)) . '/config';
if (!file_exists($configdir . '/config.php')) {
    header('Content-Type: text/plain');
    echo("You have not yet created the simpleSAMLphp configuration files.\n");
    echo("See: http://rnd.feide.no/content/installing-simplesamlphp#id434777\n");
    exit(1);
}

/* Set the timezone. */
SimpleSAML_Utilities::initTimezone();
?>