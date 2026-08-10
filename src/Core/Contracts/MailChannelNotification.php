<?php

namespace OpenKOS\Core\Contracts;

use OpenKOS\Core\Data\Mail\MailContent;

interface MailChannelNotification
{
    public function toMailChannel(object $notifiable): MailContent;
}
