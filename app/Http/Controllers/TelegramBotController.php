<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Telegram\Bot\Api;


class TelegramBotController extends Controller
{
    public function handle(Request $request)
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));

        $telegram->setWebhook([
            'url'=>'https://api.myplaylists.ir/telegram',
        ]);

        return response()->json([
            'status' => 'ok',
        ]);

        $update = $telegram->getWebhookUpdate();
        $message = $update->get('message');
        $chatId = $message->getChat()->getId();
        
        $telegram->sendMessage([
            'chat_id' =>  $chatId ,
            'text' => 'آهنگ با موفقیت ارسال شد!!',
        ]);

       

        if ($message->getText()) {
            // دریافت ایمیل و رمز عبور برای ثبت‌نام
            // اعتبارسنجی و ذخیره کاربر در دیتابیس
        } elseif ($message->getAudio()) {
            // دریافت آهنگ و ارسال به API
            $fileId = $message->getAudio()->getFileId();
            $response = Http::post('https://api.myplaylists.ir/songs', [
                'file_id' => $fileId,
                // بقیه پارامترهای مورد نیاز
            ]);
    
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'آهنگ با موفقیت ارسال شد!',
            ]);
        }
    }
    
}
