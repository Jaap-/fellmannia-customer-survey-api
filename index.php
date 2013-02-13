<?php
require 'libraries/Slim/Slim.php';
include_once('libraries/meekrodb.2.1.class.php');
include_once('libraries/FirePHPCore/FirePHP.class.php');
include_once('config/config.php');
include_once('helpers/echo_json.php');
include_once('classes/class.LoginManager.php');

// For debugging
$firephp = FirePHP::getInstance(true);
$firephp->setEnabled(true);

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();

DB::$user = DB_USER;
DB::$password = DB_PASSWORD;
DB::$dbName = DB_DATABASE;
DB::$encoding = 'utf8';

/**
 * Get categories
 */
$app->get('/categories', function() {
    $result = DB::query("SELECT * FROM Categories");

    $arr = array();
    foreach ($result as $row) {
        $arr[] = array('id' => $row['id'], 'name' => $row['name'], 'instructiontext' => $row['instructionText']);
    }

    echoJSON(array('status' => 1, 'categories' => $arr));
});

/**
 * Add category
 * @param {string} name
 * @param {string} description
 */
$app->post('/categories/:name/:description', function($name, $description) {
    DB::insert('Categories', array(
        'name' => $name,
        'description' => $description
    ));

    echoJSON(array('status' => 1));
});

/**
 * Update category
 * @param {int} id
 * @param {string} name
 * @param {string} description
 */
$app->put('/categories/:id/:name/:description', function($id, $name, $description) {
    DB::update('tbl', array(
        'name' => $name,
        'description' => $description
            ), "id=%i", $id);

    echoJSON(array('status' => 1));
});

/**
 * Delete category
 * @param {int} id
 */
$app->delete('/categories/:id', function($id) {
    $l = new LoginManager();

    if ($l->loginCheck()) {
        DB::delete('Categories', "id=%i", $id);

        echoJSON(array('status' => 1));
    }else{
        echoJSON(array('status' => 0, 'errors' => array('Not logged in')));
    }
});



/**
 * Get comments
 * @param {int} catid
 * @param {int} limit optional
 */
$app->get('/comments/:catid/:limit', function($catid, $limit) {
    if ($catid != '') {
        if (is_numeric($limit)) {
            $arr = array();
            if ($catid == 1) {
                $result = DB::query('SELECT * FROM Comments WHERE questionID = 0 ORDER BY Categories_id DESC, id DESC');
                $arrCount = array();
                foreach ($result as $row) {
                    if (!isset($arrCount[$row['Categories_id']])) {
                        $arrCount[$row['Categories_id']] = 0;
                    }
                    if ($arrCount[$row['Categories_id']] <= 1) {
                        $arrCount[$row['Categories_id']]++;
                        $arr[] = array('id' => $row['id'],
                            'categoryid' => $row['Categories_id'],
                            //'type'=>$row['type'], 
                            'status' => $row['status'],
                            'timestamp' => $row['timestamp'],
                            'thumbcountplus' => $row['thumbCountPlus'],
                            'thumbcountminus' => $row['thumbCountMinus'],
                            'text' => $row['text'],
                            //'contactinfo'=>$row['contactInfo'], 
                            //'contact'=>$row['contact'], 
                            'checked' => $row['checked']);
                    }
                }
            } else if ($catid == 2) { // monthly question
                $result = DB::query('SELECT * FROM Questions ORDER BY id DESC LIMIT 1');
                foreach ($result as $row) {
                    $arr[] = array(
                        'id' => $row['id'],
                        'status' => 3,
                        'text' => $row['text']);
                }
            } else {
                if ($limit == 0) {
                    $result = DB::query('SELECT * FROM Comments WHERE Categories_id=%i', $catid);
                } else {
                    $result = DB::query('SELECT * FROM Comments WHERE Categories_id=%i LIMIT %i', $catid, $limit);
                }
                foreach ($result as $row) {
                    $arr[] = array('id' => $row['id'],
                        'categoryid' => $row['Categories_id'],
                        //'type'=>$row['type'], 
                        'status' => $row['status'],
                        'timestamp' => $row['timestamp'],
                        'thumbcountplus' => $row['thumbCountPlus'],
                        'thumbcountminus' => $row['thumbCountMinus'],
                        'text' => $row['text'],
                        //'contactinfo'=>$row['contactInfo'], 
                        //'contact'=>$row['contact'], 
                        'checked' => $row['checked']);
                }
            }

            echoJSON(array('status' => 1, 'comments' => $arr));
        } else {
            echoJSON(array('status' => 0, 'msg' => 'Incorrect limit'));
        }
    } else {
        echoJSON(array('status' => 0, 'msg' => 'Category ID missing'));
    }
});

/**
 * Add comment
 * @param {string} name
 * @param {string} description
 */
