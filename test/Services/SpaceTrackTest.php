<?php

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'Services/SpaceTrack.php';

class Services_SpaceTrackTest extends PHPUnit_Framework_TestCase
{
    public function testOptions()
    {
        $st = new Services_SpaceTrack(
            array(
                'username' => 'foo',
                'password' => 'bar',
            )
        );
        $this->assertSame('foo', $st->getOption('username'));
        $this->assertSame('bar', $st->getOption('password'));
        $this->assertSame(null, $st->getOption('nonexistant'));
    }

    public function testGetLatestTLE() {
        $st = $this->getMock(
            'Services_SpaceTrack',         
            array('sendRequest')
        );

        $st->expects($this->once())
            ->method('sendRequest')
            ->will($this->returnValue('tle-contents'));

        $this->assertSame('tle-contents', $st->getLatestTLE());
    }

    public function testSendRequestSuccess() {
        $st = $this->getMock(
            'Services_SpaceTrack',         
            array(
                'sendCurlRequest',
                'authenticate',
                'isAuthenticated',
                'getHTTPResponseCode'
            )
        );

        $st->expects($this->once())
            ->method('sendCurlRequest')
            ->will($this->returnValue('tle-contents'));
        $st->expects($this->once())
            ->method('authenticate')
            ->will($this->returnValue(null));
        $st->expects($this->once())
            ->method('isAuthenticated')
            ->will($this->returnValue(false));
        $st->expects($this->once())
            ->method('getHTTPResponseCode')
            ->will($this->returnValue(200));

        // Test setting verbose headers
        $st->setOption('debug', true);

        $this->assertSame('tle-contents', $st->getLatestTLE());
    }

    public function testSendRequestFailure() {
        $this->setExpectedException('Services_SpaceTrack_Exception');

        $st = $this->getMock(
            'Services_SpaceTrack',         
            array(
                'sendCurlRequest',
                'isAuthenticated',
                'getHTTPResponseCode'
            )
        );

        $st->expects($this->once())
            ->method('isAuthenticated')
            ->will($this->returnValue(true));
        $st->expects($this->once())
            ->method('getHTTPResponseCode')
            ->will($this->returnValue(500));
        $st->expects($this->once())
            ->method('sendCurlRequest');

        $st->getLatestTLE();
    }

    public function testIsAuthenticatedDefaultValue()
    {
        $st = new Services_SpaceTrack();
        $this->assertFalse($st->isAuthenticated());
    }

    public function testAuthenticateAlreadyAuthenticated()
    {
        $st = $this->getMock(
            'Services_SpaceTrack',         
            array(
                'isAuthenticated'
            )
        );

        $st->expects($this->once())
            ->method('isAuthenticated')
            ->will($this->returnValue(true));

        $this->assertTrue($st->authenticate());
    }

    public function testAuthenticateFailMissingUsernameOrPassword()
    {
        $this->setExpectedException('Services_SpaceTrack_Exception');

        $st = $this->getMock(
            'Services_SpaceTrack',         
            array(
                'isAuthenticated'
            )
        );

        $st->expects($this->once())
            ->method('isAuthenticated')
            ->will($this->returnValue(false));

        $this->assertTrue($st->authenticate());
    }

    public function testAuthenticateSuccess()
    {
        $st = $this->getMock(
            'Services_SpaceTrack',         
            array(
                'isAuthenticated',
                'sendCurlRequest',
                'getHTTPResponseCode'
            ),
            array(
                array('username' => 'foo', 'password' => 'bar', 'debug' => true)
            )
        );

        $st->expects($this->once())
            ->method('isAuthenticated')
            ->will($this->returnValue(false));
        $st->expects($this->once())
            ->method('sendCurlRequest')
            ->will($this->returnValue('sweet, dude'));
        $st->expects($this->once())
            ->method('getHTTPResponseCode')
            ->will($this->returnValue(200));

        $this->assertTrue($st->authenticate());
    }

    public function testAuthenticateFailure()
    {
        $this->setExpectedException('Services_SpaceTrack_Exception');

        $st = $this->getMock(
            'Services_SpaceTrack',         
            array(
                'isAuthenticated',
                'sendCurlRequest',
                'getHTTPResponseCode'
            ),
            array(
                array('username' => 'foo', 'password' => 'bar', 'debug' => true)
            )
        );

        $st->expects($this->once())
            ->method('isAuthenticated')
            ->will($this->returnValue(false));
        $st->expects($this->once())
            ->method('sendCurlRequest')
            ->will($this->returnValue('sweet, dude'));
        $st->expects($this->once())
            ->method('getHTTPResponseCode')
            ->will($this->returnValue(500));

        $st->authenticate();
    }

    public function testCleanUpUnlinkFile()
    {
        $tempFile = '/tmp/' . __CLASS__ . '-' . md5(microtime(true));
        touch($tempFile);
        $this->assertTrue(file_exists($tempFile));
        $st = new Services_SpaceTrack(array('cookieFile' => $tempFile));
        $st->cleanUp();
        $this->assertFalse(file_exists($tempFile));
    }
}
