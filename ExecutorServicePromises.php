<?php

require 'vendor/autoload.php';

class ExecutorService extends \Thread
{
    
    /**
     * Contructor
     *
     * @param string $startFlags The start flags used for starting threads
     */
    public function __construct($bean, $startFlags = null)
    {
        $this->__initBeanAnnotations($bean);
        $this->bean = new $bean();
        
        if (is_null($startFlags)) {
            $startFlags = PTHREADS_INHERIT_ALL | PTHREADS_ALLOW_GLOBALS;
        }
        $this->start($startFlags);
    }
    
    /**
     * Return a valid variable name to be set as global variable
     * based on own class name with automatic namespace cutoff
     *
     * @static
     * @return string
     */
    static public function __getGlobalVarName()
    {
        $cn = __CLASS__;
        return '__' . strtolower(substr($cn, strrpos($cn, '\\')+(int)($cn[0] === '\\')));
    }
    
    /**
     * Initialises the global storage.
     * This fuction should be called on global scope.
     *
     * @static
     * @return void
     */
    static public function __init($bean)
    {
        $globalVarName = self::__getGlobalVarName();
        global $$globalVarName;
        if (is_null($$globalVarName)) {
            return $$globalVarName = new self($bean);
        }
    }
    
    /**
     * Returns the instance created in global scope
     *
     * @static
     * @return GlobalStorage The global storage instance
     */
    static public function __getInstance()
    {
        $globalVarName = self::__getGlobalVarName();
        global $$globalVarName;
        if (is_null($$globalVarName)) {
            throw new \Exception(sprintf("Failed to get instance '$%s'. Please call init() in global scope first and check if PTHREADS_ALLOW_GLOBALS flag is set in specific Thread start calls.", $globalVarName));
        }
        return $$globalVarName;
    }
    
    public function __then(callable $resolve, callable $reject = null, callable $notify = null)
    {
        $this->synchronized(function ($self, $resolve, $reject, $notify) {
            $id = ++$self->promisesCount;
            // copy closures to thread object
            $self["__promises/{$id}/resolve"] = $resolve;
            $self["__promises/{$id}/reject"] = $reject;
            $self["__promises/{$id}/notify"] = $notify;
        }, $this, $resolve, $reject, $notify);
        // return executor service
        return $this;
    }
    
    /**
     * Executes given command with args in a default non locked way
     * 
     * @param string  $cmd   The command to execute
     * @param string  $args  The arguments for the command to be executed
     * @param boolean $async Wheater the method should be executed asynchronously or not
     *
     * @return mixed The return value we got from execution
     */
    public function __execute($cmd, $args, $async = false) {
        // check if execution is going on
        if ($this->run === true) {
            // return null in that case to avoid possible race conditions
            // return null;
            
            // wait while execution is running
            while($this->run) {
                // sleep a little while waiting loop
                usleep(100);
            }
        }
        // synced communication call
        $this->synchronized(function ($self, $cmd, $args) {
            // set run flag to be true cause we wanna run now
            $self->run = true;
            // set command and argument values
            $self->cmd = $cmd;
            $self->args = $args;
            // notify to start execution
            $self->notify();
        }, $this, $cmd, $args);
        
        // check if function should be called async or not
        if ($async) {
            return $this;
        }
        // wait while execution is running
        while($this->run) {
            // sleep a little while waiting loop
            usleep(100);
        }
        
        // check if an exceptions was thrown and throw it again in this context.
        if ($this->exception) {
            throw $this->exception;
        }
        
        // return the return value we got from execution
        return $this->return;
    }
    
    /**
     * Executes the given command and arguments in a synchronized way.
     *
     * This function is intend to be protected to make use of automatic looking
     * when calling this function to avoid race conditions and dead-locks.
     * This means this function can not be called simultaneously.
     *
     * @param string $cmd  The command to execute
     * @param string $args The arguments for the command to be executed
     *
     * @return mixed The return value we got from execution
     */
    protected function __executeSynchronized($cmd, $args)
    {
        // call normal execute function
        return $this->__execute($cmd, $args);
    }

