<?php
// TEMPORARY DIAGNOSTIC — delete immediately after reading the output.
// Boots Laravel itself (in-process) and asks its own Router object
// what's actually registered, instead of guessing from the outside.

require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Boot the kernel so all service providers (including route
// registration) actually run, without dispatching a real request.
$kernel->bootstrap();

$router = $app->make('router');
$routes = $router->getRoutes();

echo '<pre style="font-family: monospace; white-space: pre-wrap;">';
echo "App environment: " . $app->environment() . "\n";
echo "Routes are cached: " . ($app->routesAreCached() ? 'YES' : 'NO') . "\n";
if ($app->routesAreCached()) {
    echo "Route cache file: " . $app->getCachedRoutesPath() . "\n";
    echo "Route cache exists on disk: " . (file_exists($app->getCachedRoutesPath()) ? 'YES' : 'NO') . "\n";
    if (file_exists($app->getCachedRoutesPath())) {
        echo "Route cache last modified: " . date('Y-m-d H:i:s', filemtime($app->getCachedRoutesPath())) . "\n";
    }
}
echo "Total routes registered: " . count($routes) . "\n\n";

echo "--- Searching for our target routes ---\n\n";

$needles = ['ping-test-123', 'responder/incidents', 'resolve', 'accept', 'decline'];

$found = false;
foreach ($routes as $route) {
    $uri = $route->uri();
    foreach ($needles as $needle) {
        if (str_contains($uri, $needle)) {
            $found = true;
            echo implode('|', $route->methods()) . "  " . $uri . "\n";
            break;
        }
    }
}

if (!$found) {
    echo "NONE of the target routes were found in the live Router object.\n";
    echo "This means Laravel itself never registered them — even though\n";
    echo "the file on disk is correct. Likely cause: a DIFFERENT\n";
    echo "bootstrap/app.php or vendor/ is actually being loaded (a stale\n";
    echo "deployment, a symlink pointing elsewhere, or a second Laravel\n";
    echo "install), or route caching is picking up an old compiled file\n";
    echo "from a path this script isn't checking.\n";
}

echo "\n--- ALL registered routes (first 60) ---\n\n";
$i = 0;
foreach ($routes as $route) {
    if ($i++ >= 60) {
        echo "... (" . (count($routes) - 60) . " more)\n";
        break;
    }
    echo implode('|', $route->methods()) . "  " . $route->uri() . "\n";
}

echo '</pre>';