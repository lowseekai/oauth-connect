<?php

namespace Lowseekai\OAuthConnect\Support;

use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Lowseekai\OAuthConnect\Models\Application;
use Lowseekai\OAuthConnect\Notifications\ApplicationReviewedNotification;
use Lowseekai\OAuthConnect\Notifications\ApplicationSubmittedNotification;

class ApplicationNotifier
{
    public function __construct(private NotificationSyncer $notifications)
    {
    }

    public function submitted(Application $application): void
    {
        $application->loadMissing('user');
        $recipients = User::query()
            ->where('is_admin', true)
            ->orWhereHas('groups.permissions', fn (Builder $query) => $query->where('permission', 'oauthConnect.manageApplications'))
            ->get();

        $this->notifications->sync(new ApplicationSubmittedNotification($application), $recipients->all());
    }

    public function reviewed(Application $application, User $reviewer): void
    {
        $application->loadMissing('user');

        if ($application->user) {
            $this->notifications->sync(new ApplicationReviewedNotification($application, $reviewer), [$application->user]);
        }
    }
}
