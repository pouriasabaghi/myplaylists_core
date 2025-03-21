<?php
namespace App\Services;

use App\Models\Playlist;
use App\Models\Song;
use Symfony\Component\HttpFoundation\JsonResponse;
use Telegram\Bot\Objects\File;
use App\Interfaces\AiInterface;
use Illuminate\Database\Eloquent\Collection;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\InlineQuery\InlineQueryResultArticle;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\FileUpload\InputFile;
use App\Models\User;
use App\Models\TelegramUser;

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

    public function sendWelcomeMessage($telegram, $chatId, int|null $userId, string $telegramUsername)
    {
        TelegramUser::updateOrCreate(
            ['chat_id' => $chatId],
            ['username' => $telegramUsername]
        );

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "👋 Hello {$telegramUsername}. \n\n",
            'reply_markup' => Keyboard::make([
                'keyboard' => [
                    [
                        [
                            'text' => '👤 Support',
                        ],
                        [
                            'text' => '🔑 Access',
                        ],
                        [
                            'text' => '✈️ Tour',
                        ]
                    ]
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ])
        ]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Please select your preferred language:",
            'reply_markup' => Keyboard::make([
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'فارسی 🇮🇷',
                            'callback_data' => "setLanguage:fa"
                        ],
                        [
                            'text' => 'English 🇺🇸',
                            'callback_data' => "setLanguage:en"
                        ]
                    ]
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ])
        ]);
    }

    public function commandNotFound($telegram, $chatId)
    {
        $telegram->sendMessage([
            'chat_id' => $chatId,
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
                return compact('message', 'type');
            }

            if ($type === 'lyrics') {
                $songs = Song::where('name', 'like', "%{$value}%")->take(1)->get(['id', 'name', 'lyrics', 'artist']);
                $message = $this->responseMessage($songs, 'lyrics');
                return compact('message', 'type');
            }

            if ($type === 'playlist') {
                $playlists = Playlist::where('name', 'like', "%{$value}%")->take(20)->get(['id', 'name']);
                $message = $this->responseMessage($playlists, 'playlist');
                return compact('message', 'type');
            }

            throw new \Exception('Invalid type', 400);
        }

        return "Sorry I can't understand your request 😥";
    }

    public function responseMessage(Collection $data, string $type = '')
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
                $artist = "{$song->artist} -" ?? '';
                $key++;
                $message .= "$key. 🎧 $artist {$song->name} \n🔗 {$song->directLink} \n📥 Download /dl_{$song->id}\n\n";
            }
        }

        if ($type === 'lyrics') {
            foreach ($data as $song) {
                $message .= "🎧 {$song->name} \n[🟣 Listen In Application]({$song->directLink})\n📥 [Download](https://t.me/Myplaylists_ir_Bot?start=sendSongToTelegram_{$song->id})\n\n{$song->lyrics}";
            }
        }

        return $message;
    }

    public function uploadSongFromBotToSite($telegram, $chatId, $message, $user, $telegramBotService, $songService)
    {
        // get audio
        $audio = $message->getAudio();

        // get file
        $fileId = $audio->getFileId();

        // send loading text
        $songUploadingMessage = $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Uploading {$audio->getTitle()}...",
        ]);

        // get file url
        $file = $telegram->getFile(['file_id' => $fileId]);

        $fileUrl = $telegramBotService::getFileUrl($file);

        //  check for upload limitation
        if (!$user->canUpload($audio->getFileSize())) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "🔥 You have reached your upload limit 3GB  \n Please send message to t.me/p_nightwolf",
            ]);
            return;
        }

        $song = $songService->createSongFromTelegramBot($fileUrl, $audio, $user);

        // response success message
        $songUploadedMessage = $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "🟢 Song has been uploaded successfully. wait for link....",
        ]);

        //  delete and cleanup messages
        $telegram->deleteMessage([
            'chat_id' => $chatId,
            'message_id' => $songUploadingMessage->getMessageId(),
        ]);
        $telegram->deleteMessage([
            'chat_id' => $chatId,
            'message_id' => $songUploadedMessage->getMessageId(),
        ]);


        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "🎧 Song:\n {$song->name} \n {$song->share_link}",
            'reply_markup' => Keyboard::make([
                'inline_keyboard' => [
                    [
                        ['text' => 'Add To Playlist', 'callback_data' => "showPlaylists:{$user->telegram_username}:{$song->id}"],
                    ]
                ]
            ])
        ]);
    }

    public function searchSongsInlineQuery($inlineQuery, $userEnteredText)
    {
        if (empty(trim($userEnteredText))) {
            Telegram::answerInlineQuery([
                'inline_query_id' => $inlineQuery->get('id'),
                'results' => json_encode([
                    new InlineQueryResultArticle([
                        'id' => uniqid(),
                        'title' => "نام خواننده یا آهنگ رو برام بنویس... 🙂",
                        'input_message_content' => [
                            'message_text' => "🟣 [MyPlaylists](t.me/Myplaylists_ir_Bot)",
                            'parse_mode' => 'markdown',
                        ],
                        'thumbnail_url' => 'https://myplaylists.ir/assets/no-cover-logo-B8RP5QBr.png',
                    ])
                ]),
                'cache_time' => 0,
            ]);

            return;
        }

        $songs = Song::where('name', 'LIKE', "%$userEnteredText%")
            ->orWhere('artist', 'LIKE', "%$userEnteredText%")
            ->limit(10)
            ->get();

        // no result founded
        if ($songs->isEmpty()) {
            Telegram::answerInlineQuery([
                'inline_query_id' => $inlineQuery->get('id'),
                'results' => json_encode([
                    new InlineQueryResultArticle([
                        'id' => uniqid(),
                        'title' => "چیزی پیدا نکردم 🫠",
                        'description' => "خواننده یا آهنگ دیگه ای رو امتحان کن 😃",
                        'input_message_content' => [
                            'message_text' => "🟣 [MyPlaylists](t.me/Myplaylists_ir_Bot)",
                            'parse_mode' => 'markdown',
                        ],
                        'thumbnail_url' => 'https://myplaylists.ir/assets/no-cover-logo-B8RP5QBr.png',
                    ])
                ]),
                'cache_time' => 0,
            ]);

            return;
        }

        
        $results = [];
        foreach ($songs as $song) {
            $album = $song->album ?? 'unknwon';
            $artist = $song->artist ?? 'unknown';
            $title = $song->name;
            $messageText = "🎧 <strong>{$song->name}</strong> \n🗣 $artist  \n💽 $album";

            // add songs lyrics to message response
            if ($song->lyrics) {
                $messageText .= "<blockquote expandable>{$song->lyrics}</blockquote>";
                $title .= " - Lyrics";
            }

            $messageText .= "\n<a href='{$song->share_link}'>🟣 Listen In Application</a>";

            $params = [
                'id' => (string) $song->id,
                'title' => $title,
                'description' => $song->artist ?? $song->album,
                'input_message_content' => [
                    'message_text' => $messageText,
                    'parse_mode' => 'HTML',
                ],
                'reply_markup' => Keyboard::make([
                    'inline_keyboard' => [
                        [
                            ['text' => '🎧 Listen', 'url' => $song->share_link],
                            ['text' => '📥 Download', 'url' => "https://t.me/Myplaylists_ir_Bot?start=sendSongToTelegram_{$song->id}"]
                        ]
                    ]
                ])

            ];

            $params['thumb_url'] = $song->cover ?: "https://myplaylists.ir/assets/no-cover-logo-B8RP5QBr.png";

            $results[] = new InlineQueryResultArticle($params);
        }

        Telegram::answerInlineQuery([
            'inline_query_id' => $inlineQuery->get('id'),
            'results' => json_encode($results),
            'cache_time' => 0,
        ]);
    }

    public function getAccess($telegram, $chatId, $encryptedToken, $telegramUsername)
    {
        $token = (new \App\Http\Controllers\api\v1\TokenController())->isTokenValid($encryptedToken);
        if ($token) {
            if (empty($telegramUsername)) {
                $telegram->sendMessage([
                    "chat_id" => $chatId,
                    "text" => "🫠 For accessing to bot you should have telegram username. Check tg://settings",
                    "parse_mode" => "Markdown",
                ]);
                return;
            }
            $user = \App\Models\User::firstWhere("email", $token['email']);

            if ($user->telegram_username && $user->telegram_username === $telegramUsername) {
                $telegram->sendMessage([
                    "chat_id" => $chatId,
                    "text" => "🤔 It seems you already have access to bot, But we updated your access.",
                ]);
            }

            $user->update([
                "telegram_username" => $telegramUsername,
            ]);

            $telegram->sendMessage([
                "chat_id" => $chatId,
                "text" => "🟣 Now you have access to upload your songs to MyPlaylists. \n\n🔼 Please send me a song file to upload it. \n\n👉 Maximum size due telegram limitation is 20MB.",
                "parse_mode" => "Markdown",
            ]);

        } else {
            $telegram->sendMessage([
                "chat_id" => $chatId,
                "text" => "Your token is invalid, If you think this is but please contact support at t.me/p_nightwolf",
            ]);
        }

    }

    public function searchForSongFromSiteArchive($telegram, $chatId, $userEnteredText)
    {
        $songs = Song::query()->where('name', 'LIKE', "%$userEnteredText%")->orWhere('artist', 'LIKE', "%$userEnteredText%")->take(10)->get();

        // search through internet
        $replayMarkup = Keyboard::make([
            'inline_keyboard' => [
                [
                    [
                        'text' => '🔎 Search The Internet',
                        'callback_data' => "sOut:{$userEnteredText}:youtubemusic"
                    ],
                ],
            ],
        ]);

        if ($songs->count() === 0) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "🦦 Nothing found, For better result you can search through Internet",
                "reply_markup" => $replayMarkup,
            ]);
            return;
        }

        $message = "🟣 Here is founded songs: \n\n";

        foreach ($songs as $key => $song) {
            $artist = "{$song->artist} -" ?? '';
            $key++;
            $message .= "$key. 🎧 $artist {$song->name} \n🔗 {$song->directLink} \n📥 Download /dl_{$song->id}\n\n";
        }

        $telegram->sendMessage([
            "chat_id" => $chatId,
            "text" => $message,
            "reply_markup" => $replayMarkup
        ]);
    }

    public function getUser($telegramUsername)
    {
        return User::firstWhere('telegram_username', $telegramUsername);
    }

    /**
     * Payloads
     * contains "_"
     */
    // Payload: download from outer resources


    // Payload: send song to telegram
    public function sendSongToTelegram($telegram, $chatId, $songId)
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

        $telegram->sendAudio($params);
    }

    // Payload: ask access payload
    public function askAccess($telegram, $chatId)
    {
        $message = "🔑 Your access key has been copied to your clipboard.Please send it to me.\n⚠️ This token expire after 60 second, If your toked expired generate new one.\n\n";
        $message .= "🔑 کلید دسترسی شما در کلیپ بورد شما ذخیره شده  است. لطفا برای من ارسال کنید.\n⚠️ این توکن تنها ۶۰ ثانیه اعتبار دارد، در صورت منقضی شدن مجدد توکن دریافت کنید.";

        $telegram->sendMessage([
            "chat_id" => $chatId,
            "text" => $message,
        ]);
    }
    
    public function playlistsInlineKeyboard($user, $buttonsPerRow = 2, $additionalData = null)
    {
        $playlists = $user->playlists;
        $buttons = [];

        foreach ($playlists as $playlist) {
            $callbackData = "addToPlaylist:{$user->telegram_username}:{$playlist->id}";

            if ($additionalData)
                $callbackData .= ":$additionalData";

            $buttons[] = [
                'text' => $playlist->name,
                'callback_data' => $callbackData,
            ];
        }

        // buttons per row 
        return array_chunk($buttons, $buttonsPerRow);
    }

}
