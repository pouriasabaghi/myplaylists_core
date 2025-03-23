<?php

namespace App\Traits;

trait TelegramBotTrait
{
    public function getUser($telegramId)
    {
        return \App\Models\User::firstWhere('telegram_id', $telegramId);
    }

    /**
     * Expand a shortened URL to its final destination.
     *
     * @param string $shortUrl
     * @return string The expanded URL, or the original if not redirected.
     */
    public function expandUrl(string $shortUrl)
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
}