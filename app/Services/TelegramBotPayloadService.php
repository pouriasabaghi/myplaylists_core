<?php

namespace App\Services;

use App\Models\Song;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Api as TelegramBotApi;

/**
 * Payloads
 * contains "_"
 */
class TelegramBotPayloadService
{

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
        $message = "🔑 Your access key has been copied to your clipboard.Please send it to me.\n⚠️ This token expire after 60 second, If your toked expired generate new one.\n\n";
        $message .= "🔑 کلید دسترسی شما در کلیپ بورد شما ذخیره شده  است. لطفا برای من ارسال کنید.\n⚠️ این توکن تنها ۶۰ ثانیه اعتبار دارد، در صورت منقضی شدن مجدد توکن دریافت کنید.";

        $telegram->sendMessage([
            "chat_id" => $chatId,
            "text" => $message,
        ]);
    }
}