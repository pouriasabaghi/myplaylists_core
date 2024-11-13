<?php 
namespace App\Services;

use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\Http;

class TelegramBotService
{
    public function handle($message)
    {
        try {
            // استخراج file_id از پیام صوتی در تلگرام
            $fileId = $message['audio']['file_id'];
            
            // دریافت اطلاعات فایل از تلگرام
            $fileResponse = Telegram::getFile(['file_id' => $fileId]);
            $filePath = $fileResponse->getFilePath();
            
            // ایجاد لینک دانلود فایل از تلگرام
            $downloadUrl = "https://api.telegram.org/file/bot" . config('telegram.bots.mybot.token') . "/" . $filePath;

            // دریافت محتوای فایل صوتی از لینک دانلود
            $fileContent = file_get_contents($downloadUrl);

            if (!$fileContent) {
                throw new \Exception("Failed to download the file from Telegram");
            }

            // ارسال فایل به API پروژه
            $apiResponse = Http::attach(
                'file', $fileContent, 'audio.mp3'
            )->post('https://api.myplaylists.ir/songs');

            // بررسی نتیجه پاسخ از API
            if ($apiResponse->successful()) {
                return response()->json([
                    'message' => 'File sent successfully to API',
                    'data' => $apiResponse->json(),
                ]);
            } else {
                throw new \Exception("Failed to send file to API: " . $apiResponse->body());
            }

        } catch (\Throwable $e) {
            // مدیریت خطا
            return response()->json([
                'message' => 'An error occurred while processing the file',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
