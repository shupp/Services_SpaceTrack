### Overview

**Services_SpaceTrack** is a very thin API client for http://space-track.org.
What it really provides is a simple sendRequest() method and automation of
authenticating.

#### Example usage

Example usage for getting the latest TLE for the ISS:

```
<?php

require_once 'Services/SpaceTrack.php';

$st = new Services_SpaceTrack(
    array(
         'username' => 'myusername',
         'password' => 'mypassword'
    )
);

try {
    $tle = $st->getLatestTLE('25544');
} catch (Services_SpaceTrack_Exception $e) {
    echo $e->getMessage();
}

echo $tle;
```

#### Running tests

You'll need phpunit installed, and Xdebug if you want code coverage.  Coverage
report will be in test/coverage.

```
cd test && phpunit .
```
