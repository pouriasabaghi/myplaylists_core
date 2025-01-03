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

    public function handle(Request $request, TelegramBotService $telegramBotService, SongService $songService, AiInterface $aiService)
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
                    'text' => "🟢 Song has been uploaded successfully. wait for link...",
                ]);

                $prompt = "نظرت رو راجب آهنگ {$song->name} از {$song->artist} بگو اگر احساس میکنی که نام خواننده یا آلبوم بی ربط است دقیقا این جلمه رو بگو * پیشنهاد میکنم که برای کاربری بهتر در نرم افزار حتما اطلاعات آهنگ مثل نام، اسم آلبوم و نام خواننده رو به اطلاعات صحیح ویرایش بکنید.* لطفا بدون هیچ کلمه کم زیادی متن بین * رو بگو";
                $aiOpinion = "راجب آهنگ: ";
                $aiOpinion .= $aiService->generateContent($prompt);

                if (str_contains($aiOpinion, 'پیشنهاد')) {
                    $this->telegram->sendMessage([
                        'chat_id' => $this->chatId,
                        'text' => "🎧 Song:\n {$song->direct_link}",
                    ]);
                    return;
                }

                $this->telegram->sendMessage([
                    'chat_id' => $this->chatId,
                    'text' => "🎧 Song:\n {$song->direct_link} \n \n $aiOpinion ",
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

    public function aiResponseBaseOnUserData(AiInterface $aiService, string $userAskedRequest)
    {
        $prompt = config('app.ai_prompt');
        $prompt .= $userAskedRequest;
        $aiResponse = $aiService->generateContent($prompt);
        $arrayResponse = $aiService->textJsonToArray($aiResponse);

        if (is_array($arrayResponse) && !empty($arrayResponse['type']) && !empty($arrayResponse['value'])) {
            $type = $arrayResponse['type'];
            $value = $arrayResponse['value'];

            if ($type === 'link') {
                $songs = Song::where('name', 'like', "%{$value}%")->orWhere('artist', 'like', "%{$value}%")->take(10)->get(['id', 'name', 'lyrics', 'artist']);
                $message = $this->responseMessage($songs, 'link');
                return $message;
            }

            if ($type === 'lyrics') {
                $songs = Song::where('name', 'like', "%{$value}%")->take(1)->get(['id', 'name', 'lyrics', 'artist']);
                $message = $this->responseMessage($songs, 'lyrics');
                return $message;
            }

            if ($type === 'playlist') {
                $playlists = Playlist::where('name', 'like', "%{$value}%")->take(10)->get(['id', 'name']);
                $message = $this->responseMessage($playlists, 'playlist');
                return $message;
            }


            throw new \Exception('Invalid type', 400);
        }

        return "Sorry I can't understand your request";
    }

    protected function responseMessage(Collection $data, string $type = '')
    {
        if (!$data->count())
            return $message = "Sorry i didn't find any thing";

        $message = '';

        if ($type === 'playlist') {
            if ($data->count() > 1)
                $message = "🟣 here is founded playlists: \n\n";
            foreach ($data as $key => $playlist) {
                $key++;
                $message .= "$key. 🎶 {$playlist->name} \n 🔗 {$playlist->directLink} \n\n";
            }
        }

        if ($type === 'link') {
            if ($data->count() > 1)
                $message = "🟣 here is founded songs: \n\n";

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

}
