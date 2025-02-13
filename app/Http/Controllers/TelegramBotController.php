<?php

namespace App\Http\Controllers;

use App\Interfaces\AiInterface;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use App\Services\SongService;
use App\Services\TelegramBotService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Keyboard\Keyboard;

class TelegramBotController extends Controller
{
    public $telegram;
    public $message;
    public $account;
    public $chatId;
    public $update;
    public $user;

    public function __construct()
    {
        $this->telegram = Telegram::bot('mybot');

        $this->update = $this->telegram->getWebhookUpdate();

        $this->message = $this->update->getMessage();

        $this->account = $this->message->from;

        $this->chatId = $this->message->getChat()->getId();
    }

    public function handle(Request $request, TelegramBotService $telegramBotService, SongService $songService, AiInterface $aiService)
    {
        try {
            // get user by telegram username 
            $user = $this->getUser($this->account->username);

            if ($this->update->has('callback_query')) {
                $callbackQuery = $this->update->getCallbackQuery();
                $callbackData = $callbackQuery->getData();

                $this->runCommand($callbackData);
                return;
            }

            if ($this->message->getText() === '/start') {
                $this->sendWelcomeMessage($user?->id);
            } elseif ($this->message->getAudio()) {
                // user isn't registered
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
                $songUploadingMessage = $this->telegram->sendMessage([
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
                $songUploadedMessage = $this->telegram->sendMessage([
                    'chat_id' => $this->chatId,
                    'text' => "🟢 Song has been uploaded successfully. wait for link....",
                ]);

                //  delete and cleanup messages
                $this->telegram->deleteMessage([
                    'chat_id' => $this->chatId,
                    'message_id' => $songUploadingMessage->getMessageId(),
                ]);
                $this->telegram->deleteMessage([
                    'chat_id' => $this->chatId,
                    'message_id' => $songUploadedMessage->getMessageId(),
                ]);


                $this->telegram->sendMessage([
                    'chat_id' => $this->chatId,
                    'text' => "🎧 Song:\n {$song->name} \n {$song->direct_link}",
                    'reply_markup' => Keyboard::make([
                        'inline_keyboard' => [
                            [
                                ['text' => 'Add To Playlist', 'callback_data' => "showPlaylists:{$user->telegram_username}:{$song->id}"],
                            ]
                        ]
                    ])
                ]);

                return;
            } else {
                if ($user && is_string($this->message->getText())) {
                    $text = $this->aiResponseBaseOnUserData($aiService, $this->message->getText());
                    $this->telegram->sendMessage([
                        'chat_id' => $this->chatId,
                        'text' => $text,
                    ]);
                    return;
                }

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
        $welcomeText = "👋 Hello {$this->account->username}. \n\n";

        if ($userId) {
            $welcomeText .= "🔼 Please send me a song file to upload it. \n\n";
            $welcomeText .= "👉 Maximum size due telegram limitation is 20MB.";
        } else {
            $welcomeText .= "😥 Your account isn't registered t.me/p_nightwolf";
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

    public function aiResponseBaseOnUserData(AiInterface $aiService, string $userAskedRequest)
    {
        $prompt = config('app.ai_search_prompt');
        $prompt .= $userAskedRequest;
        $aiResponse = $aiService->generateContent($prompt);
        $arrayResponse = $aiService->textJsonToArray($aiResponse);

        if (is_array($arrayResponse) && !empty($arrayResponse['type']) && !empty($arrayResponse['value'])) {
            $type = $arrayResponse['type'];
            $value = $arrayResponse['value'];

            if ($type === 'link') {
                $songs = Song::where('name', 'like', "%{$value}%")->orWhere('artist', 'like', "%{$value}%")->take(20)->get(['id', 'name', 'lyrics', 'artist']);
                $message = $this->responseMessage($songs, 'link');
                return $message;
            }

            if ($type === 'lyrics') {
                $songs = Song::where('name', 'like', "%{$value}%")->take(1)->get(['id', 'name', 'lyrics', 'artist']);
                $message = $this->responseMessage($songs, 'lyrics');
                return $message;
            }

            if ($type === 'playlist') {
                $playlists = Playlist::where('name', 'like', "%{$value}%")->take(20)->get(['id', 'name']);
                $message = $this->responseMessage($playlists, 'playlist');
                return $message;
            }


            throw new \Exception('Invalid type', 400);
        }

        return "Sorry I can't understand your request 😥";
    }

    protected function responseMessage(Collection $data, string $type = '')
    {
        if (!$data->count())
            return $message = "Sorry I didn't find any thing 🔎";

        $message = '';

        if ($type === 'playlist') {
            if ($data->count() > 1)
                $message = "🟣 Here is founded playlists: \n\n";
            foreach ($data as $key => $playlist) {
                $key++;
                $message .= "$key. 🎶 {$playlist->name} \n 🔗 {$playlist->directLink} \n\n";
            }
        }

        if ($type === 'link') {
            if ($data->count() > 1)
                $message = "🟣 Here is founded songs: \n\n";

            foreach ($data as $key => $song) {
                $key++;
                $message .= "$key. 🎧 {$song->name} \n 🔗 {$song->directLink} \n\n";
            }
        }

        if ($type === 'lyrics') {
            foreach ($data as $song) {
                $message .= "🎧 {$song->name} \n 🔗 {$song->directLink} \n\n {$song->lyrics}";
            }
        }

        return $message;
    }

    public function getUser($telegramUsername)
    {
        return User::firstWhere('telegram_username', $telegramUsername);
    }

    public function playlistsInlineKeyboard($user, $buttonsPerRow = 2, $additionalData = null)
    {
        $playlists = $user->playlists;
        $buttons = [];


        foreach ($playlists as $playlist) {
            $callbackData = "addToPlaylist:{$user->telegram_username}:{$playlist->id}";

            if ($additionalData)
                $callbackData .= (string) $additionalData;

            $buttons[] = [
                'text' => $playlist->name,
                'callback_data' => $callbackData,
            ];
        }

        // buttons per row 
        return array_chunk($buttons, $buttonsPerRow);
    }

    public function runCommand($command)
    {
        $command = explode(':', $command);

        $commandName = $command[0];

        unset($command[0]);
        $commandData = $command;

        call_user_func([$this, $commandName], ...$commandData);
    }

    public function showPlaylists($telegramUsername, $songId)
    {
        $user = $this->getUser($telegramUsername);
        $playlistsInlineKeyboard = $this->playlistsInlineKeyboard($user, 4, ":$songId");

        $replyMarkup = Keyboard::make([
            'inline_keyboard' => $playlistsInlineKeyboard
        ]);
        $this->telegram->sendMessage([
            'chat_id' => $this->chatId,
            'text' => "Select your playlist 👇",
            'reply_markup' => $replyMarkup,
        ]);
    }

    public function addToPlaylist($telegramUsername, $playlistId, $songId)
    {
        $user = $this->getUser($telegramUsername);
        $playlist = Playlist::findOrFail($playlistId);
        $song = Song::findOrFail($songId);

        if ($song->user_id !== $user->id || $playlist->user_id !== $user->id) {
            $this->telegram->sendMessage([
                'chat_id' => $this->chatId,
                'text' => "Only owner can make these changes",
            ]);
            return;
        }

        $playlist->songs()->attach($song);
        $this->telegram->sendMessage([
            'chat_id' => $this->chatId,
            'text' => "✅ Song added to playlist successfully",
        ]);
        return;
    }
}
