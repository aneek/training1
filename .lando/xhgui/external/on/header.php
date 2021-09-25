<?php

require_once __DIR__ . '/vendor/autoload.php';

use \Xhgui\Profiler\Profiler;
use \Xhgui\Profiler\ProfilingFlags;

putenv('XHGUI_MONGO_HOST=mongodb://xhgui:27017');

// Check which profile is enabled.
$extensions = get_loaded_extensions();
if (in_array(Profiler::PROFILER_TIDEWAYS_XHPROF, $extensions)) {
    $extension = Profiler::PROFILER_TIDEWAYS_XHPROF;
} elseif (in_array(Profiler::PROFILER_XHPROF, $extensions)) {
    $extension = Profiler::PROFILER_XHPROF;
}

$profiler = new Profiler([
    'profiler' => $extension,
    'profiler.flags' => array(
        ProfilingFlags::CPU,
        ProfilingFlags::MEMORY,
        ProfilingFlags::NO_BUILTINS,
        ProfilingFlags::NO_SPANS,
    ),
    'save.handler' => Profiler::SAVER_MONGODB,
    'save.handler.mongodb' => [
        'dsn' => getenv('XHGUI_MONGO_HOST') ?: 'mongodb://127.0.0.1:27017',
        'database' => 'xhprof',
        'options' => [],
        'driverOptions' => [],
    ],
    'profiler.options' => [],
    'profiler.enable' => function () {
        if (isset($_COOKIE['XDEBUG_PROFILE']) && !empty($_COOKIE['XDEBUG_PROFILE'])) {
          return true;
        }
        return false;
    },
]);

$profiler->start();
