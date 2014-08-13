<?php
namespace Swoole\Network;
use Swoole;
/**
 * Class Server
 * @package Swoole\Network
 */
class Server extends \Swoole\Server implements \Swoole\Server\Driver
{
    static $sw_mode = SWOOLE_PROCESS;
    /**
     * @var \swoole_server
     */
    protected $sw;
    protected $swooleSetting;

    function __construct($host, $port, $ssl = false)
    {
        $flag = $ssl ? (SWOOLE_SOCK_TCP | SWOOLE_SSL) : SWOOLE_SOCK_TCP;
        $this->sw = new \swoole_server($host, $port, self::$sw_mode, $flag);
        $this->host = $host;
        $this->port = $port;
        Swoole\Error::$stop = false;
        Swoole\JS::$return = true;
        $this->swooleSetting = array(
            //'reactor_num' => 4,      //reactor thread num
            //'worker_num' => 4,       //worker process num
            'backlog' => 128,        //listen backlog
            //'open_cpu_affinity' => 1,
            //'open_tcp_nodelay' => 1,
            //'log_file' => '/t$this->protocols[]mp/swoole.log',
        );
    }
    function daemonize()
    {
        $this->swooleSetting['daemonize'] = 1;
    }

    function onMasterStart($serv)
    {
        global $argv;
        Swoole\Console::setProcessName('php ' . $argv[0] . ': master -host=' . $this->host . ' -port=' . $this->port);
    }

    function run($setting = array())
    {
        $set = array_merge($this->swooleSetting, $setting);
        $this->sw->set($set);
        $version = explode('.', SWOOLE_VERSION);
        //1.7.0
        if ($version[1] >= 7)
        {
            $this->sw->on('ManagerStart', function($serv) {
                global $argv;
                Swoole\Console::setProcessName('php '.$argv[0].': manager');
            });
        }
        $this->sw->on('Start', array($this, 'onMasterStart'));

        ksort($this->protocols);

        $this->sw->on('Connect', array($this, 'onConnect'));
        $this->sw->on('Receive', array($this, 'onReceive'));
        $this->sw->on('Close', array($this, 'onClose'));

        foreach($this->protocols as $protocolInfo) {
            $protocol = $protocolInfo[0];

            $this->sw->on('WorkerStart', array($protocol, 'onStart'));

            $this->sw->on('WorkerStop', array($protocol, 'onShutdown'));

            if (is_callable(array($protocol, 'onTimer')))
            {
                $this->sw->on('Timer', array($protocol, 'onTimer'));
            }
            if (is_callable(array($protocol, 'onTask')))
            {
                $this->sw->on('Task', array($protocol, 'onTask'));
                $this->sw->on('Finish', array($protocol, 'onFinish'));
            }
        }

        $this->sw->start();
    }

    /**
     * Proxy to protocol's onConnect implementation base on host and port.
     */
    function onConnect($serv, $client_id, $from_id)
    {
        $info = $serv->connection_info($client_id);

        foreach ($this->protocols as $key => $protocolInfo) {
            if ($protocolInfo[2] == $info['from_port']) {
                $protocolInfo[0]->onConnect($serv, $client_id, $from_id);
            }
        }
    }

    /**
     * Proxy to protocol's onReceive implementation base on host and port.
     */
    function onReceive($serv, $client_id, $from_id, $data)
    {
        $info = $serv->connection_info($client_id);

        foreach ($this->protocols as $key => $protocolInfo) {
            if ($protocolInfo[2] == $info['from_port']) {
                $protocolInfo[0]->onReceive($serv, $client_id, $from_id, $data);
            }
        }
    }

    /**
     * Proxy to protocol's onClose implementation base on host and port.
     */
    function onClose($serv, $client_id, $from_id)
    {
        $info = $serv->connection_info($client_id);

        foreach ($this->protocols as $key => $protocolInfo) {
            if ($protocolInfo[2] == $info['from_port']) {
                $protocolInfo[0]->onClose($serv, $client_id, $from_id);
            }
        }
    }

    function shutdown()
    {
        return $this->sw->shutdown();
    }

    function close($client_id)
    {
        return $this->sw->close($client_id);
    }

    function setProtocol($protocol)
    {
        $protocol->server = $this;
        $this->protocols[0] = array($protocol, $this->host, $this->port);
        parent::setProtocol($protocol);
    }

    function addProtocol($protocol, $host, $port)
    {
        $protocol->server = $this;
        $this->protocols[$host . ':' . $port] = array($protocol, $host, $port);
    }

    function addListener($host, $port)
    {
        return $this->sw->addlistener($host, $port, SWOOLE_SOCK_TCP);
    }

    function send($client_id, $data)
    {
        return $this->sw->send($client_id, $data);
    }
}