$app->post('/comments/:catid/:questionid/:status/:text(/:name(/:email(/:phone)))', function($catid, $questionid, $status, $text, $name='_', $email='_', $phone='_') {
    DB::insert('Comments', array(
        'Categories_id' => $catid,
        'questionID' => $questionid,
        //'type'=>$type,
        'status' => $status,
        'timestamp' => time(),
        'text' => $text,
        'name' => ($name == '_') ? '' : $name,
        'email' => ($email == '_') ? '' : $email,
        'phone' => ($phone == '_') ? '' : $phone
    ));

    echoJSON(array('status' => 1));
});

/**
 * Delete comment
 * @param {int} id
 */
$app->delete('/comments/:id', function($id) {
    $l = new LoginManager();

    if ($l->loginCheck()) {
        DB::delete('Thumbs', "Comments_id=%i", $id);
        DB::delete('Comments', "id=%i", $id);

        echoJSON(array('status' => 1));
    }else{
        echoJSON(array('status' => 0, 'errors' => array('Not logged in')));
    }
});

/**
 * Like comment
 * @param {int} comment id
 */
$app->post('/comments/like/:id', function($id) {
    $userHash = md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
    $resultThumbs = DB::queryFirstRow("SELECT * FROM Thumbs WHERE Comments_id=%i AND userHash=%s", $id, $userHash);

    if (!isset($resultThumbs['userHash'])) {
        $result = DB::query("SELECT thumbCountPlus FROM Comments WHERE id=%i", $id);
        DB::update('Comments', array(
            'thumbCountPlus' => $result[0]['thumbCountPlus'] + 1
                ), "id=%i", $id);

        DB::insert('Thumbs', array(
            'Comments_id' => $id,
            'status' => 0,
            'timestamp' => time(),
            'userHash' => $userHash
        ));

        echoJSON(array('status' => 1));
    } else {
        echoJSON(array('status' => 0, 'msg' => 'Olet jo arvioinut tämän palautteen!'));
    }
});

/**
 * Disike comment
 * @param {int} comment id
 */
$app->post('/comments/dislike/:id', function($id) {
    $userHash = md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
    $resultThumbs = DB::queryFirstRow("SELECT * FROM Thumbs WHERE Comments_id=%i AND userHash=%s", $id, $userHash);

    if (!isset($resultThumbs['userHash'])) {
        $resultComments = DB::query("SELECT thumbCountMinus FROM Comments WHERE id=%i", $id);
        DB::update('Comments', array(
            'thumbCountMinus' => $resultComments[0]['thumbCountMinus'] + 1
                ), "id=%i", $id);

        DB::insert('Thumbs', array(
            'Comments_id' => $id,
            'status' => 1,
            'timestamp' => time(),
            'userHash' => $userHash
        ));

        echoJSON(array('status' => 1));
    } else {
        echoJSON(array('status' => 0, 'msg' => 'Olet jo arvioinut tämän palautteen!'));
    }
});



/**
 * Get question
 */
$app->get('/question', function() {
    $arr = array();
    $result = DB::query('SELECT * FROM Questions ORDER BY id DESC LIMIT 1');
    $arrCount = array();
    foreach ($result as $row) {
        $arr[] = array('text' => $row['text']);
    }

    echoJSON(array('status' => 1, 'question' => $arr));
});

/**
 * Get question comments
 * @param {int} question ID
 */
$app->get('/question/comments/:qid', function($qid) {
    if ($qid != '') {
        $arr = array();
        $result = DB::query('SELECT text FROM Comments WHERE questionID = %i ORDER BY id DESC', $qid);
        foreach ($result as $row) {
            $arr[] = array('text' => $row['text']);
        }

        echoJSON(array('status' => 1, 'comments' => $arr));
    } else {
        echoJSON(array('status' => 0, 'msg' => 'Question ID missing'));
    }
});

/**
 * Add question
 * @param {string} question
 */
$app->post('/question/:question', function($question) {
    str_replace('?', '&63;', $question);
    DB::insert('Questions', array(
        'text' => $question
    ));

    echoJSON(array('status' => 1));
});

/**
 * Add question comment
 * @param {string} comment
 */
$app->post('/comments/question/:text', function($text) {
    if($text != ''){
        $question = DB::queryFirstRow('SELECT * FROM Questions ORDER BY id DESC LIMIT 1');
        DB::insert('Comments', array(
            'Categories_id' => 2,
            'questionID' => $question['id'],
            'text' => $text
        ));

        echoJSON(array('status' => 1));
    }else{
        echoJSON(array('status' => 0, 'errors' => array('Comment missing')));
    }
});


/**
 * Login
 * @param {string} email
 * @param {string} hashed password
 */
