<?php

namespace App\Services;

use App\Models\User;

class SubscriptionService{

    public function subscribe(User $subscriber, $subscribed){
        $subscriber->subscriptions()->attach($subscribed);
    }

    public function unsubscribe(User $subscriber, $subscribed){
        $subscriber->subscriptions()->detach($subscribed);
    }

    public function isSubscribed(User $subscriber, User $subscribed){
        return $subscriber->subscriptions()->where('subscribed_id', $subscribed->id)->exists();
    }
}