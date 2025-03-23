<?php

namespace App\Services;

use App\Models\TelegramUser;
use Telegram\Bot\Api as TelegramBotApi;
use Telegram\Bot\Keyboard\Keyboard;
use App\Traits\TelegramBotTrait;

/**
 * Payloads
 * contains "_"
 */
class TelegramBotPayloadService
{
    use TelegramBotTrait;

    /**
     * Payload: send song to telegram
     * @param \Telegram\Bot\Api $telegram
     * @param int $chatId
     * @param int $songId
     * @return void
     */
    public function sendSongToTelegram(TelegramBotApi $telegram, int $chatId, int $songId)
    {
        (new TelegramBotService())->sendSongToTelegram($telegram, $chatId, $songId);
    }

    /**
     * Payload: ask access payload
     * @param \Telegram\Bot\Api $telegram
     * @param int $chatId
     * @return void
     */
    public function askAccess(TelegramBotApi $telegram, int $chatId)
    {
        $language = $this->getChat($chatId)?->language;

        $telegram->sendMessage([
            "chat_id" => $chatId,
            "text" => __("message.access_alert", [], $language),
        ]);
    }


    public function login(TelegramBotApi $telegram, int $chatId)
    {
        $language = $this->getChat($chatId)?->language;
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => __('message.telegram_login', [], $language),
            "reply_markup" => Keyboard::make([
                'inline_keyboard' => [
                    [
                        [
                            'text' => __('message.login_button', [], $language),
                            'login_url' => [
                                'url' => config("app.app_url") . "/telegram-auth",
                            ],
                        ]
                    ]
                ]
            ]),
        ]);
    }
}