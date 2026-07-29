<?php

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src'])
    ->withPhpVersion(PhpVersion::PHP_80)
    ->withPreparedSets(typeDeclarations: true)
    ->withPhpSets(php80: true)
    // BC-frozen extension points: consumers extend Request / use IpTool and call Arr
    // with coerced args, so their public signatures must stay untyped (overrides + param coercion).
    ->withSkipPath(__DIR__ . '/src/Http/Request/Request.php')
    ->withSkipPath(__DIR__ . '/src/Http/IpTool.php')
    ->withSkipPath(__DIR__ . '/src/Helpers/Arr.php');
