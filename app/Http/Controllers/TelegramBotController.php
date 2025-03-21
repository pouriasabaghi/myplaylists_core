<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\TelegramUser;
use App\Services\SongService;
use App\Services\TelegramBotService;
use App\Services\TelegramBotCallbackQueryService;
use App\Services\TelegramBotPayloadService;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Interfaces\AiInterface;
use Telegram\Bot\Keyboard\Keyboard;
use App\Traits\TelegramBotTrait;

class TelegramBotController extends Controller
{
    use TelegramBotTrait;
    public $telegram;
    public $message;
    public $account;
    public $chatId;
    public $update;

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
            // user sended message value
            $messageValue = $this->message->getText();

            // it's url 
            if (str_starts_with($messageValue, 'https://')) {
                $telegramBotService->getSongFromEnteredResourceUrl($this->telegram, $this->chatId, $messageValue);
                return;
            }

            // get user by telegram username 
            $user = $this->getUser($this->account->username);
            $language = TelegramUser::firstWhere('chat_id', $this->chatId)->language ?? 'en';

            // check for callback queries and return 
            if ($this->update->has('callback_query')) {
                $callbackQuery = $this->update->getCallbackQuery();
                $callbackData = $callbackQuery->getData();

                $this->callbackQueryHandler(new TelegramBotCallbackQueryService(), $callbackQuery, $callbackData);
                $this->telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->id,
                ]);
                return;
            }

            // check for inline queries and return 
            if ($this->update->has('inline_query')) {
                $inlineQuery = $this->update->getInlineQuery();
                $queryText = $inlineQuery->get('query');

                $telegramBotService->searchSongsInlineQuery($inlineQuery, $queryText);
                return;
            }

            // check for payload and return "/start " has space after start
            if (str_starts_with($this->message->getText(), '/start ')) {
                $this->payloadHandler(new TelegramBotPayloadService(), $messageValue);
                return;
            }

            // /dl_ command means user want to download a song
            if (str_starts_with($this->message->getText(), '/dl_')) {
                [$_, $songId] = explode('_', $this->message->getText());
                $telegramBotService->sendSongToTelegram($this->telegram, $this->chatId, $songId);
                return;
            }

            // send start message and return
            if ($this->message->getText() === '/start') {
                $telegramBotService->sendWelcomeMessage($this->telegram, $this->chatId, $user?->id, $this->account->username);
                return;
            }

            // get access | this is static command
            if (str_starts_with($this->message->getText(), 'getAccess#')) {
                [$_, $token] = explode('#', $this->message->getText());
                $telegramBotService->getAccess($this->telegram, $this->chatId, $token, $this->account->username);
                return;
            }

            // handle keyboard messages
            if ($this->isKeyboardMessage($messageValue)) {
                $this->keyboardHandler($messageValue, $user, $language, $telegramBotService);
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
                    //'text' => "Some thing went wrong. If you think this is a bug contact support please.",
                    'text' => $th->getMessage() . " - " . $th->getLine() . $th->getFile(),
                ]);
            }
            return;
        }
    }

    public function callbackQueryHandler(TelegramBotCallbackQueryService $telegramBotCallbackQueryService, $callbackQuery, string $command)
    {
        $command = explode(':', $command);

        $commandName = $command[0];

        // replace command with callback
        $command[0] = $callbackQuery;

        // inject telegram instance and chat_id
        $commandData = [
            $this->telegram,
            $this->chatId,
            ...$command,
        ];

        call_user_func([$telegramBotCallbackQueryService, $commandName], ...$commandData);
    }

    public function payloadHandler(TelegramBotPayloadService $telegramBotPayloadService, string $payload)
    {
        // remove /"start " from payload
        if (str_starts_with($payload, "/start "))
            $payload = str_replace("/start ", "", $payload);

        $payload = explode('_', $payload);

        // get action
        $action = $payload[0];

        // unset action
        unset($payload[0]);

        // inject telegram instance and chat_id
        $payloadData = [
            $this->telegram,
            $this->chatId,
            ...$payload,
        ];

        call_user_func([$telegramBotPayloadService, $action], ...$payloadData);
    }

    public function isKeyboardMessage($messageValue)
    {
        $buttons = [
            '👤 Support',
            '🔑 Access',
            '✈️ Tour',
        ];

        return !isset($buttons[$messageValue]);
    }

    public function keyboardHandler($userEnteredKeyboardButton, $user, $language, TelegramBotService $telegramBotService)
    {
        $buttons = [
            '👤 Support' => [
                "text" => "👤 Support: @p_nightwolf",
            ],
            '🔑 Access' => [
                "text" => __("message.need_access", [], $language),
                "reply_markup" => Keyboard::make([
                    'inline_keyboard' => [
                        [
                            [
                                'text' => __("message.token_button", [], $language),
                                'url' => config("app.frontend_url") . "/songs/upload",
                            ]
                        ]
                    ]
                ]),
            ],
            '✈️ Tour' => function () use ($telegramBotService, $user) {
                $telegramBotService->sendWelcomeMessage($this->telegram, $this->chatId, $user?->id, $this->account->username);
            }
        ];
        if (isset($buttons[$userEnteredKeyboardButton])) {
            if (is_callable($buttons[$userEnteredKeyboardButton])) {
                // call if it's callable
                $buttons[$userEnteredKeyboardButton]();
            } else {
                // send message
                $this->telegram->sendMessage(array_merge(["chat_id" => $this->chatId], $buttons[$userEnteredKeyboardButton]));
            }

            return true;
        }

        return false;
    }
}
