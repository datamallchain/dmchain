<?php
namespace Shaarli\Security;

require_once 'tests/utils/FakeConfigManager.php';

use PHPUnit\Framework\TestCase;

/**
 * Test coverage for LoginManager
 */
class LoginManagerTest extends TestCase
{
    /** @var \FakeConfigManager Configuration Manager instance */
    protected $configManager = null;

    /** @var LoginManager Login Manager instance */
    protected $loginManager = null;

    /** @var SessionManager Session Manager instance */
    protected $sessionManager = null;

    /** @var string Banned IP filename */
    protected $banFile = 'sandbox/ipbans.php';

    /** @var string Log filename */
    protected $logFile = 'sandbox/shaarli.log';

    /** @var array Simulates the $_COOKIE array */
    protected $cookie = [];

    /** @var array Simulates the $GLOBALS array */
    protected $globals = [];

    /** @var array Simulates the $_SERVER array */
    protected $server = [];

    /** @var array Simulates the $_SESSION array */
    protected $session = [];

    /** @var string Advertised client IP address */
    protected $clientIpAddress = '10.1.47.179';

    /** @var string Local client IP address */
    protected $ipAddr = '127.0.0.1';

    /** @var string Trusted proxy IP address */
    protected $trustedProxy = '10.1.1.100';

    /** @var string User login */
    protected $login = 'johndoe';

    /** @var string User password */
    protected $password = 'IC4nHazL0g1n?';

    /** @var string Hash of the salted user password */
    protected $passwordHash = '';

    /** @var string Salt used by hash functions */
    protected $salt = '669e24fa9c5a59a613f98e8e38327384504a4af2';

    /**
     * Prepare or reset test resources
     */
    public function setUp()
    {
        if (file_exists($this->banFile)) {
            unlink($this->banFile);
        }

        $this->passwordHash = sha1($this->password . $this->login . $this->salt);

        $this->configManager = new \FakeConfigManager([
            'credentials.login' => $this->login,
            'credentials.hash' => $this->passwordHash,
            'credentials.salt' => $this->salt,
            'resource.ban_file' => $this->banFile,
            'resource.log' => $this->logFile,
            'security.ban_after' => 4,
            'security.ban_duration' => 3600,
            'security.trusted_proxies' => [$this->trustedProxy],
        ]);

        $this->cookie = [];

        $this->globals = &$GLOBALS;
        unset($this->globals['IPBANS']);

        $this->session = [];

        $this->sessionManager = new SessionManager($this->session, $this->configManager);
        $this->loginManager = new LoginManager($this->globals, $this->configManager, $this->sessionManager);
        $this->server['REMOTE_ADDR'] = $this->ipAddr;
    }

    /**
     * Wipe test resources
     */
    public function tearDown()
    {
        unset($this->globals['IPBANS']);
    }

    /**
     * Instantiate a LoginManager and load ban records
     */
    public function testReadBanFile()
    {
        file_put_contents(
            $this->banFile,
            "<?php\n\$GLOBALS['IPBANS']=array('FAILURES' => array('127.0.0.1' => 99));\n?>"
        );
        new LoginManager($this->globals, $this->configManager, null);
        $this->assertEquals(99, $this->globals['IPBANS']['FAILURES']['127.0.0.1']);
    }

    /**
     * Record a failed login attempt
     */
    public function testHandleFailedLogin()
    {
        $this->loginManager->handleFailedLogin($this->server);
        $this->assertEquals(1, $this->globals['IPBANS']['FAILURES'][$this->ipAddr]);

        $this->loginManager->handleFailedLogin($this->server);
        $this->assertEquals(2, $this->globals['IPBANS']['FAILURES'][$this->ipAddr]);
    }

    /**
     * Record a failed login attempt - IP behind a trusted proxy
     */
    public function testHandleFailedLoginBehindTrustedProxy()
    {
        $server = [
            'REMOTE_ADDR' => $this->trustedProxy,
            'HTTP_X_FORWARDED_FOR' => $this->ipAddr,
        ];
        $this->loginManager->handleFailedLogin($server);
        $this->assertEquals(1, $this->globals['IPBANS']['FAILURES'][$this->ipAddr]);

        $this->loginManager->handleFailedLogin($server);
        $this->assertEquals(2, $this->globals['IPBANS']['FAILURES'][$this->ipAddr]);
    }

