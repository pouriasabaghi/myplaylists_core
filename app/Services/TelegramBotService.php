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

    /**
     * send welcome message 
     * @param int|null $userId
     * @return void
     */
    public function sendWelcomeMessage($telegram, $chatId, int|null $userId, string $telegramUsername)
    {
        $welcomeText = "👋 Hello {$telegramUsername}. \n\n";
        $welcomeText .= "🟣 Myplaylist is a music platform that you can create and share your playlists \n\n";

        if ($userId) {
            $welcomeText .= "🔼 Please send me a song file to upload it. \n\n";
            $welcomeText .= "👉 Maximum size due telegram limitation is 20MB.";
        } else {
            $welcomeText .= "Your Telegram account is not registered in the system. However, you can still use all the features of the MyPlaylists bot and application. If you wish to upload a song via the bot, please contact support at t.me/p_nightwolf.";
        }

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $welcomeText,
            'reply_markup' => Keyboard::make([
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Search & Share Song 🔍',
                            'switch_inline_query_current_chat' => 'gary moore'
                        ]
                    ]
                ]
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
            'text' => "🎧 Song:\n {$song->name} \n {$song->direct_link}",
            'reply_markup' => Keyboard::make([
                'inline_keyboard' => [
                    [
                        ['text' => 'Add To Playlist', 'callback_data' => "showPlaylists:{$user->telegram_username}:{$song->id}"],
                    ]
                ]
            ])
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

    public function showPlaylistsHandler($telegram, $chatId, $user, $songId)
    {
        $playlistsInlineKeyboard = $this->playlistsInlineKeyboard($user, 4, $songId);

        $replyMarkup = Keyboard::make([
            'inline_keyboard' => $playlistsInlineKeyboard
        ]);
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Select your playlist 👇",
            'reply_markup' => $replyMarkup,
        ]);
    }

    public function addToPlaylistHandler($telegram, $chatId, $user, $playlistId, $songId)
    {
        $playlist = Playlist::findOrFail($playlistId);
        $song = Song::findOrFail($songId);

        if ($song->user_id !== $user->id || $playlist->user_id !== $user->id) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "Only owner can make these changes",
            ]);
            return;
        }

        $playlist->songs()->attach($song);
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "✅ Song added to playlist successfully",
        ]);
    }

    public function searchSongsInlineQuery($inlineQuery, $queryText, $chatId)
    {
        $songs = Song::where('name', 'LIKE', "%$queryText%")
            ->orWhere('artist', 'LIKE', "%$queryText%")
            ->limit(10)
            ->get();

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

            $messageText .= "\n<a href='{$song->direct_link}'>🟣 Listen In Application</a>";

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
                            ['text' => '🎧 Listen', 'url' => $song->direct_link],
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


    /**
     * Expand a shortened URL to its final destination.
     *
     * @param string $shortUrl
     * @return string The expanded URL, or the original if not redirected.
     */
    public function expandUrl($shortUrl)
    {
        // use get_headers for getting HTTP headers
        $headers = @get_headers($shortUrl, 1);
        if ($headers !== false && isset($headers['Location'])) {
            // if Location is an array return last index
            if (is_array($headers['Location']))
                return end($headers['Location']);
            else
                return $headers['Location'];

        }

        return $shortUrl;
    }

    public function searchForSongFromYoutubeMusic($telegram, $chatId, $userEnteredText)
    {

        // prevent from sending shell
        $escapedUserEnteredText = escapeshellarg($userEnteredText);

        // for exploding title from url 
        $separator = "|||";

        // run scdl script and get output
        $command = "yt-dlp ytsearch2:$escapedUserEnteredText --print '%(title)s$separator%(id)s'";

        // inform user that search is in process
        $stMessage = $telegram->sendMessage([
            "chat_id" => $chatId,
            "text" => "⏳ Searching for $userEnteredText",
        ]);

        // out put of scdl command contains
        $output = shell_exec($command);

        if (empty($output)) {
            $telegram->sendMessage([
                "chat_id" => $chatId,
                "text" => "Nothing found for $userEnteredText",
            ]);
            return;
        }

        // split output 
        $results = explode("\n", $output);

        // create inline keyboard
        $inlineKeyboard = [];
        foreach ($results as $result) {
            $song = explode($separator, $result);

            if (empty($song[0]))
                continue;

            $inlineKeyboard[] = [
                [
                    'text' => $song[0], // song title
                    'callback_data' => "dlOut:{$chatId}:{$song[1]}:youtubemusic", // song id
                ]
            ];
        }

        $replyMarkup = Keyboard::make([
            'inline_keyboard' => $inlineKeyboard,
        ]);

        // cleanup messages
        $telegram->deleteMessage([
            "chat_id" => $chatId,
            "message_id" => $stMessage->getMessageId(),
        ]);

        $telegram->sendMessage([
            "chat_id" => $chatId,
            "text" => "Founded songs for $userEnteredText 👇",
            'reply_markup' => $replyMarkup,
        ]);
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
                        'callback_data' => "sOut:{$chatId}:{$userEnteredText}:youtubemusic"
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

}
