<?php
namespace Shaarli\Security;

use Exception;
use Shaarli\Config\ConfigManager;

/**
 * User login management
 */
class LoginManager
{
    /** @var array A reference to the $_GLOBALS array */
    protected $globals = [];

    /** @var ConfigManager Configuration Manager instance **/
    protected $configManager = null;

    /** @var SessionManager Session Manager instance **/
    protected $sessionManager = null;

    /** @var BanManager Ban Manager instance **/
    protected $banManager;

    /** @var bool Whether the user is logged in **/
    protected $isLoggedIn = false;

    /** @var bool Whether the Shaarli instance is open to public edition **/
    protected $openShaarli = false;

    /** @var string User sign-in token depending on remote IP and credentials */
    protected $staySignedInToken = '';
    /** @var CookieManager */
    protected $cookieManager;

    /**
     * Constructor
     *
     * @param ConfigManager  $configManager  Configuration Manager instance
     * @param SessionManager $sessionManager SessionManager instance
     * @param CookieManager  $cookieManager  CookieManager instance
     */
    public function __construct($configManager, $sessionManager, $cookieManager)
    {
        $this->configManager = $configManager;
        $this->sessionManager = $sessionManager;
        $this->cookieManager = $cookieManager;
        $this->banManager = new BanManager(
            $this->configManager->get('security.trusted_proxies', []),
            $this->configManager->get('security.ban_after'),
            $this->configManager->get('security.ban_duration'),
            $this->configManager->get('resource.ban_file', 'data/ipbans.php'),
            $this->configManager->get('resource.log')
        );

        if ($this->configManager->get('security.open_shaarli') === true) {
            $this->openShaarli = true;
        }
    }

    /**
     * Generate a token depending on deployment salt, user password and client IP
     *
     * @param string $clientIpAddress The remote client IP address
     */
    public function generateStaySignedInToken($clientIpAddress)
    {
        if ($this->configManager->get('security.session_protection_disabled') === true) {
            $clientIpAddress = '';
        }
        $this->staySignedInToken = sha1(
            $this->configManager->get('credentials.hash')
            . $clientIpAddress
            . $this->configManager->get('credentials.salt')
        );
    }

    /**
     * Return the user's client stay-signed-in token
     *
     * @return string User's client stay-signed-in token
     */
    public function getStaySignedInToken()
    {
        return $this->staySignedInToken;
    }

    /**
     * Check user session state and validity (expiration)
     *
     * @param string $clientIpId Client IP address identifier
     */
    public function checkLoginState($clientIpId)
    {
        if (! $this->configManager->exists('credentials.login')) {
            // Shaarli is not configured yet
            $this->isLoggedIn = false;
            return;
        }

        if ($this->staySignedInToken === $this->cookieManager->getCookieParameter(CookieManager::STAY_SIGNED_IN)) {
            // The user client has a valid stay-signed-in cookie
            // Session information is updated with the current client information
            $this->sessionManager->storeLoginInfo($clientIpId);
        } elseif ($this->sessionManager->hasSessionExpired()
            || $this->sessionManager->hasClientIpChanged($clientIpId)
        ) {
            $this->sessionManager->logout();
            $this->isLoggedIn = false;
            return;
        }

        $this->isLoggedIn = true;
        $this->sessionManager->extendSession();
    }

    /**
     * Return whether the user is currently logged in
     *
     * @return true when the user is logged in, false otherwise
     */
    public function isLoggedIn()
    {
        if ($this->openShaarli) {
            return true;
        }
        return $this->isLoggedIn;
    }

    /**
     * Check user credentials are valid
     *
     * @param string $remoteIp   Remote client IP address
     * @param string $clientIpId Client IP address identifier
     * @param string $login      Username
     * @param string $password   Password
     *
     * @return bool true if the provided credentials are valid, false otherwise
     */
    public function checkCredentials($remoteIp, $clientIpId, $login, $password)
    {
        // Check login matches config
        if ($login !== $this->configManager->get('credentials.login')) {
            return false;
        }

        // Check credentials
        try {
            $useLdapLogin = !empty($this->configManager->get('ldap.host'));
            if ((false === $useLdapLogin && $this->checkCredentialsFromLocalConfig($login, $password))
                || (true === $useLdapLogin && $this->checkCredentialsFromLdap($login, $password))
            ) {
                    $this->sessionManager->storeLoginInfo($clientIpId);
                    logm(
                        $this->configManager->get('resource.log'),
                        $remoteIp,
                        'Login successful'
                    );
                    return true;
            }
        }
        catch(Exception $exception) {
            logm(
                $this->configManager->get('resource.log'),
                $remoteIp,
                'Exception while checking credentials: ' . $exception
            );
        }

        logm(
            $this->configManager->get('resource.log'),
            $remoteIp,
            'Login failed for user ' . $login
        );
        return false;
    }


    /**
     * Check user credentials from local config
     *
     * @param string $login      Username
     * @param string $password   Password
     *
     * @return bool true if the provided credentials are valid, false otherwise
     */
    public function checkCredentialsFromLocalConfig($login, $password) {
        $hash = sha1($password . $login . $this->configManager->get('credentials.salt'));

        return $login == $this->configManager->get('credentials.login')
             && $hash == $this->configManager->get('credentials.hash');
    }

    /**
     * Check user credentials are valid through LDAP bind
     *
     * @param string $remoteIp   Remote client IP address
     * @param string $clientIpId Client IP address identifier
     * @param string $login      Username
     * @param string $password   Password
     *
     * @return bool true if the provided credentials are valid, false otherwise
     */
    public function checkCredentialsFromLdap($login, $password, $connect = null, $bind = null)
    {
        $connect = $connect ?? function($host) {
            $resource = ldap_connect($host);

            ldap_set_option($resource, LDAP_OPT_PROTOCOL_VERSION, 3);

            return $resource;
        };
        $bind = $bind ?? function($handle, $dn, $password) {
            return ldap_bind($handle, $dn, $password);
        };

        return $bind(
            $connect($this->configManager->get('ldap.host')),
            sprintf($this->configManager->get('ldap.dn'), $login),
            $password
        );
    }

    /**
     * Handle a failed login and ban the IP after too many failed attempts
     *
     * @param array $server The $_SERVER array
     */
    public function handleFailedLogin($server)
    {
        $this->banManager->handleFailedAttempt($server);
    }

    /**
     * Handle a successful login
     *
     * @param array $server The $_SERVER array
     */
    public function handleSuccessfulLogin($server)
    {
        $this->banManager->clearFailures($server);
    }

    /**
     * Check if the user can login from this IP
     *
     * @param array $server The $_SERVER array
     *
     * @return bool true if the user is allowed to login
     */
    public function canLogin($server)
    {
        return ! $this->banManager->isBanned($server);
    }
}