    /**
     * Executes the given command and arguments in an asynchronous way.
     *
     * It will return a promise object which can be used for further callback processing.
     *
     * @param string $cmd  The command to execute
     * @param string $args The arguments for the command to be executed
     *
     * @return mixed The return value we got from execution
     */
    protected function __executeAsynchronous($cmd, $args)
    {
        // call execute function to be async
        return $this->__execute($cmd, $args, true);
    }
    
    /**
     * Introduce a magic __call function to delegate all methods to the internal
     * execution functionality. If you hit a Method which is not available in executor
     * logic, it will throw an exception as you would get a fatal error if you want to call
     * a function on undefined object.
     *
     * @param string $cmd  The command to execute
     * @param string $args The arguments for the command to be executed
     *
     * @return mixed The return value we got from execution
     */
    public function __call($name, $args)
    {
        // check method execution type from mapper
        $executeTypeFunction = $this->methodExecutionTypeMapper["::{$name}"];
        return $this->$executeTypeFunction($name, $args);
    }
    
    /**
     * Returns the bean instance itself
     * 
     * @return mixed
     */
    public function __getBeanInstance()
    {
        return $this->bean;
    }
    
    /**
     * Initializes all bean method annotations
     *  
     * @param string $class The class name
     * @return array
     */
    public function __initBeanAnnotations($class)
    {
        // get reflection of bean class
        $reflector = new ReflectionClass($class);
        // get all Methods
        $methods = $reflector->getMethods();
        // init method exec type mapper array
        $methodExecutionTypeMapper = array();
        // iterate all methods
        foreach ($methods as $method) {
            // set default method type
            $methodExecutionTypeMapper["::{$method->getName()}"] =  '__execute';
            // get method annotations
            preg_match_all('#@(.*?)\n#s', $method->getDocComment(), $annotations);
            // check if asynch annotation
            if (in_array('Asynchronous', $annotations[1])) {
                $methodExecutionTypeMapper["::{$method->getName()}"] = '__executeAsynchronous';
            }
            // check if synch annotation
            if (in_array('Synchronized', $annotations[1])) {
                $methodExecutionTypeMapper["::{$method->getName()}"] = '__executeSynchronized';
            }
        }
        // set mapper array
        $this->methodExecutionTypeMapper = $methodExecutionTypeMapper;
    }
    
    /**
     * Resolves deferred object if exists
     * 
     * @param mixed $valueToResolve The value to resolve
     * 
     * @return mixed
     */
    protected function __resolveDeferred($valueToResolve) {
        // init deferred object for promises
        $deferred = new \React\Promise\Deferred();
        for ($i = 1; $i <= $this->promisesCount; $i++) {
            // build up promises chain from copied closure refs
            $deferred->promise()->then(
                $this["__promises/{$i}/resolve"],
                $this["__promises/{$i}/reject"],
                $this["__promises/{$i}/notify"]
            );
            // free copied closure refs
            unset($this["__promises/{$i}/resolve"]);
            unset($this["__promises/{$i}/reject"]);
            unset($this["__promises/{$i}/notify"]);
        }
        return $deferred->resolve($valueToResolve);
    }
    
    /**
     * The main thread routine function
     *
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        // register autoloader
        require 'vendor/autoload.php';
        
        // set initial param values
        $this->return = null;
        $this->exception = null;
        // set shutdown flag internally so that its only possible change it via shutdown command
        $shutdown = false;
        
        // get bean instance to local var ref
        $bean = $this->__getBeanInstance();
        
        // loop while no shutdown command was sent
        do {
            // synced communication call
            $this->synchronized(function ($self) {
                // set initial param values
                $this->cmd = null;
                $this->args = array();
                $this->promisesCount = 0;
                $self->run = false;
                $self->wait();
                // reset return and exception properties
                $this->exception = null;
                $this->return = null;
            }, $this);
            
            try {
                // try to execute given command with arguments
                $this->return = call_user_func_array(array(&$bean, $this->cmd),  $this->args);
                
                // check if promises are given
                if ($this->promisesCount > 0) {
                    // set return value from deferred resolver
                    $this->return = $this->__resolveDeferred($this->return);
                }
                
            } catch (\Exception $e) {
                // catch and hold all exceptions throws while processing for further usage
                $this->exception = $e;
            }
    
        } while($shutdown === false);
    }
    
}
