<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\Song;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\FileUpload\InputFile;
use App\Traits\TelegramBotTrait;
use App\Models\TelegramUser;
use Telegram\Bot\Api as TelegramBotApi;
use Telegram\Bot\Objects\CallbackQuery;

/**
 * Commands
 * contains ":"
 */
class TelegramBotCallbackQueryService
{
    use TelegramBotTrait;
    /**
     * Command: showPlaylists 
     * one of use case is after uploading song from bot and user want directly add to playlist
     * 
     * @param TelegramBotApi $telegram
     * @param int $chatId
     * @param \Telegram\Bot\Objects\CallbackQuery $callbackQuery
     * @param int $telegramId
     * @param int|string $songId
     * @return void
     */
    public function showPlaylists(TelegramBotApi $telegram, int $chatId, CallbackQuery $callbackQuery, int $telegramId, int|string $songId): void
    {
        $telegramBotService = new \App\Services\TelegramBotService();
        $user = $this->getUser($telegramId);
        $playlistsInlineKeyboard = $telegramBotService->playlistsInlineKeyboard($user, 4, $songId);

        $replyMarkup = Keyboard::make([
            'inline_keyboard' => $playlistsInlineKeyboard
        ]);
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => __('message.select_playlist', [], $user->language),
            'reply_markup' => $replyMarkup,
        ]);

    }

    /**
     * Command: addToPlaylist
     * After user selected playlist from list, this function is called and add song to playlist
     * 
     * @param TelegramBotApi $telegram
     * @param int $chatId
     * @param \Telegram\Bot\Objects\CallbackQuery $callbackQuery
     * @param int $telegramId
     * @param int|string $playlistId
     * @param int|string $songId
     * @return void
     */
    public function addToPlaylist(TelegramBotApi $telegram, int $chatId, CallbackQuery $callbackQuery, int $telegramId, int|string $playlistId, int|string $songId): void
    {
        $user = $this->getUser($telegramId);
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
            'text' => __('message.song_added_to_playlist', [], $user->language),
        ]);
    }

    /**
     * Command: search from outer resources 
     * appear on bottom of every search from archive response
     * 
     * @param TelegramBotApi $telegram
     * @param int $chatId
     * @param \Telegram\Bot\Objects\CallbackQuery $callbackQuery
     * @param string $userEnteredText
     * @param string $resource
     * @return void
     */
    public function sOut(TelegramBotApi $telegram, int $chatId, CallbackQuery $callbackQuery, string|null $userEnteredText, string $resource): void
    {
        if ($resource == 'youtubemusic') {
            // inform user that search is in process
            $stMessage = $telegram->sendMessage([
                "chat_id" => $chatId,
                "text" => "Searching for $userEnteredText ...",
            ]);

            dispatch(new \App\Jobs\SearchYoutubeMusicJob($chatId, $stMessage->getMessageId(), $userEnteredText));
            return;
        }

        return;
    }

    /**
     * Download a song from an outer resource like YouTube
     *
     * @param TelegramBotApi $telegram
     * @param int $chatId
     * @param CallbackQuery $callbackQuery
     * @param string $identifier
     * @param string $resource
     * @return void
     */
    public function dlOut(TelegramBotApi $telegram, int $chatId, CallbackQuery $callbackQuery, string $identifier, string $resource): void
    {
        if ($resource == 'youtubemusic') {
            $userEnteredUrl = "https://www.youtube.com/watch?v=$identifier";

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
            return;
        }

        return;
    }


    /**
     * Command: set language and suggest tour
     * 
     * @param TelegramBotApi $telegram
     * @param int $chatId
     * @param CallbackQuery $callbackQuery
     * @param string $language
     * @return void
     */
    public function setLanguage(TelegramBotApi $telegram, int $chatId, CallbackQuery $callbackQuery, string $language): void
    {
        TelegramUser::firstWhere('chat_id', $chatId)->update(['language' => $language]);

        $message = $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Preparing tour...",
        ]);

        $messageId = $message->getMessageId();

        $telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => __("tour.introduction", [], $language),
            'reply_markup' => Keyboard::make([
                'inline_keyboard' => [
                    [
                        [
                            'text' => __('tour.start_button', [], $language),
                            'callback_data' => "stepHandler:$messageId:0:$language"
                        ],
                    ]
                ],
            ])
        ]);
    }

    /**
     * Command: handle tour steps
     *
     * @param TelegramBotApi $telegram
     * @param int $chatId
     * @param CallbackQuery $callbackQuery
     * @param int $messageId
     * @param int $step
     * @param string $language
     * @return void
     */
    public function stepHandler(TelegramBotApi $telegram, int $chatId, CallbackQuery $callbackQuery, int $messageId, int $step, string $language): void
    {
        $nextStep = $step + 1;
        $prevStep = $step - 1;

        $inlineKeyboards = [[]];

        if ($step > 0) {
            $inlineKeyboards[0][] = [
                'text' => '⬅️',
                'callback_data' => "stepHandler:$messageId:$prevStep:$language"
            ];
        }

        $inlineKeyboards[0][] = [
            'text' => '➡️',
            'callback_data' => "stepHandler:$messageId:$nextStep:$language"
        ];

        if ($step == 6) {
            $inlineKeyboards[] = [
                [
                    'text' => __('tour.try_out_button', [], $language),
                    'switch_inline_query_current_chat' => 'back in black'
                ]
            ];
        }

        if ($step == 10) {
            $inlineKeyboards = [
                [
                    [
                        'text' => __('tour.end_button', [], $language),
                        'callback_data' => "finishTour:$messageId:$language"
                    ]
                ]
            ];
        }

        $telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => __("tour.steps.$step", [], $language),
            'reply_markup' => Keyboard::make([
                'inline_keyboard' => $inlineKeyboards
            ])
        ]);
    }

    /**
     * Command: finish tour
     * @param TelegramBotApi $telegram
     * @param int $chatId
     * @param CallbackQuery $callbackQuery
     * @param int $messageId
     * @param string $language
     * @return void
     */
    public function finishTour(TelegramBotApi $telegram, int $chatId, CallbackQuery $callbackQuery, int $messageId, string $language): void
    {
        $telegram->deleteMessage([
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => __("tour.rules_and_limitations", [], $language),
        ]);
    }
}