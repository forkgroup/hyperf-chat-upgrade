<?php

declare(strict_types=1);
/**
 *
 * This file is part of the My App.
 *
 * Copyright CodingHePing 2016-2020.
 *
 * This is my open source code, please do not use it for commercial applications.
 *
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code
 *
 * @author CodingHePing<847050412@qq.com>
 * @link   https://github.com/codingheping/hyperf-chat-upgrade
 */
namespace App\Controller;

use App\Amqp\Producer\ChatProducer;
use App\Component\MessageParser;
use App\Helper\StringHelper;
use App\Service\GroupService;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\RateLimit\Annotation\RateLimit;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\Exception\ParallelExecutionException;
use Hyperf\Utils\Parallel;
use Hyperf\WebSocketClient\ClientFactory;
use Hyperf\WebSocketClient\Frame;

/**
 * @Controller(prefix="index")
 */
class IndexController extends AbstractController
{
    /**
     * @Inject
     * @var ClientFactory
     */
    protected $clientFactory;

    /**
     * @Inject
     * @var \Hyperf\Contract\StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var \Hyperf\Amqp\Producer
     */
    protected $producer;

    /**
     * @Inject
     * @var \Phper666\JWTAuth\JWT
     */
    protected $jwt;

    /**
     * @var
     */
    protected $pro;

    /**
     * @RequestMapping(path="index")
     */
    public function index()
    {
        return 'hello world';
        //                for ($i=0;$i<10000;$i++){
        //                    $message = new ChatProducer("$i");
        //                    var_dump($this->producer->produce($message));
        //                }
        //
        //                var_dump(AnnotationCollector::get(static::class));
        //                for ($i = 0;$i<100;$i++){
        //                    Coroutine::create(function (){
        //                        // 对端服务的地址，如没有提供 ws:// 或 wss:// 前缀，则默认补充 ws://
        //                        $host = '127.0.0.1:9502';
        //                        // 通过 ClientFactory 创建 Client 对象，创建出来的对象为短生命周期对象
        //                        $client = $this->clientFactory->create($host,false);
        //                        // 向 WebSocket 服务端发送消息
        //                        $client->push('HttpServer 中使用 WebSocket Client 发送数据。'.StringHelper::randString(15));
        //                        // 获取服务端响应的消息，服务端需要通过 push 向本客户端的 fd 投递消息，才能获取；以下设置超时时间 2s，接收到的数据类型为 Frame 对象。
        //                        /** @var Frame $msg */
        //                        $msg = $client->recv(2);
        //                        // 获取文本数据：$res_msg->data
        //                        $msg->data;
        //                    });
        //
        //                }
    }

    /**
     * @RequestMapping(path="token")
     */
    public function token()
    {
        dump(Coroutine::id());
        $username = $this->request->input('username', 'user');
        $password = $this->request->input('password', 'pass');
        if ($username && $password) {
            $userData = [
                'cloud_uid' => 1, // 如果使用单点登录，必须存在配置文件中的sso_key的值，一般设置为用户的id
                'username' => 'xx',
            ];
            // 使用默认场景登录
            $token = $this->jwt->setScene('cloud')->getToken($userData);
            $data = [
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'token' => $token,
                    'exp' => $this->jwt->getTTL(),
                ],
            ];
            return $this->response->success($data);
        }
        return null;
    }

    /**
     * @RequestMapping(path="check-token")
     */
    public function checkToken()
    {
        $token = $this->request->input('token');
        var_dump($this->jwt->checkToken($token));
        var_dump($this->jwt->getParserData($token));
    }

    /**
     * @RequestMapping(path="rate-limit")
     * @RateLimit(create=1, consume=2, capacity=2, waitTimeout=3, limitCallback={TestController::class, "limitCallback"})
     */
    public function rateLimit()
    {
        sleep(1);
        return ['QPS 2, 峰值2'];
    }

    public static function limitCallback(float $seconds, ProceedingJoinPoint $proceedingJoinPoint)
    {
        var_dump($seconds);
        // $seconds 下次生成Token 的间隔, 单位为秒
        // $proceedingJoinPoint 此次请求执行的切入点
        // 可以通过调用 `$proceedingJoinPoint->process()` 继续执行或者自行处理
        return $proceedingJoinPoint->process();
    }

    /**
     * @RequestMapping(path="group")
     */
    public function group()
    {
        //TODO 1.根据groupid获取uid
        //        $groupUids = make(GroupService::class)->getGroupUid(1);
        //        $groupUids = array_column($groupUids, 'user_id');
        //        $groupUids = [
        //            1,
        //            2,
        //            5,
        //            6
        //        ];
        //        $ips       = [
        //            '127.0.0.1',
        //        ];
        //        var_dump(array_rand($ips));
        //        //TODO 2.根据ip获取uid
        //        $ipuids = BindingDependency::getIpUid('127.0.0.1');
        //        $ipUids = array_intersect($groupUids, $ipuids);
        //        //TODO 3.取出uid对应的fd
        //        $fds = BindingDependency::fds($ipUids);
        //        var_dump($fds);
        //TODO 1.根据groupid获取uid
        $groupUids = make(GroupService::class)->getGroupUid(1);
        $groupUids = array_column($groupUids, 'user_id');
        /**
         * @var array $ips
         */
        $serverIps = (config('websocket_server_ips'));
        $ips = array_values($serverIps);
        $parallelCnt = count($ips);

        //利用swoole wait_group
        //创建N个协程并行
        $parallels = new Parallel($parallelCnt);
        foreach ($serverIps as $server => $ip) {
            $parallels->add(function () use ($ip, $server, $groupUids) {
                if (empty($fds)) {
                    //协程内抛出异常
                    throw new ParallelExecutionException(sprintf('Server:[%s]服务器暂时无该群组[%s]的人员!', $server, 1));
                }
                //返回结果
                return true;
            }, $server);
        }
        try {
            //执行协程
            return $parallels->wait();
        } catch (ParallelExecutionException $e) {
            /**
             * @var ParallelExecutionException $ex
             */
            //捕获返回的异常
            foreach ($e->getThrowables() as $server => $ex) {
                $this->logger->error(sprintf('群组推送消息发生错误:%s[%s] in %s', $server, $ex->getMessage(), $ex->getLine(), $ex->getFile()));
            }
            //获取返回的结果
            foreach ($e->getResults() as $server => $result) {
                $this->logger->info(sprintf('群组推送消息[%s]结果为[%s]', $server, $result === true ? '成功' : '失败'));
            }
            return $e->getResults();
        }
    }

    /**
     * @RequestMapping(path="json")
     */
    public function json()
    {
        $ips = config('websocket_server_ips');
        dump($ips);
        $data = [
            'hello' => 'word',
            'word' => 'hello',
        ];
        $startTime = microtime(true);
        $json = json_encode($data);
        dump(json_decode($json, true));
        $endTime = microtime(true);
        echo 'php_json执行了' . ($endTime - $startTime) * 1000 . ' ms' . PHP_EOL;
    }

    /**
     * @RequestMapping(path="swoolejson")
     */
    public function swoolejson()
    {
        $data1 = [
            'hello' => 'word1',
            'word' => 'hello1',
        ];
        $startTime1 = microtime(true);
        $json1 = json_encode($data1);
        dump(MessageParser::decode($json1));
        $endTime1 = microtime(true);
        echo 'swoole_json执行了' . ($endTime1 - $startTime1) * 1000 . ' ms' . PHP_EOL;
    }
}
