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

    public function getUserSubscribers(User $user)
    {
        $authUser = auth()->user();

        $subscribers = $user->subscribers()
            ->select('users.*')
            ->selectSub(function ($q) use ($authUser) {
                $q->from('subscription_user')
                    ->whereColumn('subscription_user.subscribed_id', 'users.id')
                    ->where('subscription_user.subscriber_id', $authUser->id)
                    ->selectRaw('1');
            }, 'is_subscribed')
            ->get();
        return response()->json($subscribers);
    }

    public function getUserSubscriptions(User $user)
    {
        $authUser = auth()->user();

        $subscriptions = $user->subscriptions()
            ->select('users.*')
            ->selectSub(function ($q) use ($authUser) {
                $q->from('subscription_user')
                    ->whereColumn('subscription_user.subscribed_id', 'users.id')
                    ->where('subscription_user.subscriber_id', $authUser->id)
                    ->selectRaw('1');
            }, 'is_subscribed')
            ->get();

        return response()->json($subscriptions);
    }

}
