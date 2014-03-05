<?php

/*
 * Services_SpaceTrack - a thin client for the space-track.org API
 *
 * Example usage:
 *
 * <code>
 * $st = new Services_SpaceTrack(
 *     array(
 *          'username' => 'myusername',
 *          'password' => 'mypassword'
 *     )
 * );
 *
 * try {
 *     $tle = $st->getLatestTLE('25544');
 * } catch (Services_SpaceTrack_Exception $e) {
 *     echo $e->getMessage();
 * }
 *
 * echo $tle;
 * </code>
 *
 */

require_once 'Services/SpaceTrack/Exception.php';

/**
 * Services_SpaceTrack 
 * 
 * @category  Services
 * @package   SpaceTrack
 * @author    Bill Shupp <hostmaster@shupp.org> 
 * @license   BSD
 * @link      http://github.com/shupp/Services_SpaceTrack
 */
class Services_SpaceTrack
{
    protected $authenticated = false;
    protected $options = array(
        'url'        => 'https://www.space-track.org/',
        'username'   => '',
        'password'   => '',
        'cookieFile' => null,
        'timeout'    => 5, // seconds
        'debug'      => false
    );

    /**
     * Constructor, allows for setting options
     * 
     * @param array $options Options to set
     * 
     * @return void
     */
    public function __construct(array $options = array())
    {
        foreach ($options as $name => $val) {
            $this->setOption($name, $val);
        }
    }

    /**
     * Sets an option
     * 
     * @param string $name The option name
     * @param mixed  $val  The option value
     * 
     * @return void
     */
    public function setOption($name, $val)
    {
        // Probably should do some validation
        $this->options[$name] = $val;
    }

    /**
     * Getter for $this->authenticated.  Abstracted for testing.
     * 
     * @return bool
     */
    public function isAuthenticated()
    {
        return $this->authenticated;
    }

    /**
     * Get an option
     * 
     * @param string $name The option name
     * 
     * @return mixed on success, null on failure
     */
    public function getOption($name)
    {
        // Probably should do some validation
        if (isset($this->options[$name])) {
            return $this->options[$name];
        }
        return null;
    }

    /**
     * Shortcut for getting the latest TLE contents
     * 
     * @param int $noradID The NORAD ID of the satellite
     * 
     * @return string The TLE contents
     */
    public function getLatestTLE($noradID = 25544)
    {
        return $this->sendRequest(
            '/basicspacedata/query/class/tle/format/tle/NORAD_CAT_ID/'
            . $noradID . '/orderby/EPOCH%20desc/limit/1'
        );
    }

    /**
     * Sends a GET request to space-track.org.  Requires authenticate() to have
     * been run first.
     * 
     * @param string $path The URI path
     * 
     * @return string The response contents
     * @throws Services_SpaceTrack_Exception on failure
     */
    public function sendRequest($path)
    {
        if (!$this->isAuthenticated()) {
            $this->authenticate();
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->options['cookieFile']);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->options['cookieFile']);
        curl_setopt($ch, CURLOPT_URL, $this->options['url'] . $path);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->options['timeout']);

        if ($this->options['debug']) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
        }

        ob_start();
        $result = $this->sendCurlRequest($ch);
        ob_end_clean();

        // Check response code
        $code = $this->getHTTPResponseCode($ch);
        if (strncmp($code, '2', 1)) {
            $this->authenticated = false;
            $this->cleanUp();
            throw new Services_SpaceTrack_Exception(
                "Unexpected response code: $code:\n" . $result,
                $code
            );
        }

        return $result;
    }

    /**
     * Sends an authentication request and stores the cookie for future requests.
     * 
     * @return true on success or already authenticated
     * @throws Services_SpaceTrack_Exception on error
     */
    public function authenticate()
    {
        if ($this->isAuthenticated()) {
            return true;
        }

        if (!strlen($this->options['username'])
            || !strlen($this->options['password'])) {

            $this->cleanUp();
            throw new Services_SpaceTrack_Exception(
                'You must supply a username and a password'
            );
        }

        if ($this->options['cookieFile'] === null) {
            $rand = md5(microtime(true));
            $this->options['cookieFile'] = '/tmp/Services_SpaceTrack-cookiefile-' . $rand;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->options['cookieFile']);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->options['cookieFile']);
        curl_setopt($ch, CURLOPT_URL, $this->options['url'] . '/ajaxauth/login');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->options['timeout']);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            'identity=' . $this->options['username']
            . '&password=' . $this->options['password']
        );

        if ($this->options['debug']) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
        }

        ob_start();
        $result = $this->sendCurlRequest($ch);
        ob_end_clean();

        // Check response code
        $code = $this->getHTTPResponseCode($ch);
        if (strncmp($code, '2', 1)) {
            $this->cleanUp(); // Clean up on auth failures
            throw new Services_SpaceTrack_Exception(
                "Unable to authenticate, received $code:\n" . $result,
                $code
            );
        }

        $this->authenticated = true;

        curl_close($ch);

        return true;
    }

    /**
     * Remove cookie file on exit for security
     * 
     * @return void
     */
    public function cleanUp()
    {
        // Remove cookie file
        if (strlen($this->options['cookieFile'])
            && is_writeable($this->options['cookieFile'])) {

            unlink($this->options['cookieFile']);
        }
    }

    /**
     * Call curl_exec on the curl resource.  Abstracted for testing.
     * 
     * @param resource $ch The curl resource
     * 
     * @codeCoverageIgnore
     * @return string The response content (using RETURNTRANSFER)
     */
    protected function sendCurlRequest($ch)
    {
        return curl_exec($ch);
    }

    /**
     * Get the http respons code form the curl resource.  Abstracted for testing.
     * 
     * @param resource $ch The curl resource
     * 
     * @codeCoverageIgnore
     * @return int The response code
     */
    protected function getHTTPResponseCode($ch)
    {
        return curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }

    /**
     * Clean up cookie file on exit
     * 
     * @return void
     */
    public function __destruct()
    {
        $this->cleanUp();
    }
}

?>
