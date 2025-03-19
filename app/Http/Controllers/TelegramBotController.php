<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Models\User;
use App\Models\TelegramUser;
use App\Services\SongService;
use App\Services\TelegramBotService;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Interfaces\AiInterface;

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

        $this->account = $this->message->isNotEmpty() && $this->message->has('from') ? $this->message->from : null; // prevent error in inline queries

        $this->chatId = $this->message->isNotEmpty() ? $this->message?->getChat()?->getId() : null; // prevent error in inline queries
    }

    public function handle(TelegramBotService $telegramBotService, SongService $songService, AiInterface $aiService)
    {
        try {
            // It's url 
            if (str_starts_with($this->message->getText(), 'https://')) {
                // supported sites
                $allowedSites = [
                    'https://soundcloud.com/' => 'soundcloud',
                    'https://m.soundcloud.com/' => 'soundcloud',
                    'https://on.soundcloud.com/' => 'soundcloud',
                    'https://music.youtube.com/' => 'youtubemusic',
                    'https://youtu.be/' => 'youtubemusic',
                    'https://www.youtube.com/' => 'youtubemusic',
                ];

                // check for supported sites
                foreach ($allowedSites as $site => $platform) {
                    if (str_starts_with($this->message->getText(), $site)) {
                        if ($platform === 'youtubemusic') {
                            $telegramBotService->getFromYoutubeMusic($this->telegram, $this->chatId, $this->message->getText());
                            return;
                        }

                        // Download from sound cloud
                        if ($platform === 'soundcloud') {
                            $telegramBotService->getFromSoundCloud($this->telegram, $this->chatId, $this->message->getText());
                            return;
                        }
                    }
                }

                // no supported site found
                $this->telegram->sendMessage([
                    'chat_id' => $this->chatId,
                    'text' => "Entered url is not valid.\nCurrently we support SoundCloud and YoutubeMusic.",
                ]);
                return;
            }

            // get user by telegram username 
            $user = $this->getUser($this->account->username);

            // check for callback queries and return 
            if ($this->update->has('callback_query')) {
                $callbackQuery = $this->update->getCallbackQuery();
                $callbackData = $callbackQuery->getData();

                $this->runCommand($callbackData);
                return;
            }

            // check for inline queries and return 
            if ($this->update->has('inline_query')) {
                $inlineQuery = $this->update->getInlineQuery();
                $queryText = $inlineQuery->get('query');

                if (empty($queryText))
                    return;

                $telegramBotService->searchSongsInlineQuery($inlineQuery, $queryText, $this->chatId);
                return;
            }

            // check for payload and return "/start " has space after start
            if (str_starts_with($this->message->getText(), '/start ')) {
                $this->handlePayload($this->chatId, $this->message->getText());
                return;
            }

            // /dl_ command means user want to download a song
            if (str_starts_with($this->message->getText(), '/dl_')) {
                [$_, $songId] = explode('_', $this->message->getText());
                $this->sendSongToTelegram($this->chatId, $songId);
                return;
            }

            // send start message and return
            if ($this->message->getText() === '/start') {
                TelegramUser::updateOrCreate(
                    ['chat_id' => $this->chatId],
                    ['username' => $this->account->username]
                );

                $telegramBotService->sendWelcomeMessage($this->telegram, $this->chatId, $user?->id, $this->account->username);
                return;
            }

            if (str_starts_with($this->message->getText(), 'getAccess#')) {
                [$_, $token] = explode('#', $this->message->getText());
                $telegramBotService->getAccess($this->telegram, $this->chatId, $token, $this->account->username);
                return;
            }

            // get audio and upload to site and return 
            if ($this->message->getAudio()) {
                // user isn't registered
                if (!$user) {
                    $this->telegram->sendMessage([
                        'chat_id' => $this->chatId,
                        'text' => "You are not registered. \n Please send message to t.me/p_nightwolf"
                    ]);
                    return;
                }

                // upload song and send success message
                $telegramBotService->uploadSongFromBotToSite($this->telegram, $this->chatId, $this->message, $user, $telegramBotService, $songService);
                return;
            }

            // user send text message handle and return 
            if (is_string($this->message->getText())) {
                $telegramBotService->searchForSongFromSiteArchive($this->telegram, $this->chatId, $this->message->getText());
                return;
            }

            $telegramBotService->commandNotFound($this->telegram, $this->chatId);
            return;

        } catch (\Throwable $th) {
            \Log::error($th->getMessage());
            if ($this->chatId) {
                $this->telegram->sendMessage([
                    'chat_id' => $this->chatId,
                    'text' => "Some thing went wrong. If you think this is a bug contact support please.",
                    //'text' => $th->getMessage() . " - " . $th->getLine() . $th->getFile(),
                ]);
            }
            return;
        }
    }

    public function getUser($telegramUsername)
    {
        return User::firstWhere('telegram_username', $telegramUsername);
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
        (new TelegramBotService())->showPlaylistsHandler($this->telegram, $this->chatId, $user, $songId);
    }

    public function addToPlaylist($telegramUsername, $playlistId, $songId)
    {
        $user = $this->getUser($telegramUsername);
        (new TelegramBotService())->addToPlaylistHandler($this->telegram, $this->chatId, $user, $playlistId, $songId);
    }

    public function handlePayload($chatId, string $payload)
    {
        // remove /"start " from payload
        if (str_starts_with($payload, "/start "))
            $payload = str_replace("/start ", "", $payload);

        $payload = explode('_', $payload);

        // get action
        $action = $payload[0];

        // replace chat_id with action
        $payload[0] = $chatId;

        call_user_func([$this, $action], ...$payload);
    }

    public function sendSongToTelegram($chatId, $songId)
    {

        $song = Song::firstWhere('id', $songId);

        $songUrl = config('app.app_url') . "/storage/{$song->path}";

        $caption = "<a href='t.me/myplaylists_ir'>🟣 MyPlaylists</a>";

        if ($song->lyrics) {
            $lyrics = mb_substr($song->lyrics, 0, 1000, 'utf-8') . "...";
            $caption = "<blockquote expandable>$lyrics</blockquote>\n<a href='t.me/myplaylists_ir'>🟣 MyPlaylists</a>";
        }

        $params = [
            'chat_id' => $chatId,
            'audio' => InputFile::create($songUrl),
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];

        $params['title'] = $song->name;
        $params['performer'] = $song->artist;
        $params['thumb'] = InputFile::create($song->cover);

        $this->telegram->sendAudio($params);
    }

    // download form outer resources
    public function dlOut($chatId, $identifier, $resource)
    {
        $telegramBotService = new TelegramBotService();
        if ($resource == 'youtubemusic') {
            $telegramBotService->getFromYoutubeMusic($this->telegram, $chatId, "https://www.youtube.com/watch?v=$identifier");
            return;
        }

        return;
    }

    // search from outer resources
    public function sOut($chatId, $identifier, $resource)
    {
        $telegramBotService = new TelegramBotService();
        if ($resource == 'youtubemusic') {
            $telegramBotService->searchForSongFromYoutubeMusic($this->telegram, $chatId, $identifier);
            return;
        }

        return;
    }

    // ask access payload
    public function askAccess($chatId)
    {
        $message = "🔑 Your access key has been copied to your clipboard.Please send it to me.\n⚠️ This token expire after 60 second, If your toked expired generate new one.\n\n";
        $message .= "🔑 کلید دسترسی شما در کلیپ بورد شما ذخیره شده  است. لطفا برای من ارسال کنید.\n⚠️ این توکن تنها ۶۰ ثانیه اعتبار دارد، در صورت منقضی شدن مجدد توکن دریافت کنید.";

        $this->telegram->sendMessage([
            "chat_id" => $chatId,
            "text" => $message,
        ]);
    }
}