$app->post('/login/:email/:password', function($email, $password) {
    global $firephp;
    if (isset($email, $password)) {
        $l = new LoginManager();
        if ($l->login($email, $password, $firephp) == true) {
            echoJSON(array('status' => 1));
        } else {
            echoJSON(array('status' => 0, 'msq' => 'Login failed'));
        }
    } else {
        echoJSON(array('status' => 0, 'msq' => 'Login credentials missing'));
    }
});

/**
 * Login check
 */
$app->post('/logincheck', function() {
    $l = new LoginManager();
    if (!$l->loginCheck()) {
        echoJSON(array('status' => 0));
    } else {
        echoJSON(array('status' => 1));
    }
});

/**
 * Logout
 */
$app->post('/logout', function() {
    $l = new LoginManager();
    $l->logout();
    echoJSON(array('status' => 1));
});

/**
 * Open locked account
 * @param {string} email
 * @param {string} hashed password
 */
$app->post('/openlockedaccount/:userid', function($userid) {
    if ($userid != '') {
        $l = new LoginManager();
        $affectedRows = $l->openLockedAccount($userid);
        echoJSON(array('status' => 1));
    } else {
        echoJSON(array('status' => 0, 'msg' => 'Userid missing'));
    }
});

/**
 * Add user
 * @param {string} username
 * @param {string} email
 * @param {string} hashed password
 */
$app->post('/adduser/:username/:email/:password', function($username, $email, $password) {
    if ($username != '' && $email != '' && $password != '') {
        $salt = hash(HASH_FUNCTION, uniqid(mt_rand(1, mt_getrandmax()), true)); // Create a random salt

        DB::insert('Users', array(
            'username' => $username,
            'email' => $email,
            'password' => hash(HASH_FUNCTION, $password . $salt), // Create salted password
            'salt' => $salt
        ));
        $affectedRows = DB::affectedRows();

        echoJSON(array('status' => $affectedRows));
    } else {
        echoJSON(array('status' => 0, 'msg' => 'Missing information'));
    }
});


/**
 * Get staff data
 * @param {int} start
 * @param {int} end
 */ 
$app->get('/staffdata(/:start(/:end))', function($start='', $end=''){
    global $firephp;
    $l = new LoginManager();

    if ($l->loginCheck()) {
        if($start == '' && $end == ''){
            $arrStatsByTime = DB::query("SELECT co.id, co.Categories_id, ca.name, co.status, co.timestamp, co.thumbCountPlus, co.thumbCountMinus, co.checked FROM Comments AS co, Categories AS ca WHERE co.Categories_id = ca.id AND ca.id != 2 ORDER BY ca.id ASC, co.timestamp ASC");
	}else{
            $arrStatsByTime = DB::query("SELECT co.id, co.Categories_id, ca.name, co.status, co.timestamp, co.thumbCountPlus, co.thumbCountMinus, co.checked FROM Comments AS co, Categories AS ca WHERE co.Categories_id = ca.id AND ca.id != 2 AND co.timestamp >= %i AND co.timestamp <= %i ORDER BY ca.id ASC, co.timestamp ASC", $start, $end);
	}

        $arrStatsByTime[] = array('end');
        
        $arr = array();
        $stats = array();

        $counter = 0;
        $currentCategory = 0;
        $currentCategoryName = 0;
        
        foreach ($arrStatsByTime as &$row) {
            if ($counter == 0) {
                $currentCategory = $row['Categories_id'];
                $currentCategoryName = $row['name'];
            }

            if(!isset($row['Categories_id'])){
                if($row[0] == 'end'){
                    $row['Categories_id'] = 'end';
                    $previousCategory = $currentCategory;
                    $currentCategory = $row['Categories_id'];
                }
            }else{
                $previousCategory = $currentCategory;
                $currentCategory = $row['Categories_id'];
                $categoryName[$currentCategory] = $row['name'];

                (isset($stats[$currentCategory]['status'][$row['status']])) ? $stats[$currentCategory]['status'][$row['status']]++ : $stats[$currentCategory]['status'][$row['status']] = 1;
                //$stats[$currentCategory]['status'][$row['status']]++;
                (isset($stats[$currentCategory]['count'])) ? $stats[$currentCategory]['count']++ : $stats[$currentCategory]['count'] = 1;
            }

            if ($row['Categories_id'] != $previousCategory) {
                $stats2 = array();

                // 0=negatiivinen, 1=neutraali, 2=positiivinen
                $count = (isset($stats[$previousCategory]['count'])) ? $stats[$previousCategory]['count'] : 0; // - $stats[$previousCategory]['status'][3];
                $negative = (isset($stats[$previousCategory]['status'][0])) ? $stats[$previousCategory]['status'][0] : 0;
                $neutral = (isset($stats[$previousCategory]['status'][1])) ? $stats[$previousCategory]['status'][1] : 0;
                $positive = (isset($stats[$previousCategory]['status'][2])) ? $stats[$previousCategory]['status'][2] : 0;

                $stats2 = array('countnegative' => $negative,
                    'countneutral' => $neutral,
                    'countpositive' => $positive,
                    'count' => $count,
                    'negativepercent' => ($count != 0) ? round(100 * $negative / $count) : 0,
                    'neutralpercent' => ($count != 0) ? round(100 * $neutral / $count) : 0,
                    'positivepercent' => ($count != 0) ? round(100 * $positive / $count) : 0
                );

                $arr[] = array('catid' => $previousCategory, 'catname' => $categoryName[$previousCategory], 'stats' => $stats2);
            }
            $counter++;
        }
        echoJSON(array('status' => 1, 'categories' => $arr));
    }else{
        echoJSON(array('status' => 0, 'errors' => array('Not logged in')));
    }
});

