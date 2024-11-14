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


        $userId = 1;

        if ($message->getText() === '/start') {
            $welcomeText = "Hello {$user->username} \n $userId Please send me a song file to upload it.";
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $welcomeText,
            ]);
        } elseif ($message->getAudio()) {
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
            [$path, $_] = SongService::uploadSong($fileUrl);

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
                'cover'=>$cover,
            ]);


            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "file size is: $fileSize \n filename is $fileName \n artist $artist",
            ]);

        }
    }

}
