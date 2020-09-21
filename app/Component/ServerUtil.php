<?php
declare(strict_types = 1);

/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */
namespace App\Component;

use Hyperf\Utils\Coroutine;
use Hyperf\WebSocketServer\Sender;
use Swoole\Server;

class ServerUtil
{
    /**
     * @param       $data
     * @param array $fds
     */
    public static function sendToAll($data, array $fds = []):void
    {
        foreach ($fds as $fd) {
            self::push($fd, $data);
        }
    }

    /**
     * @param     $data
     * @param int $fd
     *
     * @return mixed
     */
    public static function push($data, int $fd)
    {
        return di(Sender::class)->push($fd, $data);
    }

    /**
     * Disconnect for client, will trigger onClose
     *
     * @param int    $fd
     * @param int    $code
     * @param string $reason
     *
     * @return bool|mixed
     */
    public static function disconnect(int $fd, int $code = 0, string $reason = '')
    {
        return di(Sender::class)->disconnect($fd, $code, $reason);
    }

    /**
     * @param int $fd
     *
     * @return void
     */
    public static function close(int $fd) : void
    {
        if (Coroutine::inCoroutine()) {
            Coroutine::create(function () use ($fd)
            {
                self::disconnect($fd);
            });
        }
        di(Server::class)->close($fd);
    }
}

