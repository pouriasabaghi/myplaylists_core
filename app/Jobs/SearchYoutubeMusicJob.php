<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SearchYoutubeMusicJob implements ShouldQueue
{
    use Queueable;

    protected int $chatId;
    protected string $userEnteredText;
    protected int $messageId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $chatId, int $messageId, string $userEnteredText)
    {
        $this->chatId = $chatId;
        $this->messageId = $messageId;
        $this->userEnteredText = $userEnteredText;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $telegram =  \Telegram\Bot\Laravel\Facades\Telegram::bot('mybot');
        $chatId = $this->chatId;
        $messageId = $this->messageId;
        $userEnteredText = $this->userEnteredText;

        // prevent from sending shell
        $escapedUserEnteredText = escapeshellarg($userEnteredText);

        // for exploding title from url 
        $separator = "|||";

        // run scdl script and get output
        $command = "yt-dlp ytsearch:$escapedUserEnteredText  --max-downloads 3 --print '%(title)s$separator%(id)s'";

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
                    'callback_data' => "dlOut:{$song[1]}:youtubemusic", // song id
                ]
            ];
        }

        $replyMarkup = \Telegram\Bot\Keyboard\Keyboard::make([
            'inline_keyboard' => $inlineKeyboard,
        ]);

        // cleanup messages
        $telegram->deleteMessage([
            "chat_id" => $chatId,
            "message_id" => $messageId,
        ]);

        $telegram->sendMessage([
            "chat_id" => $chatId,
            "text" => "Search result for:\n$userEnteredText ",
            'reply_markup' => $replyMarkup,
        ]);
        
        return;
    }
}
