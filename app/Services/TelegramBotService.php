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
use App\Models\User;
use App\Models\TelegramUser;
use Telegram\Bot\FileUpload\InputFile;
use App\Traits\TelegramBotTrait;

class TelegramBotService
{
    use TelegramBotTrait;
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

    public function sendWelcomeMessage($telegram, $chatId, $account)
    {
        TelegramUser::updateOrCreate(
            ['chat_id' => $chatId],
            ['username' => $account->username]
        );

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "👋 Hello {$account->username}. \n\n",
            'reply_markup' => Keyboard::make([
                'keyboard' => [
                    [
                        [
                            'text' => '👤 Support',
                        ],

                        [
                            'text' => '✈️ Tour',
                        ],
                    ],
                    [
                        [
                            'text' => '🔑 Access',
                        ],
                        [
                            'text' => '📲 Login'
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
                        ['text' => 'Add To Playlist', 'callback_data' => "showPlaylists:{$user->telegram_id}:{$song->id}"],
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

    public function getAccess($telegram, $chatId, $encryptedToken, $account)
    {
        $token = (new \App\Http\Controllers\api\v1\TokenController())->isTokenValid($encryptedToken);
        $language = $this->getChat($chatId)?->language;
        
        if ($token) {

            $user = User::firstWhere("email", $token['email']);
            $user->update([
                "telegram_id" => $account->getId(),
                "telegram_username" => $account->username,
            ]);

            $telegram->sendMessage([
                "chat_id" => $chatId,
                "text" => __("message.access_granted", [], $language),
                "parse_mode" => "Markdown",
            ]);

        } else {
            $telegram->sendMessage([
                "chat_id" => $chatId,
                "text" => __("message.access_denied", [], $language),
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

    public function playlistsInlineKeyboard($user, $buttonsPerRow = 2, $additionalData = null)
    {
        $playlists = $user->playlists;
        $buttons = [];

        foreach ($playlists as $playlist) {
            $callbackData = "addToPlaylist:{$user->telegram_id}:{$playlist->id}";

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

    public function getSongFromEnteredResourceUrl($telegram, $chatId, $message)
    {
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
            if (str_starts_with($message, $site)) {
                if ($platform === 'youtubemusic') {
                    $this->getFromYoutubeMusic($telegram, $chatId, $message);
                    return;
                }

                // Download from sound cloud
                if ($platform === 'soundcloud') {
                    $this->getFromSoundCloud($telegram, $chatId, $message);
                    return;
                }
            }
        }

        // no supported site found
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Entered url is not valid.\nCurrently we support SoundCloud and YoutubeMusic.",
        ]);
    }

    public function getFromSoundCloud($telegram, $chatId, $userEnteredUrl, $isUrl = true)
    {
        $dlFromScMessage = $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "📥 Downloading From SoundCloud Server...",
        ]);

        if (str_starts_with($userEnteredUrl, 'https://on.soundcloud.com'))
            $userEnteredUrl = $this->expandUrl($userEnteredUrl);

        // prevent from sending shell
        $url = escapeshellarg($userEnteredUrl);

        // run scdl script and get output
        $command = $isUrl ? "/usr/local/bin/scdl -l $url --overwrite  2>&1" : "/usr/local/bin/scdl -s $url -n 1  2>&1";

        // out put of scdl command contains
        $output = shell_exec($command);


        preg_match('/(.+?)\.(mp3|m4a|flac|opus)/', $output, $matches);

        // alert user to prevent sending artist, album or other pages link
        if (empty($matches[0])) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "⚠️ Invalid url \nPlease make sure it's a song page not artist or album \nIf you thing this is a bug please contact t.me/p_nightwolf",
            ]);
            return;
        }

        $filenameWithExtension = trim($matches[0]) ?? null;
        // $filename = $matches[1];
        // $fileExtension = $matches[2];


        $downloadedFile = "/var/www/downloads/$filenameWithExtension";

        $telegram->deleteMessage([
            'chat_id' => $chatId,
            'message_id' => $dlFromScMessage->getMessageId(),
        ]);


        if ($filenameWithExtension && file_exists($downloadedFile)) {
            $params = [
                'chat_id' => $chatId,
                'audio' => InputFile::create($downloadedFile),
                'caption' => "[🟣 Myplaylists](https://t.me/myplaylists_ir)",
                'parse_mode' => 'Markdown'
            ];
            $telegram->sendAudio($params);

            // delete the file after sending
            unlink($downloadedFile);

        } else {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "Invalid url \n If you thing this is a but please contact t.me/p_nightwolf"
            ]);
        }
    }

    public function getFromYouTubeMusic($telegram, $chatId, $userEnteredUrl)
    {
        // inform user that playlists not allowed
        if (strpos($userEnteredUrl, 'list=') !== false) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "⚠️ It seems to be a playlist URL\.\n Sorry but downloading playlists is not allowed ||Yet 🔥||\.",
                'parse_mode' => 'MarkdownV2'
            ]);
            return;
        }

        // inform user that connecting is starting
        $cnToYmMessage = $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "⏳ Connecting to YouTube server...",
        ]);

        if (str_starts_with($userEnteredUrl, 'https://youtu.be'))
            $userEnteredUrl = $this->expandUrl($userEnteredUrl);

        // escape URL to prevent shell injection
        $url = escapeshellarg($userEnteredUrl);
        $downloadPath = "/var/www/downloads/";

        // Step 1: Get the expected filename using --get-filename
        $getFilenameCmd = "/usr/local/bin/yt-dlp --get-filename --audio-format mp3 --embed-metadata  --output '{$downloadPath}%(title)s.mp3' $url";
        $downloadedFile = trim(shell_exec($getFilenameCmd));


        // Inform user that downloading song started
        $dlFromYmMessage = $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "📥 Downloading " . basename($downloadedFile) . " From Youtube server....",
        ]);

        // Step 2: Download the audio file using yt-dlp
        $downloadCommand = "/usr/local/bin/yt-dlp -x --playlist-items 1 --max-filesize 20M --audio-format mp3 --embed-thumbnail --embed-metadata --output '{$downloadPath}%(title)s.%(ext)s' $url 2>&1";
        shell_exec($downloadCommand);

        // inform user that sending song started
        $almostDoneMessage = $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "⬆️ Almost done, Sending for you...",
        ]);

        // clean up messages
        $telegram->deleteMessage([
            'chat_id' => $chatId,
            'message_id' => $cnToYmMessage->getMessageId(),
        ]);
        $telegram->deleteMessage([
            'chat_id' => $chatId,
            'message_id' => $dlFromYmMessage->getMessageId(),
        ]);
        $telegram->deleteMessage([
            'chat_id' => $chatId,
            'message_id' => $almostDoneMessage->getMessageId(),
        ]);

        // check if file exists, then send and remove it
        if (file_exists($downloadedFile)) {
            $params = [
                'chat_id' => $chatId,
                'audio' => InputFile::create($downloadedFile),
                'thumb' => InputFile::create("https://myplaylists.ir/assets/no-cover-logo-B8RP5QBr.png"),
                'caption' => "[🟣 Myplaylists](https://t.me/myplaylists_ir)",
                'parse_mode' => 'Markdown'
            ];
            $telegram->sendAudio($params);

            // delete the file after sending
            unlink($downloadedFile);
        } else {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "Failed to download audio. This happen due on of this:\nFile is too big.\nURL is not valid.\nDue some region restrictions.\nIf you thing this is a but please contact t.me/p_nightwolf",
            ]);
        }
    }
}
