<?php
/**
 * Get file extension
 * @param $file string
 * @return string
 */
function getFileExtension($file)
{
    return substr($file, strrpos($file, '.'));
}


function generateToken()
{
    $ip = $_SERVER['REMOTE_ADDR'];
    $time = microtime(true);
    $rand = mt_rand(10000, 99999999);

    $str = sha1($ip) . md5($rand) . sha1($time);
    $str = mb_substr($str, 0, 100);
}

function unquote($str)
{
    $str = str_replace('"', '', $str);
    $str = str_replace("'", '', $str);
    return $str;
}

function sqlGetOne($sql)
{
    global $_MYSQL;
    $res = $_MYSQL->query($sql);
    if (!$res)
        return false;
    $r = $res->fetch_array(MYSQLI_NUM);
    return $r[0];
}

function url_origin($s, $use_forwarded_host = false)
{
    $ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on');
    $sp = strtolower($s['SERVER_PROTOCOL']);
    $protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
    $port = $s['SERVER_PORT'];
    $port = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':' . $port;
    $host = ($use_forwarded_host && isset($s['HTTP_X_FORWARDED_HOST'])) ? $s['HTTP_X_FORWARDED_HOST'] : (isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : null);
    $host = isset($host) ? $host : $s['SERVER_NAME'] . $port;
    return $protocol . '://' . $host;
}

function clearOut($text)
{
    $text = strip_tags($text);
    $text = htmlentities(($text));
    return $text;
}

function clearIn($text)
{
    global $_MYSQL;
    $text = trim($text);
    $text = strip_tags($text);
    $text = htmlentities(($text));
    $text = addslashes($text);
    $text = $_MYSQL->real_escape_string($text);
    return $text;
}

function nf($number)
{
    return number_format($number, 2, '.', ',');
}

function getCurrUrl()
{
    $protocol = 'http';
    if (isset($_SERVER['HTTPS']))
        $protocol = 'https';
    return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Formats amount in cents, no decimal spaces
 * @param $amount
 * @return float|int
 */
function stripeFormatAmount($amount)
{
    $amount = $amount * 100;
    $amount = round($amount, 0);
    return $amount;
}

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
function isBeta()
{
    return $_SERVER['SERVER_NAME'] == 'intrepicurebeta.com'
        || $_SERVER['SERVER_NAME'] == 'my.intrepicurebeta.com';
}

/**
 * @return bool
 */
function isLive()
{
    return !isLocalhost() && !isBeta();
}

function NOW()
{
    return date('Y-m-d H:i:s');
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

/**
 * Format a string for url
 * Replace all non alphanumeric chars with _
 * @param string $string
 * @return string
 */
function formatStringForUrl($string)
{
    return preg_replace("/[^A-Za-z0-9]/", '_', trim($string));
}

/**
 * Draw rating stars
 * @param int $score
 * @param int $count
 * @return string
 */
function drawRatingStars($score = 0, $count = 0)
{
    $html = "";
    for ($i = 1; $i <= ((int)$score); $i++) {
        $html .= "\n<i class='fa fa-star star' aria-hidden='true'></i>";
    }
    for ($j = ((int)$score); $j < 5; $j++) {
        $html .= "\n<i class='fa fa-star' aria-hidden='true'></i>";
    }
    $html .= "\n<span>({$count})</span>";
    return $html;
}

/**
 * Strip all tags except BR, and nl2br a string for output
 * @param $string
 * @return string
 */
function outputString($string){
    return nl2br(strip_tags($string, 'br'));
}