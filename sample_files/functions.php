<?php

/**
 * @return bool
 */
function isLocalhost()
{
    return $_SERVER['SERVER_NAME'] == 'localhost';
}


/**
 * @return bool
 */
function isLive()
{
    return !isLocalhost();
}

function coalesce()
{
    foreach (func_get_args() as $arg) {
        if (!empty($arg) || $arg === 0 || $arg === '0') { // allow zero as string or int
            return $arg;
        }
    }
    return null;
}

function uuid()
{
    $data = random_bytes(16);
    assert(strlen($data) == 16);

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * returns a random image (full path) for banner page
 * @return string
 */
function getBannerImage()
{
    return "/assets/img/banner/" . rand(1, 7) . ".jpg";
}

