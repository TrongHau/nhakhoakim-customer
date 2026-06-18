<?php
namespace App\Libs\Queue;

use App\Libs\Base\Factory;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use App\Libs\Crypt\Crypt;
use \Exception AS Exception;

/**
 *
 * @author giangdn
 *
 */
class Queue
{
    /**
     * @var AMQPStreamConnection
     */
    public $connection      = null;
    
    /**
     * @var AMQPChannel
     */
    public $channel         = null;
    
    /**
     * @var ConfigQueue
     */
    public $config          = null;
    
    /**
     * @var array
     */
    public $listening       = [];
    
    /**
     * @param object $config
     */
    public function __construct($config)
    {
        $this->config = $config;
        try {
            //connect to queue server
            $this->_connect();
        } catch (Exception $e) {
            app('log')->error('Connect to queue server failure. ' . $e->getMessage());
        }
    }
    
    /**
     * @param string $queue
     * @param array| Message $msg
     * @return App\Libs\Remote\Queue\Queue
     */
    public function enqueue($queue, $msg, $exchange = '')
    {
        if ($queue) {
            try {
                $this->_declare($queue);
                if (is_object($msg)) {
                    $this->channel->basic_publish($msg, $exchange, $queue);
                } elseif (is_array($msg) && count($msg)) {
                    foreach ($msg AS $m) {
                        $this->channel->basic_publish($m, $exchange, $queue);
                    }
                }
            } catch (Exception $e) {
                app('log')->error('Queue enqueue fail: ' . $e->getMessage());
            }
        }
        
        return $this;
    }
    
    /**
     * @param string $QueueName
     * @param callable $callbacks
     * @param string $consumerTag
     * @param string $exchange
     * @return Queue
     */
    public function listen($queue, $callback, $consumerTag = '', $exchange = '', $deliveryTag = '', $qos = 0)
    {
        if ($queue && is_callable($callback)) {
            try {
                /**
                 * name: $queue
                 * passive: false
                 * durable: true // the queue will survive server restarts
                 * exclusive: false // the queue can be accessed in other channels
                 * auto_delete: false //the queue won't be deleted once the channel is closed.
                 */
                $this->_declare($queue);
                
                if (is_object($this->channel)) {
                    /**
                     * exchange the queue or not
                     */
                    if ($exchange) {
                        /**
                         * name: $exchange
                         * type: direct
                         * passive: false
                         * durable: true // the exchange will survive server restarts
                         * auto_delete: false //the exchange won't be deleted once the channel is closed.
                         */
                        $this->channel->exchange_declare($exchange, 'direct', false, true, false);
                        $this->channel->queue_bind($queue, $exchange);
                    }
                    
                    /**
                     * prefetch option for fitting quality-of-service
                     */
                    if ($qos > 0) {
                        $this->channel->basic_qos(null, $qos, null);
                        $this->channel->basic_ack($deliveryTag, true);
                    }
                    
                    /**
                     * name: Queue from where to get the messages
                     * consumer_tag: Consumer identifier
                     * no_local: Don't receive messages published by this consumer.
                     * no_ack: If set to true, automatic acknowledgement mode will be used by this consumer. See https://www.rabbitmq.com/confirms.html for details.
                     * exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
                     * nowait:
                     * callback: A PHP Callback
                     * ticket:
                     * arguments:
                     */
                    $this->channel->basic_consume($queue, $consumerTag, false, true, false, false, $callback);
                } else throw new Exception('channel is not an object');
            } catch (Exception $e) {
                app('log')->error('Queue listen fail: ' . $e->getMessage());
            }
        }
        return $this;
    }
    
