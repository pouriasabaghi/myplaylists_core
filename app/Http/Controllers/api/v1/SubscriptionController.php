<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SubscriptionService;
use App\Models\User;
;

class SubscriptionController extends Controller
{
    public function __construct(public SubscriptionService $subscriptionService)
    {
    }
    public function subscriptions()
    {
        return response()->json(auth()->user()->subscriptions);
    }
    public function subscribers()
    {
        return response()->json(auth()->user()->subscribers);
    }

    public function subAndUnsubscribeUser(Request $request, User $user)
    {
        $subscriber = auth()->user();
        $subscribed = $user;

        if ($subscriber->id === $subscribed->id)
            return response()->json(['message' => 'You cannot subscribe to yourself'], 400);


        if ($this->subscriptionService->isSubscribed($subscriber, $subscribed)) {
            $this->subscriptionService->unsubscribe($subscriber, $subscribed);
            $message = 'Unsubscribed';
        } else {
            $this->subscriptionService->subscribe($subscriber, $subscribed);
            $message = 'Subscribed Successfully';
        }

        return response()->json(['message' => $message]);
    }

    public function isSubscribe(User $user)
    {
        $subscriber = auth()->user();
        $subscribed = $user;
        return $this->subscriptionService->isSubscribed($subscriber, $subscribed);
    }

}
