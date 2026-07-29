<?php

use PHPUnit\TextUI\Application;

if (PHP_SAPI !== 'phpdbg') {
    fwrite(STDERR, "Run coverage with PHPDBG.\n");
    exit(2);
}

require dirname(__DIR__) . '/vendor/autoload.php';

phpdbg_start_oplog();
$testExitCode = (new Application())->run([
    'phpunit',
    '--configuration',
    dirname(__DIR__) . '/phpunit.xml',
    '--do-not-cache-result',
]);
$oplog = phpdbg_end_oplog();

$executable = phpdbg_get_executable();
$targets    = [
    BitApps\WPKit\Http\Router\RouteRegister::class => [
        'handleMiddleware',
        'runMiddlewares',
        'handleRequest',
        'setRequest',
        'authorize',
        'validate',
        'handleAction',
        'invokeAsReflectionFunction',
        'processParameters',
        'invokeAsReflection',
        'block',
    ],
    BitApps\WPKit\Http\Router\Router::class => [
        'setMiddlewares',
        'getRegisteredMiddleware',
    ],
    BitApps\WPKit\Http\Router\MiddlewareRegistry::class => [
        'register',
        'resolve',
    ],
    BitApps\WPKit\Http\Request\Request::class => [
        'ip',
        'setTrustedProxies',
    ],
    BitApps\WPKit\Http\Detection\ClientIpResolver::class => [
        'setTrustedProxies',
        'checkIP',
        'normalizeIP',
        'isTrustedProxy',
        'isIpInRange',
    ],
    BitApps\WPKit\Http\Client\HttpClient::class => [
        'allowUnsafeUrls',
        'request',
        'setDefault',
    ],
    BitApps\WPKit\Http\Response::class => [
        'reset',
        'headers',
        'header',
    ],
];

$coveredLineCount    = 0;
$executableLineCount = 0;
$uncovered           = [];

foreach ($targets as $class => $methods) {
    $reflection = new ReflectionClass($class);
    foreach ($methods as $methodName) {
        $method                = $reflection->getMethod($methodName);
        $file                  = realpath($method->getFileName());
        $methodExecutableLines = [];

        foreach (array_keys($executable[$file] ?? []) as $line) {
            if ($line >= $method->getStartLine() && $line <= $method->getEndLine()) {
                $methodExecutableLines[] = $line;
            }
        }

        $methodUncoveredLines = array_values(
            array_filter($methodExecutableLines, static function ($line) use ($oplog, $file) {
                return !isset($oplog[$file][$line]);
            }),
        );

        $methodExecutableCount = count($methodExecutableLines);
        $methodCoveredCount    = $methodExecutableCount - count($methodUncoveredLines);
        $executableLineCount += $methodExecutableCount;
        $coveredLineCount    += $methodCoveredCount;

        if (!empty($methodUncoveredLines)) {
            $uncovered["{$class}::{$methodName}"] = $methodUncoveredLines;
        }
    }
}

$coverage = $executableLineCount === 0
    ? 0
    : ($coveredLineCount / $executableLineCount) * 100;

echo sprintf(
    "\nSelected HTTP coverage: %.2f%% (%d/%d executable lines)\n",
    $coverage,
    $coveredLineCount,
    $executableLineCount,
);

foreach ($uncovered as $method => $lines) {
    echo $method . ': uncovered lines ' . implode(', ', $lines) . "\n";
}

exit($testExitCode === 0 && $coveredLineCount === $executableLineCount ? 0 : 1);
