<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Traits\TelegramBotTrait;
use Telegram\Bot\FileUpload\InputFile;

class DownloadYoutubeMusicJob implements ShouldQueue
{
    use Queueable, TelegramBotTrait;

    protected int $chatId;
    protected string $identifier;
    protected $cnToYmMessageId;

    /**
     * Create a new job instance.
     */
    public function __construct($chatId, $identifier, $cnToYmMessageId)
    {
        $this->chatId = $chatId;
        $this->identifier = $identifier;
        $this->cnToYmMessageId = $cnToYmMessageId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $telegram = \Telegram\Bot\Laravel\Facades\Telegram::bot('mybot');
        $chatId = $this->chatId;
        $identifier = $this->identifier;

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
            'text' => "📥 Downloading " . basename($downloadedFile) . " From server....",
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
            'message_id' => $this->cnToYmMessageId,
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
}
