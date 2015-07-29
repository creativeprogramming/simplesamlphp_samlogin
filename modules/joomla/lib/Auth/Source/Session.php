<?php

/**
 * Simple joomla session authsource
 *
 * This class authenticate an user from a joomla session, valid only for idemauth joomla extension
 *
 * @package simpleSAMLphp
 * @version $Id$
 */
class sspmod_joomla_Auth_Source_Session extends SimpleSAML_Auth_Source {

    /**
     * The key of the AuthId field in the state.
     */
    const AUTHID = 'joomlaSession:AuthId';

    /**
     * Constructor for this authentication source.
     *
     * @param array $info  Information about this authentication source.
     * @param array $config  Configuration.
     */
    public function __construct($info, $config) {
        assert('is_array($info)');
        assert('is_array($config)');

        /* Call the parent constructor first, as required by the interface. */
        parent::__construct($info, $config);
    }

    public static function _getJoomlaApp($type = "site", $howmanybacks = 4) {
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
        //  die (JPATH_BASE);
        define('_JEXEC', 1);
        require_once ( JPATH_BASE . DS . 'includes' . DS . 'defines.php' );
        require_once ( JPATH_BASE . DS . 'includes' . DS . 'framework.php' );

        $app = JFactory::getApplication($type);
        //die("tes2t");
        return $app;
    }

    /**
     * Start login.
     *
     * This function saves the information about the login, and redirects to the IdP.
     *
     * @param array &$state  Information about the current authentication.
     */
    public function authenticate(&$state) {
        assert('is_array($state)');
        $session = SimpleSAML_Session::getInstance();
        session_write_close();
        session_start();

        return self::authenticateJoomlaUser($state);
    }