    /**
     * Record a failed login attempt - IP behind a trusted proxy but not forwarded
     */
    public function testHandleFailedLoginBehindTrustedProxyNoIp()
    {
        $server = [
            'REMOTE_ADDR' => $this->trustedProxy,
        ];
        $this->loginManager->handleFailedLogin($server);
        $this->assertFalse(isset($this->globals['IPBANS']['FAILURES'][$this->ipAddr]));

        $this->loginManager->handleFailedLogin($server);
        $this->assertFalse(isset($this->globals['IPBANS']['FAILURES'][$this->ipAddr]));
    }

    /**
     * Record a failed login attempt and ban the IP after too many failures
     */
    public function testHandleFailedLoginBanIp()
    {
        $this->loginManager->handleFailedLogin($this->server);
        $this->assertEquals(1, $this->globals['IPBANS']['FAILURES'][$this->ipAddr]);
        $this->assertTrue($this->loginManager->canLogin($this->server));

        $this->loginManager->handleFailedLogin($this->server);
        $this->assertEquals(2, $this->globals['IPBANS']['FAILURES'][$this->ipAddr]);
        $this->assertTrue($this->loginManager->canLogin($this->server));

        $this->loginManager->handleFailedLogin($this->server);
        $this->assertEquals(3, $this->globals['IPBANS']['FAILURES'][$this->ipAddr]);
        $this->assertTrue($this->loginManager->canLogin($this->server));

        $this->loginManager->handleFailedLogin($this->server);
        $this->assertEquals(4, $this->globals['IPBANS']['FAILURES'][$this->ipAddr]);
        $this->assertFalse($this->loginManager->canLogin($this->server));

        // handleFailedLogin is not supposed to be called at this point:
        // - no login form should be displayed once an IP has been banned
        // - yet this could happen when using custom templates / scripts
        $this->loginManager->handleFailedLogin($this->server);
        $this->assertEquals(5, $this->globals['IPBANS']['FAILURES'][$this->ipAddr]);
        $this->assertFalse($this->loginManager->canLogin($this->server));
    }

    /**
     * Nothing to do
     */
    public function testHandleSuccessfulLogin()
    {
        $this->assertTrue($this->loginManager->canLogin($this->server));

        $this->loginManager->handleSuccessfulLogin($this->server);
        $this->assertTrue($this->loginManager->canLogin($this->server));
    }

    /**
     * Erase failure records after successfully logging in from this IP
     */
    public function testHandleSuccessfulLoginAfterFailure()
    {
        $this->loginManager->handleFailedLogin($this->server);
        $this->loginManager->handleFailedLogin($this->server);
        $this->assertEquals(2, $this->globals['IPBANS']['FAILURES'][$this->ipAddr]);
        $this->assertTrue($this->loginManager->canLogin($this->server));

        $this->loginManager->handleSuccessfulLogin($this->server);
        $this->assertTrue($this->loginManager->canLogin($this->server));
        $this->assertFalse(isset($this->globals['IPBANS']['FAILURES'][$this->ipAddr]));
        $this->assertFalse(isset($this->globals['IPBANS']['BANS'][$this->ipAddr]));
    }

    /**
     * The IP is not banned
     */
    public function testCanLoginIpNotBanned()
    {
        $this->assertTrue($this->loginManager->canLogin($this->server));
    }

    /**
     * The IP is banned
     */
    public function testCanLoginIpBanned()
    {
        // ban the IP for an hour
        $this->globals['IPBANS']['FAILURES'][$this->ipAddr] = 10;
        $this->globals['IPBANS']['BANS'][$this->ipAddr] = time() + 3600;

        $this->assertFalse($this->loginManager->canLogin($this->server));
    }

    /**
     * The IP is banned, and the ban duration is over
     */
    public function testCanLoginIpBanExpired()
    {
        // ban the IP for an hour
        $this->globals['IPBANS']['FAILURES'][$this->ipAddr] = 10;
        $this->globals['IPBANS']['BANS'][$this->ipAddr] = time() + 3600;
        $this->assertFalse($this->loginManager->canLogin($this->server));

        // lift the ban
        $this->globals['IPBANS']['BANS'][$this->ipAddr] = time() - 3600;
        $this->assertTrue($this->loginManager->canLogin($this->server));
    }

