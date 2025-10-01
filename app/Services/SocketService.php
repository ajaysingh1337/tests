<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class SocketService
{
    public static function emit($event, $data)
    {
        Redis::publish('events', json_encode([
            'event' => $event,
            'data' => $data,
        ]));
    }
}
?>