/**
 * Get staff data
 * @param {int} start
 * @param {int} end
 */ 
$app->get('/staffchartdata(/:start(/:end))', function($start='', $end=''){
    global $firephp;
    $l = new LoginManager();

    if ($l->loginCheck()) {
        if($start == '' && $end == ''){
            $arrStatsByTime = DB::query("SELECT co.id, co.Categories_id, ca.name, co.status, co.timestamp, co.thumbCountPlus, co.thumbCountMinus, co.checked FROM Comments AS co, Categories AS ca WHERE co.Categories_id = ca.id ORDER BY ca.id ASC, co.timestamp ASC");
	}else{
            $arrStatsByTime = DB::query("SELECT co.id, co.Categories_id, ca.name, co.status, co.timestamp, co.thumbCountPlus, co.thumbCountMinus, co.checked FROM Comments AS co, Categories AS ca WHERE co.Categories_id = ca.id AND co.timestamp >= %i AND co.timestamp <= %i ORDER BY ca.id ASC, co.timestamp ASC", $start, $end);
	}

        $arrStatsByTime[] = array('end');
        
        $arr = array();
        $stats = array();

        $counter = 0;
        $currentCategory = 0;
        $currentCategoryName = 0;
        
        foreach ($arrStatsByTime as &$row) {
            if ($counter == 0) {
                $currentCategory = $row['Categories_id'];
                $currentCategoryName = $row['name'];
            }

            if(!isset($row['Categories_id'])){
                if($row[0] == 'end'){
                    $row['Categories_id'] = 'end';
                    $previousCategory = $currentCategory;
                    $currentCategory = $row['Categories_id'];
                }
            }else{
                $previousCategory = $currentCategory;
                $currentCategory = $row['Categories_id'];
                $categoryName[$currentCategory] = $row['name'];
                $currentTime = strftime('%d.%m.%Y', $row['timestamp']);

                (isset($stats[$currentCategory][$currentTime]['status'][$row['status']])) ? $stats[$currentCategory][$currentTime]['status'][$row['status']]++ : $stats[$currentCategory][$currentTime]['status'][$row['status']] = 1;
                //$stats[$currentCategory]['status'][$row['status']]++;
                (isset($stats[$currentCategory][$currentTime]['count'])) ? $stats[$currentCategory][$currentTime]['count']++ : $stats[$currentCategory][$currentTime]['count'] = 1;
            }

            if ($row['Categories_id'] != $previousCategory) {
                $stats2 = array();

                foreach ($stats[$previousCategory] as $i => $date) {
                    // 0=negatiivinen, 1=neutraali, 2=positiivinen
                    $count = (isset($date['count'])) ? $date['count'] : 0; // - $stats[$previousCategory]['status'][3];
                    $negative = (isset($date['status'][0])) ? $date['status'][0] : 0;
                    $neutral = (isset($date['status'][1])) ? $date['status'][1] : 0;
                    $positive = (isset($date['status'][2])) ? $date['status'][2] : 0;

                    $dateExploded = explode('.', $i);

                    $stats2[] = array('date' => mktime(0, 0, 0, $dateExploded[1], $dateExploded[0], $dateExploded[2]),
                        'countnegative' => $negative,
                        'countneutral' => $neutral,
                        'countpositive' => $positive,
                        'count' => $count,
                        'negativepercent' => ($count != 0) ? round(100 * $negative / $count) : 0,
                        'neutralpercent' => ($count != 0) ? round(100 * $neutral / $count) : 0,
                        'positivepercent' => ($count != 0) ? round(100 * $positive / $count) : 0
                    );
                }
                $arr[] = array('catid' => $previousCategory, 'catname' => $categoryName[$previousCategory], 'dates' => $stats2);
            }
            $counter++;
        }
        echoJSON(array('status' => 1, 'categories' => $arr));
    } else {
        echoJSON(array('status' => 0, 'errors' => array('Not logged in')));
    }
});

$app->run();
