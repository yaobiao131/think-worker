<?php


namespace think\worker\command;


use GatewayWorker\BusinessWorker;
use GatewayWorker\Gateway;
use GatewayWorker\Register;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use Workerman\Worker;

class GatewayWorkerWin extends Command {
    protected function configure()
    {
        $this->setName('worker:gateway-win')
            ->addArgument('service', Argument::OPTIONAL, 'workerman service: gateway|register|businessworker', null)
            ->addOption('host', 'H', Option::VALUE_OPTIONAL, 'the host of workerman server', null)
            ->addOption('port', 'P', Option::VALUE_OPTIONAL, 'the port of workerman server', null)
            ->addOption('daemon', 'd', Option::VALUE_OPTIONAL, 'Run the workerman server in daemon mode.')
            ->setDescription('GatewayWorker Server for ThinkPHP');
    }

    public function execute(Input $input, Output $output)
    {
        $service = $input->getArgument('service');

        $option = Config::get('gateway_worker');

        if ($input->hasOption('host')) {
            $host = $input->getOption('host');
        } else {
            $host = !empty($option['host']) ? $option['host'] : '0.0.0.0';
        }

        if ($input->hasOption('port')) {
            $port = $input->getOption('port');
        } else {
            $port = !empty($option['port']) ? $option['port'] : '2347';
        }

        $registerAddress = !empty($option['registerAddress']) ? $option['registerAddress'] : '127.0.0.1:1236';
        switch ($service) {
            case 'register':
                $this->register($registerAddress);
                break;
            case 'businessworker':
                $this->businessWorker($registerAddress, isset($option['businessWorker']) ? $option['businessWorker'] : []);
                break;
            case 'gateway':
                $this->gateway($registerAddress, $host, $port, $option);
                break;
            default:
                $output->writeln("<error>Invalid argument action:{$service}, Expected gateway|register|businessworker .</error>");
                exit(1);
                break;
        }

        Worker::runAll();
    }

    /**
     * 启动register
     * @access public
     * @param  string   $registerAddress
     * @return void
     */
    public function register($registerAddress)
    {
        // 初始化register
        new Register('text://' . $registerAddress);
    }

    /**
     * 启动businessWorker
     * @access public
     * @param  string   $registerAddress registerAddress
     * @param  array    $option 参数
     * @return void
     */
    public function businessWorker($registerAddress, $option = [])
    {
        // 初始化 bussinessWorker 进程
        $worker = new BusinessWorker();

        $this->option($worker, $option);

        $worker->registerAddress = $registerAddress;
    }

    /**
     * 启动gateway
     * @access public
     * @param  string   $registerAddress registerAddress
     * @param  string   $host 服务地址
     * @param  integer  $port 监听端口
     * @param  array    $option 参数
     * @return void
     */
    public function gateway($registerAddress, $host, $port, $option = [])
    {
        // 初始化 gateway 进程
        if (!empty($option['socket'])) {
            $socket = $option['socket'];
            unset($option['socket']);
        } else {
            $protocol = !empty($option['protocol']) ? $option['protocol'] : 'websocket';
            $socket   = $protocol . '://' . $host . ':' . $port;
            unset($option['host'], $option['port'], $option['protocol']);
        }

        $gateway = new Gateway($socket, isset($option['context']) ? $option['context'] : []);

        // 以下设置参数都可以在配置文件中重新定义覆盖
        $gateway->name                 = 'Gateway';
        $gateway->count                = 4;
        $gateway->lanIp                = '127.0.0.1';
        $gateway->startPort            = 2000;
        $gateway->pingInterval         = 30;
        $gateway->pingNotResponseLimit = 0;
        $gateway->pingData             = '{"type":"ping"}';
        $gateway->registerAddress      = $registerAddress;

        // 全局静态属性设置
        foreach ($option as $name => $val) {
            if (in_array($name, ['stdoutFile', 'daemonize', 'pidFile', 'logFile'])) {
                Worker::${$name} = $val;
                unset($option[$name]);
            }
        }

        $this->option($gateway, $option);
    }

    /**
     * 设置参数
     * @access protected
     * @param  Worker   $worker Worker对象
     * @param  array    $option 参数
     * @return void
     */
    protected function option($worker, array $option = [])
    {
        // 设置参数
        if (!empty($option)) {
            foreach ($option as $key => $val) {
                $worker->$key = $val;
            }
        }
    }
}