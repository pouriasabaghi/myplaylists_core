<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Models\User;
use App\Services\SongService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Telegram\Bot\Laravel\Facades\Telegram;
use Owenoj\LaravelGetId3\GetId3;

class TelegramBotController extends Controller
{
    public function handle(Request $request)
    {
        $telegram = Telegram::bot('mybot');



        $update = $telegram->getWebhookUpdate();
        $message = $update->getMessage();
        $user = $message->from;
        $chatId = $message->getChat()->getId();


        $userId = User::firstWhere('name', $user->username)?->id;



        if ($message->getText() === '/start') {
            $welcomeText = "👋 Hello {$user->username}" . PHP_EOL . PHP_EOL;

            if ($userId) {
                $welcomeText .= "🔼 Please send me a song file to upload it." . PHP_EOL . PHP_EOL;
                $welcomeText .= "👉 Maximum size due telegram limitation is 20MB" . PHP_EOL . PHP_EOL;
            } else {
                $welcomeText .= "😥 Your account is'nt registered t.me/@p_nightwolf";
            }



            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $welcomeText,
            ]);
        } elseif ($message->getAudio()) {

            if (!$userId) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "You are not registered. \n Please send message to t.me/@p_nightwolf"
                ]);
                return;
            }

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "Uploading file....",
            ]);

            $audio = $message->getAudio();
            $fileId = $audio->getFileId();
            $fileSize = $audio->getFileSize();
            $fileName = $audio->getTitle();
            $artist = $audio->getPerformer();

            $file = $telegram->getFile(['file_id' => $fileId]);

            $fileUrl = 'https://api.telegram.org/file/bot' . env('TELEGRAM_BOT_TOKEN') . '/' . $file->getFilePath();
            [$path] = SongService::uploadSong($fileUrl);

            // Get metadata
            $track = GetId3::fromDiskAndPath('public', $path);
            $info = $track->extractInfo();

            // Upload cover
            $comments = $info['comments'];
            $cover = SongService::uploadCover($comments);

            Song::create([
                'user_id' => $userId,
                'path' => $path,
                'name' => $audio->getTitle(),
                'artist' => $audio->getPerformer(),
                'size' => $audio->getFileSize(),
                'duration' => $audio->getDuration(),
                'cover' => $cover,
            ]);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "🟢 Song has been uploaded successfully.",
            ]);

        }else{
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "😐 hmmm what are you talking about?",
            ]);
        }
    }

}
