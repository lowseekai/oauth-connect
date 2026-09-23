<?php

namespace Lowseekai\OAuthConnect\Notifications;

use Flarum\Database\AbstractModel;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;
use Lowseekai\OAuthConnect\Models\Application;

class ApplicationSubmittedNotification implements BlueprintInterface, MailableInterface, AlertableInterface
{
    public function __construct(private Application $application)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->application->user;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->application->user;
    }

    public function getData(): array
    {
        return ['applicationId' => (int) $this->application->id, 'applicationName' => $this->application->name];
    }

    public static function getType(): string
    {
        return 'oauthApplicationSubmitted';
    }

    public static function getSubjectModel(): string
    {
        return User::class;
    }

    public function getEmailViews(): array
    {
        return ['text' => 'lowseekai-oauth-connect::email.plain.application-submitted', 'html' => 'lowseekai-oauth-connect::email.html.application-submitted'];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('lowseekai-oauth-connect.email.application_submitted_subject');
    }
}