    /**
     * @param number $reconnectPeding time waiting before re-connect againt if lost
     * @param number $maxReconnectAttempt how many times the consumer be allowed to re-connect
     * @desc each 10 seconds will make a re-connection, try 17280 times means the consumer will waiting for the server maximum for 2 days
     * @throws Exception
     */
    public function wait($reconnectPeding = 10, $maxReconnectAttempt = 17280)
    {
        $connectAttemp          = 1;
        while (!$this->_connected() && $connectAttemp < $maxReconnectAttempt) {
            // try to connect again and again $maxConnectAttempt times
            try {
                app('log')->error('Queue re-connect attemp ' . $connectAttemp);
                $this->_reconnect(true);
            } catch (Exception $e) {
                app('log')->error('Queue re-connect attemp ' . $connectAttemp . ' fail: ' . $e->getMessage());
            }
            sleep($reconnectPeding); 
            $connectAttemp ++;
        }
        
        while ($this->_connected() && count($this->channel->callbacks)) {
            try {
                $this->channel->wait(null, true);
            } catch (Exception $e) {
                app('log')->error('Queue wait fail: ' . $e->getMessage());
                $this->_disconnect();
            }
        }
        
        throw new Exception('Queue fail. must to restart');
    }
    
    
    public function __destruct()
    {
        $this->config       = null;
        $this->listening    = [];
        $this->channel      = null;
        $this->_disconnect();
    }
    
    /**
     * @return boolean
     */
    protected function _connected()
    {
        return is_object($this->connection)
        && $this->connection->isConnected()
        && is_object($this->channel);
    }
    
    /**
     * @param string $queue
     * @param boolean $passive
     * @param boolean $durable
     * @param boolean $exclusive
     * @param boolean $auto_delete
     */
    protected function _declare($queue, $passive = false, $durable = true, $exclusive = false, $auto_delete = false)
    {
        if (!in_array($queue, $this->listening)) {
            $this->listening[] = $queue;
            $this->channel->queue_declare($queue, $passive, $durable, $exclusive, $auto_delete);
        }
    }
    
    
    /**
     * @throws \Exception
     */
    protected function _connect()
    {

        $host       = env('QUEUE_HOST','127.0.0.1');
        $port       = env('QUEUE_PORT',5672);
        $username   = env('QUEUE_USERNAME','demo');
        $password   = env('QUEUE_PASSWORD','');
        $vhost   = env('QUEUE_VHOST','');
        try {
            $this->connection = new AMQPStreamConnection($host, $port, $username, $password, $vhost );
            //binding default channel
            $this->channel = $this->connection->channel('');

        } catch (Exception $e) {
            app('log')->error($e->getMessage());
            throw $e;
        }
        
    }
    
    protected function _reconnect($force = false)
    {
        try {
            if ($this->connection instanceof AMQPStreamConnection
                && (!$this->connection->isConnected() || $force)) 
            {
                $this->connection->reconnect();
                $this->channel = $this->connection->channel();
                app('log')->error('reconnected');
            }
        } catch (Exception $e) {
            app('log')->error('Queue reconect fail: ' . $e->getMessage());
            throw $e;
        }
    }
    
    protected function _disconnect()
    {
        if ($this->_connected()) {
            try {
                $this->listening = [];
                $this->channel->close();
                $this->connection->close();
            } catch (Exception $e) {
                app('log')->error('Disconnect queue server fail. ' . $e->getMessage());
            }
        }
    }
    
    
    
    /**
     * @param string $key
     * @param mixed $dft
     */
    public function getConfig($key = null, $dft = '')
    {
        if (!$key) {
            return $this->config;
        } else {
            return isset($this->config->$key) ? $this->config->$key : $dft;
        }
    }
    
    /**
     * @param mixed stdClass|array $config
     * @return Queue
     */
    public static function getInstance($config = null)
    {
        
        $config = (Object)[
            'host'=>env('QUEUE_HOST',''),  
            'port'=>env('QUEUE_PORT',''),
            'username'=>env('QUEUE_USERNAME',''),
            'password'=>env('QUEUE_PASSWORD',''),
        ];
        if ($config) {
            $key = Crypt::hash(json_encode($config), Crypt::$SHA1);
            
            if (!isset(self::$instances[$key])) {
                self::$instances[$key] = new Queue($config);
            }
            
            return self::$instances[$key];
        } else {
            app('log')->error('invalid queue config');
        }
    }
    
    /**
     * @var Queue[]
     */
    protected static $instances = [];
}