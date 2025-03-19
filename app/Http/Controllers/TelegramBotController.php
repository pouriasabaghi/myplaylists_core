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
            $language = TelegramUser::firstWhere('chat_id', $this->chatId)->language ?? 'en';

            // check for callback queries and return 
            if ($this->update->has('callback_query')) {
                $callbackQuery = $this->update->getCallbackQuery();
                $callbackData = $callbackQuery->getData();

                $this->runCommand($callbackQuery, $callbackData);
                $this->telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->id,
                ]);
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

            if (in_array($this->message->getText(), ['👤 Support', '🔑 Access', "✈️ Tour"])) {
                if ($this->message->getText() === '👤 Support') {
                    $this->telegram->sendMessage([
                        "chat_id" => $this->chatId,
                        "text" => "👤 Support: @p_nightwolf"
                    ]);
                }

                if ($this->message->getText() === '🔑 Access') {
                    $this->telegram->sendMessage([
                        "chat_id" => $this->chatId,
                        "text" => __("message.need_access",[], $language),
                        "reply_markup" => Keyboard::make([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => __("message.token_button",[], $language),
                                        'url' => config("app.frontend_url") . "/songs/upload",
                                    ]
                                ]
                            ]
                        ]),
                    ]);
                }

                if ($this->message->getText() === "✈️ Tour") {
                    $telegramBotService->sendWelcomeMessage($this->telegram, $this->chatId, $user?->id, $this->account->username);
                }

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

    public function runCommand($callbackQuery, $command)
    {
        $command = explode(':', $command);

        $commandName = $command[0];

        // replace command with callback
        $command[0] = $callbackQuery;

        call_user_func([$this, $commandName], ...$command);
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

    public function getUser($telegramUsername)
    {
        return User::firstWhere('telegram_username', $telegramUsername);
    }

    /**
     * Commands
     * contains ":"
     */

    // Command
    public function showPlaylists($callbackQuery, $telegramUsername, $songId)
    {
        $user = $this->getUser($telegramUsername);
        (new TelegramBotService())->showPlaylistsHandler($this->telegram, $this->chatId, $user, $songId);
    }

    // Command
    public function addToPlaylist($callbackQuery, $telegramUsername, $playlistId, $songId)
    {
        $user = $this->getUser($telegramUsername);
        (new TelegramBotService())->addToPlaylistHandler($this->telegram, $this->chatId, $user, $playlistId, $songId);
    }

    // Command: search from outer resources
    public function sOut($callbackQuery, $chatId, $identifier, $resource)
    {
        $telegramBotService = new TelegramBotService();
        if ($resource == 'youtubemusic') {
            $telegramBotService->searchForSongFromYoutubeMusic($this->telegram, $chatId, $identifier);
            return;
        }

        return;
    }

    // Command: set language
    public function setLanguage($callbackQuery, $chatId, $language)
    {
        TelegramUser::firstWhere('chat_id', $chatId)->update([
            'language' => $language
        ]);

        $message = $this->telegram->sendMessage([
            "chat_id" => $chatId,
            'text' => "Preparing tour...",
        ]);

        $messageId = $message->getMessageId();

        $this->telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => __("tour.introduction", [], $language),
            'reply_markup' => Keyboard::make([
                'inline_keyboard' => [
                    [
                        [
                            'text' => __('tour.start_button', [], $language),
                            'callback_data' => "stepHandler:next:$chatId:$messageId:0:$language"
                        ],
                    ]
                ],
            ])
        ]);
    }

    // Command: handle tour steps
    public function stepHandler($callbackQuery, $action, $chatId, $messageId, $step, $language)
    {
        $nextStep = $step + 1;
        $prevStep = $step - 1;

        $inlineKeyboards = [
            []
        ];

        // Remove prev step button if it's start
        if ($step > 0) {
            $inlineKeyboards[0][] = [
                'text' => '⬅️',
                'callback_data' => "stepHandler:prev:$chatId:$messageId:$prevStep:$language"
            ];
        }

        // add next step button
        $inlineKeyboards[0][] = [
            'text' => '➡️',
            'callback_data' => "stepHandler:next:$chatId:$messageId:$nextStep:$language"
        ];

        // add try out button for instance share button
        if ($step == 6) {
            $inlineKeyboards[] = [
                [
                    'text' => __('tour.try_out_button', [], $language),
                    'switch_inline_query_current_chat' => 'back in black'
                ]
            ];
        }


        // empty inline keyboard
        if ($step == 10) {
            $inlineKeyboards = [
                [
                    [
                        'text' => __('tour.end_button', [], $language),
                        'callback_data' => "sendRulesAndLimitations:$chatId:$messageId:$language"
                    ]
                ]
            ];
        }

        // send to telegram server
        $this->telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => __("tour.steps.$step", [], $language),
            'reply_markup' => Keyboard::make([
                'inline_keyboard' => $inlineKeyboards
            ])
        ]);

    }

    public function sendRulesAndLimitations($callbackQuery, $chatId, $messageId, $language)
    {
        $this->telegram->deleteMessage([
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => __("tour.rules_and_limitations", [], $language),
        ]);
    }

    /**
     * Payloads
     * contains "_"
     */
    // Payload: download from outer resources
    public function dlOut($chatId, $identifier, $resource)
    {
        $telegramBotService = new TelegramBotService();
        if ($resource == 'youtubemusic') {
            $telegramBotService->getFromYoutubeMusic($this->telegram, $chatId, "https://www.youtube.com/watch?v=$identifier");
            return;
        }

        return;
    }

    // Payload: send song to telegram
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

    // Payload: ask access payload
    public function askAccess($chatId)
    {
        $message = "🔑 Your access key has been copied to your clipboard.Please send it to me.\n⚠️ This token expire after 60 second, If your toked expired generate new one.\n\n";
        $message .= "🔑 کلید دسترسی شما در کلیپ بورد شما ذخیره شده  است. لطفا برای من ارسال کنید.\n⚠️ این توکن تنها ۶۰ ثانیه اعتبار دارد، در صورت منقضی شدن مجدد توکن دریافت کنید.";

        $this->telegram->sendMessage([
            "chat_id" => $chatId,
            "text" => $message,
        ]);
    }

    /**
     * Static Commands 
     * contains "#"
     */

}
