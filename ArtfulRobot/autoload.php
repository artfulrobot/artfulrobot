<?php
/**
 * @file sets up an spl_autoload function to load ArtfulRobot libraries.
 */
spl_autoload_register( function ($className)
{
    // we only work for ArtfulRobot
    if (!preg_match('@^\\\\?ArtfulRobot\\\\(.+)$@', $className, $matches)) {
        return;
    }

    $fileName = dirname(__FILE__) . DIRECTORY_SEPARATOR
                . strtr($matches[1],array('_'=> DIRECTORY_SEPARATOR, '\\' => DIRECTORY_SEPARATOR)) . '.php';

    if (!file_exists($fileName)) {
        error_log("ArtfulRobot autoload: File for class '$className' not found at $fileName");
        return;
    }
    require $fileName;
});
