<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Telegram\Bot\Keyboard\Keyboard;

class PlaylistUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public \App\Models\Playlist $playlist)
    {

    }

    public function via()
    {
        return [
            TelegramChannel::class,
        ];
    }

    public function toDatabase()
    {
        return [
            'subject' => 'Playlist Updated',
            'message' => "{$this->playlist->name} has been updated",
            'data' => ['playlist' => $this->playlist->id],
        ];
    }

    public function toTelegram(object $notifiable)
    {
        return [
            'subject' => __(
                'message.playlist_updated_subject',
                [
                    'playlistName' => ucfirst($this->playlist->name),
                    'playlistOwner' => ucfirst($this->playlist->user->name)
                ],
                $this->playlist->user->language
            ). "\n\n",
            'message' => __('message.playlist_updated_message',[],$this->playlist->user->language),
            'replay_markup' => Keyboard::make([
                'inline_keyboard' => [
                    [
                        [
                            'text' => __('message.view', [], $this->playlist->user->language),
                            'url' => config('app.frontend_url') . "/playlists/{$this->playlist->id}/{$this->playlist->name}",
                        ]
                    ]
                ]
            ]),
            'data' => [
                'playlist' => $this->playlist->id,
            ],
        ];
    }
}
