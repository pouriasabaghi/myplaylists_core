<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramBotController extends Controller
{
    public function handle(Request $request)
    {
        $telegram = new \Telegram\Bot\Api('7701211643:AAG_0AJpTIdosp2o9-biVYJBYmb1Qw6XmC0');
    
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
