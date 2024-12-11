<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SongService;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramBotController extends Controller
{
    public $telegram;
    public $message;
    public $user;
    public $chatId;

    public function __construct()
    {
        $this->telegram = Telegram::bot('mybot');

        $update = $this->telegram->getWebhookUpdate();

        $this->message = $update->getMessage();

        $this->user = $this->message->from;

        $this->chatId = $this->message->getChat()->getId();
    }

    public function handle(Request $request, TelegramBotService $telegramBotService, SongService $songService)
    {
        try {
            // get user by telegram username 
            $user = User::firstWhere('telegram_username', $this->user->username);
            if ($this->message->getText() === '/start') {
                $this->sendWelcomeMessage($user?->id);
            } elseif ($this->message->getAudio()) {
                // user is'nt registered
                if (!$user) {
                    $this->telegram->sendMessage([
                        'chat_id' => $this->chatId,
                        'text' => "You are not registered. \n Please send message to t.me/p_nightwolf"
                    ]);
                    return;
                }

                // get audio
                $audio = $this->message->getAudio();

                // get file
                $fileId = $audio->getFileId();

                // send loading text
                $this->telegram->sendMessage([
                    'chat_id' => $this->chatId,
                    'text' => "Uploading {$audio->getTitle()}...",
                ]);

                // get file url
                $file = $this->telegram->getFile(['file_id' => $fileId]);
                $fileUrl = $telegramBotService::getFileUrl($file);

                //  check for upload limitation
                if (!$user->canUpload($audio->getFileSize())) {
                    $this->telegram->sendMessage([
                        'chat_id' => $this->chatId,
                        'text' => "🔥 You have reached your upload limit 3GB  \n Please send message to t.me/p_nightwolf",
                    ]);
                    return;
                }

                $song = $songService->createSongFromTelegramBot($fileUrl, $audio, $user);

                // response success message
                $this->telegram->sendMessage([
                    'chat_id' => $this->chatId,
                    'text' => "🟢 Song has been uploaded successfully. \n 🎧 Song:\n {$song->direct_link}",
                ]);

                return;
            } else {
                $this->commandNotFound();
                return;
            }
        } catch (\Throwable $th) {
            $this->telegram->sendMessage([
                'chat_id' => $this->chatId,
                'text' => $th->getMessage() . ' in line:' . $th->getLine(),
            ]);
            return;
        }
    }

    /**
     * send welcome message 
     * @param int|null $userId
     * @return void
     */
    public function sendWelcomeMessage(int|null $userId)
    {
        $welcomeText = "👋 Hello {$this->user->username}. \n\n";

        if ($userId) {
            $welcomeText .= "🔼 Please send me a song file to upload it. \n\n";
            $welcomeText .= "👉 Maximum size due telegram limitation is 20MB.";
        } else {
            $welcomeText .= "😥 Your account is'nt registered t.me/p_nightwolf";
        }


        $this->telegram->sendMessage([
            'chat_id' => $this->chatId,
            'text' => $welcomeText,
        ]);
    }


    public function commandNotFound()
    {
        $this->telegram->sendMessage([
            'chat_id' => $this->chatId,
            'text' => "😐 hmmm what are you talking about? Send me a song",
        ]);
    }


}
