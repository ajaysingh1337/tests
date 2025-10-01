<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class UserNotification implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $userId;
    public $title;
    public $body;
    public $deepLink;

    public function __construct($userId, $title, $body, $deepLink)
    {
        $this->userId = $userId;
        $this->title = $title;
        $this->body = $body;
        $this->deepLink = $deepLink;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('App.Models.User.' . $this->userId);
    }

    public function broadcastAs()
    {
        return 'notification';
    }
}