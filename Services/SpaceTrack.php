<?php

/*
 * Example usage
 *
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
 *
 */

require_once 'Services/SpaceTrack/Exception.php';

class Services_SpaceTrack
{
    protected $isAuthenticated = false;
    protected $options = array(
        'url'        => 'https://www.space-track.org/',
        'username'   => '',
        'password'   => '',
        'cookieFile' => null,
        'timeout'    => 5, // seconds
        'debug'      => false
    );

    public function __construct(array $options = array())
    {
        foreach ($options as $name => $val) {
            $this->setOption($name, $val);
        }
    }

    public function setOption($name, $val)
    {
        // Probably should do some validation
        $this->options[$name] = $val;
    }

    public function getLatestTLE($noradID = 25544)
    {
        return $this->sendRequest(
            '/basicspacedata/query/class/tle/format/tle/NORAD_CAT_ID/'
            . $noradID . '/orderby/EPOCH%20desc/limit/1'
        );
    }

    public function sendRequest($path)
    {
        if (!$this->isAuthenticated) {
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
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (strncmp($code, '2', 1)) {
            // $this->cleanUp();
            throw new Services_SpaceTrack_Exception(
                "Unexpected response code: $code:\n" . $result,
                $code
            );
        }

        return $result;
    }

    public function authenticate()
    {
        if ($this->isAuthenticated) {
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
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (strncmp($code, '2', 1)) {
            $this->cleanUp();
            throw new Services_SpaceTrack_Exception(
                "Unable to authenticate, received $code:\n" . $result,
                $code
            );
        }

        $this->isAuthenticated = true;

        curl_close($ch);
    }

    protected function sendCurlRequest($ch)
    {
        return curl_exec($ch);
    }

    protected function cleanUp()
    {
        // Remove cookie file
        if (strlen($this->options['cookieFile'])
            && is_writeable($this->options['cookieFile'])) {

            unlink($this->options['cookieFile']);
        }
    }

    public function __destruct()
    {
        $this->cleanUp();
    }
}

?>
