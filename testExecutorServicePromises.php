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
     * Returns object itself
     * 
     * @return SingletonSessionBean
     */
    public function dump()
    {
        return $this;
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
        $bean = ExecutorService::__getInstance();
        $bean->doSomething();
        $bean->set('ThreadId:' . $this->getThreadId(), md5(time()));
    }
    
}
$bean = ExecutorService::__init('\SingletonSessionBean');

$maxr = 100;
$r = array();
for ($i = 1; $i <= $maxr; $i++) {
    $r[$i] = new Request();
}
for ($i = 1; $i <= $maxr; $i++) {
    $r[$i]->join();
}

$bean->set('testKey', 'testValue');
var_dump($bean->get('testKey'));

$bean->doAwesome('David', 'Test')
->__then(function($user) use ($bean) {
    echo "Amazing Callback 1111!" . PHP_EOL;
})
->__then(function($x) {
    echo "Amazing Callback 2222!" . PHP_EOL;
})
->__then(function($x) {
    echo "Amazing Callback 3333!" . PHP_EOL;
});

echo 'finished script' . PHP_EOL;

var_dump($bean->get('testKey'));

while(1) {
    usleep(1000000);
}


