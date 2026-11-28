<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

class AdminPasswordReset extends ResetPassword
{
    protected function resetUrl($notifiable)
    {
        $path = route('admin.password.reset', absolute: false);
        $fragment = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], encoding_type: PHP_QUERY_RFC3986);

        return rtrim(config('app.url'), '/').$path.'#'.$fragment;
    }
}
