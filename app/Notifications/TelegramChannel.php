<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramChannel extends Notification
{

    public function send(object $notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toTelegram'))
            throw new \Exception(get_class($notification) . 'doesn\'t have toTelegram() method');

        $telegram = Telegram::bot('mybot');
        $data = $notification->toTelegram($notifiable);

        $params = [
            'chat_id' => $notifiable->telegram->chat_id,
            'text' => "{$data['subject']}\n{$data['message']}",
        ];

        if (isset($data['replay_markup']))
            $params['reply_markup'] = $data['replay_markup'];

        $telegram->sendMessage($params);

    }
}
