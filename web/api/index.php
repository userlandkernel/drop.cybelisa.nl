<?php
// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Custom error handler
function error($message = 'A request was sent that could not be understood', $status = '400 Bad Request', $includeDefaultMessage = true) {
    header('HTTP/1.1 ' . $status);
    $defaultMessage = $includeDefaultMessage ? '. API documentation is currently unavailable, but feel free to contact lgms.nl/email for help!' : '';
    echo json_encode(['error' => $message . $defaultMessage]);
    exit;
}

// Validate API version
if (!isset($_GET['v'])) {
    error();
}
$ApiVersion = intval($_GET['v']);
if ($ApiVersion !== 1 && $ApiVersion !== 2) {
    error('Invalid version number');
}

// Load dependencies
require('dbconn.php');
require('functions.php');

// All
// Command routing
switch ($_GET['cmd'] ?? '') {
    case 'allocate':
        header('Content-Type: application/json');
        echo json_encode(allocate($_GET['code'] ?? false, $ApiVersion));
        exit;

    case 'set':
        api_set();
        exit;

    case 'setExpireAfterDownload':
        setExpireAfterDownload($_GET['secret'], $_GET['expireAfterDownload'] === 'false' ? '0' : '1');
        echo '1';
        exit;

    case 'check':
        check();
        exit;

    case 'extend':
        extend();
        exit;

    case 'move':
        move($_GET['oldsecret'], $_GET['newsecret']);
        echo '1';
        exit;

    case 'clear':
        $secret = $db->escape_string($_GET['secret']);
        clearUrl($secret);
        echo '1';
        exit;

    case 'getqr':
        if (strlen($_GET['code']) > 255) {
            error('Code too long');
        }
        require('phpqrcode/qrlib.php');
        QRcode::png('https://drop.cyberlisa.nl/' . $_GET['code'], false, 'M', 8, 2);
        exit;

    default:
        error('Unknown command or no command specified');
}

// Fallback
error('This code should never be reached, so some weird bug occurred', '500 Internal Server Error');

// Helper: check
function check() {
    $i = 0;
    do {
        list($status, $type, $data, $expires) = tryGet($_GET['val'], true);
        if ($status === true) {
            echo '1';
            return;
        }
        usleep(300 * 1000);
    } while (++$i < 5);
    echo '0';
}

// Helper: extend
function extend() {
    global $db;

    if (!isset($_GET['secret'])) {
        error("No secret included in request");
    }

    $val = intval($_GET['val']);
    if ($val != $_GET['val'] || $val > 72 * 3600 || $val < 10) {
        error('Missing or invalid value, or value is higher than the maximum or lower than the minimum time.');
    }

    $newexpires = time() + $val;
    $db->query('UPDATE shorts SET `expires` = ' . $newexpires . ' WHERE `secret` = "' . $db->escape_string($_GET['secret']) . '" AND `expires` < ' . $newexpires)
        or error('Database error 185302');

    echo '1';
}
