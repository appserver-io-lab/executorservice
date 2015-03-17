<?php

require('ExecutorService.php');

class SingletonSessionBean {
    
    public function __construct() {
        $this->array = array();
    }
    
    /**
     * Sets an value for specific key
     * 
     * @param string $key
     * @param mixed $value
     * 
     * @return void
     * @Synchronized
     */
    public function set($key, $value) {
        $this->data[$key] = $value;
    }
    
    /**
     * Returns value for specific key
     * 
     * @param string $key
     * 
     * @return mixed
     * @Synchronized
     */
    public function get($key) {
        return $this->data[$key];
    }
    
    /**
     * Do something example function
     * 
     * @return void
     * @Synchronized
     */
    public function doSomething() {
        $this->array["bla"] = array(
            "foo" => "bar"
        );
        $this->arrayObj = new \ArrayObject($this->array);
    }
    
    /**
     * Async example function
     *
     * @return void
     * @Asynchronous
     */
    public function doAwesome($a, $b) {
        echo "begin : " . __METHOD__ . PHP_EOL;
        usleep(1000000);
        $user = new \stdClass();
        $user->name = $a. $b;
        echo "end : " . __METHOD__ . PHP_EOL;
        return $user;
    }
}

class Request extends \Thread
{
    public function __construct() {
        $this->start(PTHREADS_INHERIT_ALL | PTHREADS_ALLOW_GLOBALS);
    }
    
    public function run()
    {
        $session = \AppserverIo\Lab\ExecutorService::__getEntity('session');
        $session->doSomething();
        $session->set('ThreadId:' . $this->getThreadId(), md5(time()));
    }
}

class TestThread extends \Thread
{
    public function run()
    {
        $session = \AppserverIo\Lab\ExecutorService::__newFromEntity('\SingletonSessionBean', 'session');
        usleep(2000000);
        var_dump($session->__return());
    }
}

\AppserverIo\Lab\ExecutorService::__init();

$entity = \AppserverIo\Lab\ExecutorService::__newFromEntity('\SingletonSessionBean');

$t = new \TestThread();
$t->start(PTHREADS_INHERIT_ALL | PTHREADS_ALLOW_GLOBALS);

$entity->doAwesome('FOO', 'BAR');
$entity->doSomething();

var_dump($entity->__return());

$entity->__reset();

var_dump($entity->__return());

$entity->doSomething();

var_dump($entity->__return());
$entity->doAwesome('FOO', 'BAR')->__callback(function($x) { echo 'CALLBACK!' . PHP_EOL; });

$maxr = 100;
$r = array();
for ($i = 1; $i <= $maxr; $i++) {
    $r[$i] = new \Request();
}
for ($i = 1; $i <= $maxr; $i++) {
    $r[$i]->join();
}

$session = \AppserverIo\Lab\ExecutorService::__getEntity('session');
$session->doSomething();

echo "finished script" . PHP_EOL;

$t->join();

while(1) {
    usleep(1000000);
}



