<?php
require 'flight/autoload.php';

require 'constant.php';

use flight\Engine;

define('JSON_MESSAGE', array(
    0 => 'success',
    999 => 'internal server error',
    998 => 'unauthorized',
    100 => 'failure',
    101 => 'not found'
));

$app = new Engine();

///
$app->map('error', function (Throwable $ex) use ($app) {
    file_put_contents('.error.log', strval($ex));
    $app->halt(500, json_encode(array(
        'code' => 999,
        'message' => JSON_MESSAGE[999],
    )));
});

///
$app->register('db', 'PDO', array('mysql:host=localhost;dbname=lty', DB_USER, DB_PASSWORD, array(
    PDO::ATTR_PERSISTENT => true
)), function ($db) {
    $db->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec(file_get_contents('sql/create.sql'));
});

$app->map('jsuccess', function (array $json = null) use ($app) {
    $base = array('code' => 0, 'message' => JSON_MESSAGE[0]);
    $app->json(
        $json === null? $base : array_merge($base, $json)
    );
});

$app->map('jfailure', function ($code=100, $message=null) use ($app) {
    $app->halt(400, json_encode(array(
        'code' => $code,
        'message' => $message ?? JSON_MESSAGE[$code],
    )));
});

$app->map('bauth', function ($user, $pw, $callback) {
    $unauthorized = function () {
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(array(
            'code' => 998,
            'message' => JSON_MESSAGE[998],
        ));
        die();
    };
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="admin"');
        $unauthorized();
    } else {
        if ($_SERVER['PHP_AUTH_USER'] === $user and $_SERVER['PHP_AUTH_PW'] === $pw) {
            $callback();
        } else {
            $unauthorized();
        }
    }
});

// route
$app->route('/', function () {
    echo 'hello world!';
});

$app->route('/success', function () use ($app) {
    $app->jsuccess();
});

$app->route('/failure', function () use ($app) {
    $app->jfailure();
});

$app->route('POST /db', function () use ($app) {
    $app->bauth(BASIC_AUTH_USER, BASIC_AUTH_PW, function () use ($app) {
        $app->db()->exec($_POST['c'] ?? '');
        $app->jsuccess();
    });
});

$app->route('DELETE /setqq/users/@uin:[0-9]+', function ($uin) use ($app) {
    $app->bauth(BASIC_AUTH_USER, BASIC_AUTH_PW, function () use ($uin, $app) {
        $stmt = $app->db()->prepare('DELETE FROM setqq_users WHERE uin=:uin');
        $stmt->bindParam(':uin', $uin);
        $stmt->execute();
        $app->jsuccess();
    });
});

$app->route('PUT /setqq/users/@uin:[0-9]+', function ($uin) use ($app) {
    if ($uin == 0) {
        $app->jfailure();
    } else {
        $stmt = $app->db()->prepare('REPLACE INTO setqq_users(uin, last_login) VALUES (:uin, NOW())');
        $stmt->bindParam(':uin', $uin);
        $stmt->execute();
        $app->jsuccess();
    }
});

$app->route('GET /setqq/users/@uin:[0-9]+', function ($uin) use ($app) {
    $stmt = $app->db()->query('SELECT * FROM setqq_users WHERE uin=' . $uin);
    $result = null;
    foreach ($stmt as $row) {
        $result = array_filter($row, function ($k) {
            return !is_int($k) and $k !== 'id';
        }, ARRAY_FILTER_USE_KEY);
    }
    if ($result === null) {
        $app->jfailure(101);
    } else {
        $app->jsuccess(array('result' => $result));
    }
});

$app->route('GET /setqq/users', function () use ($app) {
    $stmt = $app->db()->query('SELECT * FROM setqq_users');
    $result = array();
    foreach ($stmt as $row) {
        $result[] = array_filter($row, function ($k) {
            return !is_int($k) and $k !== 'id';
        }, ARRAY_FILTER_USE_KEY);
    }
    $app->jsuccess(array('result'=>$result));
});

$app->start();
