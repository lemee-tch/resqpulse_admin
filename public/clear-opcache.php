<?php
// TEMPORARY — delete this file immediately after using it once.
// Leaving a script like this reachable is a security risk long-term.

if (function_exists('opcache_reset')) {
    $cleared = opcache_reset();
    echo $cleared ? 'OPcache cleared successfully.' : 'opcache_reset() ran but returned false — OPcache may be disabled for CLI/this SAPI.';
} else {
    echo 'opcache_reset() is not available — OPcache may not be enabled on this server, which would mean it is NOT the cause here.';
}

echo '<br><br>Now delete this file.';
