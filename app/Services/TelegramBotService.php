<?php
namespace App\Services;

use Symfony\Component\HttpFoundation\JsonResponse;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\Http;
use Telegram\Bot\Objects\File;

class TelegramBotService
{
    public static function getFileUrl(File $file): JsonResponse|string
    {
        try {
            $fileUrl = 'https://api.telegram.org/file/bot' . config('telegram.bots.mybot.token') . '/' . $file->getFilePath();
            return $fileUrl;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'An error occurred while processing the file',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
