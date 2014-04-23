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
        $state[self::AUTHID] = $this->authId;

        define('DS', DIRECTORY_SEPARATOR);

        $joomla_base = dirname(__FILE__);

        for ($i = 0; $i < 8; $i++) {
            $joomla_base = substr($joomla_base, 0, strrpos($joomla_base, DS));
        }
        define('JPATH_BASE', $joomla_base);
        define('_JEXEC', 1);

        require_once(JPATH_BASE . DS . 'libraries' . DS . 'loader.php'); //autoloader di Joomla


        spl_autoload_register('__autoload');



        require_once ( JPATH_BASE . DS . 'includes' . DS . 'defines.php' );
        require_once ( JPATH_BASE . DS . 'includes' . DS . 'framework.php' );

        $mainframe = & JFactory::getApplication('site');

        $juser = & JFactory::getUser();


        if (isset($juser->email)) {
            require_once(JPATH_BASE . DS . 'components' . DS . 'com_idemauth' . DS . 'config' . DS . 'idemauth_config.inc.php');
            $state['Attributes']['email'][] = $idemauth_config['username2googleappsemail'][$juser->username];

            //   require(JPATH_BASE . DS . 'components' . DS . 'com_idemauth' . DS . 'simplesamlphp' . DS . 'lib' . DS . '_autoload.php');
            spl_autoload_register('SimpleSAML_autoload');
            $ssamlconfig = SimpleSAML_Configuration::getInstance();

            session_write_close();
            session_name("PHPSESSID");

//die(session_name());

            ini_set('session.save_handler', 'files');//TODO: support for detect simplesaml used handler: e.g. memchace

            ini_set('session.use_trans_sid', 0);
            session_start();
//die(session_id());
            SimpleSAML_Auth_Source::completeAuth($state);
        } else {
            require_once(JPATH_BASE . DS . 'components' . DS . 'com_idemauth' . DS . 'config' . DS . 'idemauth_config.inc.php');
            $joomla_base_url_orig = JURI::base();
            $joomla_base_url = $joomla_base_url_orig;
            for ($i = 0; $i < 7; $i++) {
                $joomla_base_url = substr($joomla_base_url, 0, strrpos($joomla_base_url, DS));
            }
            if (strlen($joomla_base_url) > 0) {

//die($joomla_base_url);
//header("location: ".$joomla_base_url);
                   setcookie( 'idp-joomla-simplesamlphp-url' ,SimpleSAML_Utilities::selfURL(), time() + 60 * 3 , '/' , '' , 0 ); //3 minutes
                SimpleSAML_Utilities::redirect($joomla_base_url);
            }else {
                SimpleSAML_Utilities::redirect($joomla_base_url_orig);
            }
        }
    }

}

?>
