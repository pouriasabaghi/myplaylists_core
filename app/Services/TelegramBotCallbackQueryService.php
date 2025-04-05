<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\Song;
use Telegram\Bot\Keyboard\Keyboard;
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
            'text' => __('message.select_playlists', [], $user->language),
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
     * @param string $cacheKey
     * @param string $resource
     * @return void
     */
    public function sOut(TelegramBotApi $telegram, int $chatId, CallbackQuery $callbackQuery, string|null $cacheKey, string $resource, $language, $searchMessageId): void
    {
        if (!cache()->has($cacheKey)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'=> __('message.request_outdated', [], $language),
            ]);
            return;
        }
        $userEnteredText = cache()->get($cacheKey);


        if ($resource == 'youtubemusic') {
            // delete search message
            $telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $searchMessageId
            ]);

            // inform user that search is in process
            $stMessage = $telegram->sendMessage([
                "chat_id" => $chatId,
                "text" => __('message.search_for', [], $language) . " $userEnteredText ...",
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
        // prevent user from sending rapidly requests   
        $lockKey = "dl_job_lock_$chatId";
        $lock = cache()->lock($lockKey, 10);

        if(!$lock->get()){
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "🧘 Please wait for the previous request to finish before sending a new one.",
            ]);
            return;
        }

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
                'text' => "⏳ Connecting to server...",
            ]);
            dispatch(new \App\Jobs\DownloadYoutubeMusicJob($chatId, $identifier, $cnToYmMessage->getMessageId()));
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