    /**
     * Generate a token depending on the user credentials and client IP
     */
    public function testGenerateStaySignedInToken()
    {
        $this->loginManager->generateStaySignedInToken($this->clientIpAddress);

        $this->assertEquals(
            sha1($this->passwordHash . $this->clientIpAddress . $this->salt),
            $this->loginManager->getStaySignedInToken()
        );
    }

    /**
     * Generate a token depending on the user credentials with session protected disabled
     */
    public function testGenerateStaySignedInTokenSessionProtectionDisabled()
    {
        $this->configManager->set('security.session_protection_disabled', true);
        $this->loginManager->generateStaySignedInToken($this->clientIpAddress);

        $this->assertEquals(
            sha1($this->passwordHash . $this->salt),
            $this->loginManager->getStaySignedInToken()
        );
    }

    /**
     * Check user login - Shaarli has not yet been configured
     */
    public function testCheckLoginStateNotConfigured()
    {
        $configManager = new \FakeConfigManager([
            'resource.ban_file' => $this->banFile,
        ]);
        $loginManager = new LoginManager($this->globals, $configManager, null);
        $loginManager->checkLoginState([], '');

        $this->assertFalse($loginManager->isLoggedIn());
    }

    /**
     * Check user login - the client cookie does not match the server token
     */
    public function testCheckLoginStateStaySignedInWithInvalidToken()
    {
        // simulate a previous login
        $this->session = [
            'ip' => $this->clientIpAddress,
            'expires_on' => time() + 100,
        ];
        $this->loginManager->generateStaySignedInToken($this->clientIpAddress);
        $this->cookie[LoginManager::$STAY_SIGNED_IN_COOKIE] = 'nope';

        $this->loginManager->checkLoginState($this->cookie, $this->clientIpAddress);

        $this->assertTrue($this->loginManager->isLoggedIn());
        $this->assertTrue(empty($this->session['username']));
    }

    /**
     * Check user login - the client cookie matches the server token
     */
    public function testCheckLoginStateStaySignedInWithValidToken()
    {
        $this->loginManager->generateStaySignedInToken($this->clientIpAddress);
        $this->cookie[LoginManager::$STAY_SIGNED_IN_COOKIE] = $this->loginManager->getStaySignedInToken();

        $this->loginManager->checkLoginState($this->cookie, $this->clientIpAddress);

        $this->assertTrue($this->loginManager->isLoggedIn());
        $this->assertEquals($this->login, $this->session['username']);
        $this->assertEquals($this->clientIpAddress, $this->session['ip']);
    }

    /**
     * Check user login - the session has expired
     */
    public function testCheckLoginStateSessionExpired()
    {
        $this->loginManager->generateStaySignedInToken($this->clientIpAddress);
        $this->session['expires_on'] = time() - 100;

        $this->loginManager->checkLoginState($this->cookie, $this->clientIpAddress);

        $this->assertFalse($this->loginManager->isLoggedIn());
    }

    /**
     * Check user login - the remote client IP has changed
     */
    public function testCheckLoginStateClientIpChanged()
    {
        $this->loginManager->generateStaySignedInToken($this->clientIpAddress);

        $this->loginManager->checkLoginState($this->cookie, '10.7.157.98');

        $this->assertFalse($this->loginManager->isLoggedIn());
    }

    /**
     * Check user credentials - wrong login supplied
     */
    public function testCheckCredentialsWrongLogin()
    {
        $this->assertFalse(
            $this->loginManager->checkCredentials('', '', 'b4dl0g1n', $this->password)
        );
    }

    /**
     * Check user credentials - wrong password supplied
     */
    public function testCheckCredentialsWrongPassword()
    {
        $this->assertFalse(
            $this->loginManager->checkCredentials('', '', $this->login, 'b4dp455wd')
        );
    }

    /**
     * Check user credentials - wrong login and password supplied
     */
    public function testCheckCredentialsWrongLoginAndPassword()
    {
        $this->assertFalse(
            $this->loginManager->checkCredentials('', '', 'b4dl0g1n', 'b4dp455wd')
        );
    }

    /**
     * Check user credentials - correct login and password supplied
     */
    public function testCheckCredentialsGoodLoginAndPassword()
    {
        $this->assertTrue(
            $this->loginManager->checkCredentials('', '', $this->login, $this->password)
        );
    }
}
