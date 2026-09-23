@include('mail::plain.notification', [
    'title' => $translator->trans('lowseekai-oauth-connect.email.application_submitted_title'),
    'body' => $translator->trans('lowseekai-oauth-connect.email.application_submitted_body', ['application' => $blueprint->getData()['applicationName']]),
])
