<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Models\User;
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

            // check for payload and return 
            if (str_starts_with($this->message->getText(), '/start ') || str_starts_with($this->message->getText(), '/dl_')) {
                $this->handlePayload($this->chatId, $this->message->getText());
                return;
            }

            // send start message and return
            if ($this->message->getText() === '/start') {
                $telegramBotService->sendWelcomeMessage($this->telegram, $this->chatId, $user?->id, $this->account->username);
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
                $response = $telegramBotService->aiResponseBaseOnUserData($aiService, $this->message->getText());
                $this->telegram->sendMessage( [
                    'chat_id' => $this->chatId,
                    'text' => $response['message'],
                    'parse_mode'=>$response['type'] === 'link' ? 'HTML' : "Markdown",
                ]);
                return;
            }

            $telegramBotService->commandNotFound($this->telegram, $this->chatId);
            return;

        } catch (\Throwable $th) {
            $this->telegram->sendMessage([
                'chat_id' => $this->chatId,
                //'text' => "Some thing went wrong. If you think this is a bug contact support please.",
                'text' => $th->getMessage() . " - " . $th->getLine(),
            ]);
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

    public function sendSongToTelegram($songId)
    {
        $this->telegram->sendMessage([
            'chat_id' => $this->chatId,
            'text' => "Song id is: $songId",
        ]);
    }

    public function handlePayload($chatId, string $payload)
    {
        [$command, $songId] = explode('_', $payload);

        $song = Song::firstWhere('id', $songId);

        $songUrl = config('app.app_url') . "/storage/{$song->path}";

        $params = [
            'chat_id' => $chatId,
            'audio' => InputFile::create($songUrl),
            'caption' => "[🟣 Myplaylists](https://t.me/myplaylists_ir)",
            'parse_mode' => 'Markdown'
        ];

        $params['title'] = $song->name;
        $params['performer'] = $song->artist;
        $params['thumb'] = InputFile::create($song->cover);

        $this->telegram->sendAudio($params);
    }
}
