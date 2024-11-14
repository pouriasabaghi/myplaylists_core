<?php

namespace App\Http\Controllers;

use App\Services\SongService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramBotController extends Controller
{
    public function handle(Request $request)
    {
        $telegram = Telegram::bot('mybot');



        $update = $telegram->getWebhookUpdate();
        $message = $update->getMessage();
        $user = $message->from;
        $chatId = $message->getChat()->getId();

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Hello {$user->username}",
        ]);


        if ($message->getText()) {
            // دریافت ایمیل و رمز عبور برای ثبت‌نام
            // اعتبارسنجی و ذخیره کاربر در دیتابیس

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'text arrived',
            ]);
        } elseif ($message->getAudio()) {


            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'file arrived',
            ]);

            $fileId = $message->getAudio()->getFileId();

            $file = $telegram->getFile(['file_id' => $fileId]);

            $fileUrl = 'https://api.telegram.org/file/bot' . env('TELEGRAM_BOT_TOKEN') . '/' . $file->getFilePath();

            [$path, $filename] = SongService::uploadSong($fileUrl);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "file path is $path and filename is $filename",
            ]);

        }
    }

}