    public static function authenticateJoomlaUser(&$state) {
        define('DS', DIRECTORY_SEPARATOR);

        $app = self::_getJoomlaApp('site', 8);

        // die(print_r($app,true));

        $juser = JFactory::getUser();

        if ($juser->guest) {
            $state['joomla:AuthID'] = "joomla-session";
            $stateId = SimpleSAML_Auth_State::saveState($state, 'joomla:Session');

            /*
             * Now we generate a URL the user should return to after authentication.
             * We assume that whatever authentication page we send the user to has an
             * option to return the user to a specific page afterwards.
             */
            $returnTo = SimpleSAML_Module::getModuleURL('joomla/resumessorequest.php', array(
                        'State' => $stateId,
            ));

            jimport('joomla.methods');
            $langHandle = "SAMLOGIN_AUTHN_REQUEST_IDP_MESSAGE";
            $usermess = JText::_($langHandle);
            if ($usermess == $langHandle) {
                $usermess = "";
            } else {
                $app->enqueueMessage($usermess);
            }
            // die($usermess);
            jimport('joomla.application.component.helper');
            $samloginParams = JComponentHelper::getParams('com_samlogin');
            /*
              $rediBase = $samloginParams->get('samlErrorRedirect', 0);
              if ($rediBase) {
              $link = 'index.php?Itemid=' . $rediBase;
              if (!empty($return)) {
              $link .= "&return=" . $return;
              }
              } else {
              $link = JURI::root();
              } */
            //   die($returnTo);
            jimport('joomla.methods');
            JFactory::getApplication()->setUserState('users.login.form.data', array("return" => $returnTo)); //good but the default joomla view seems to ignore it, it only want a POST
            JFactory::getSession()->close();
            $link = "index.php?option=com_users&view=login&return=" . base64_encode($returnTo);

//NO FIRST SALSH OR NOT ROUTED TO DEF MENU
            // echo $link; echo " ... routed: ";
            //   $link="index.php?option=com_users&view=login";
            $rediBase = JRoute::_($link);
//echo $rediBase; die();
            $rediURL = JRoute::_($rediBase, false);
            //die($rediURL);
            //  $suppressedNotices = ob_get_clean();
            // die(JURI::root());
            $app->redirect($rediURL . "#SAMLAuthNRequest");
            die("<a href='$rediURL'>click here if not automatically redirected</a>");
        } else {
            $state['Attributes']['email'][] = $juser->get("email");
            $state['Attributes']['eduPersonPrincipalName'][] = $juser->get("email");
            $fullName = $juser->get("name");
            $state['Attributes']['cn'][] = $fullName;
            //TODO: use the samlogin profile plugin as joomla only got fullname
            $firstName = $fullName;
            $lastName = $fullName;

            $nameparts = explode(" ", $fullName);
            if ($nameparts[0]) {
                $firstName = $nameparts[0];
            }
            if (isset($nameparts[1])) {
                $lastName = $nameparts[1];
            }

            //TODO: use the samlogin profile plugin
            $state['Attributes']['givenName'][] = $firstName;
            $state['Attributes']['sn'][] = $lastName;

            //tableau server
            $state['Attributes']['username'][] = $juser->get("username");

            //start emulate OneLogin attrs
            $state['Attributes']['User.Username'][] = $juser->get("email");
            $state['Attributes']['User.FirstName'][] = $firstName;
            $state['Attributes']['User.LastName'][] = $lastName;
            $state['Attributes']['User.email'][] = $juser->get("email");
            $groupNames = array();
            foreach ($juser->get("groups") as $groupId => $value) {
                $db = JFactory::getDbo();
                $db->setQuery(
                        'SELECT `title`' .
                        ' FROM `#__usergroups`' .
                        ' WHERE `id` = ' . (int) $groupId
                );
                $groupNames[] = $db->loadResult();
            }
            $userid = $juser->get("id");
            //$state['Attributes']["User.id"] = array($userid);
            $state['Attributes']['User.jGroupNames'] = $groupNames;
            $state['Attributes']['User.jGroupIDs'] = $juser->get("groups");
            try {

 
                $db->setQuery(
                        'SELECT `person_number`,`user_id`,`first_name`,`last_name`' .
                        ' FROM `bwp_user`' .
                        ' WHERE `jos_id` = ' . (int) $userid
                );
                $bwp_user = $db->loadAssoc();

		
                $db->setQuery(
                        'SELECT bwp_accountId'.
                        ' FROM `bwp_premises_accounts`' .
                        ' WHERE bwp_accountId IS NOT NULL 
						AND NOT (bwp_accountId = \'\')
						AND `user_id` = ' . (int) $bwp_user["user_id"]
                );

	//		echo $db->getQuery();
                $bwpAccountId = $db->loadColumn(0); 
				
          //      print_r($bwpAccountId); die();
				
                $state['Attributes']['givenName'] = array($bwp_user["first_name"]);
                $state['Attributes']['sn'] = array($bwp_user["last_name"]);;
                
                $state['Attributes']['personid'] = array($bwp_user["person_number"]);
                // $state['Attributes']['user_id']=array($bwp_user["user_id"]);
                $state['Attributes']['accountid'] = $bwpAccountId;

                //TODO configurable SQL mappings
                //$samloginParams->get("attrmapping_")
            } catch (Exception $noExtraSQL) {
                
            }
        }
        return $state;
    }

    /**
     * Resume authentication process.
     *
     * This function resumes the authentication process after the user has
     * entered his or her credentials.
     *
     * @param array &$state  The authentication state.
     */
    public static function resume() {

        /*
         * First we need to restore the $state-array. We should have the identifier for
         * it in the 'State' request parameter.
         */
        if (!isset($_REQUEST['State'])) {
            throw new SimpleSAML_Error_BadRequest('Missing "State" parameter.');
        }
        $stateId = (string) $_REQUEST['State'];

        // sanitize the input
        $sid = SimpleSAML_Utilities::parseStateID($stateId);
        if (!is_null($sid['url'])) {
            SimpleSAML_Utilities::checkURLAllowed($sid['url']);
        }

        /*
         * Once again, note the second parameter to the loadState function. This must
         * match the string we used in the saveState-call above.
         */
        $state = SimpleSAML_Auth_State::loadState($stateId, 'joomla:Session');

        if ($state['joomla:AuthID'] != "joomla-session") {
            die("state is corrupted");
        }


        //die("test");
        // $this->authenticate($state);
        self::authenticateJoomlaUser($state);
        SimpleSAML_Auth_Source::completeAuth($state);

        /*
         * The completeAuth-function never returns, so we never get this far.
         */
        assert('FALSE');
    }

    /**
     * This function is called when the user start a logout operation, for example
     * by logging out of a SP that supports single logout.
     *
     * @param array &$state  The logout state array.
     */
    public function logout(&$state) {
        assert('is_array($state)');
        $app = self::_getJoomlaApp('site', 8);
        //TODO: check if case to set no duplicate logout on bridged situation
        $app->logout();
        if (!session_id()) {
            /* session_start not called before. Do it here. */
            session_start();
        }

        /*
         * In this example we simply remove the 'uid' from the session.
         */
        unset($_SESSION['uid']);

        /*
         * If we need to do a redirect to a different page, we could do this
         * here, but in this example we don't need to do this.
         */
    }

}

?>