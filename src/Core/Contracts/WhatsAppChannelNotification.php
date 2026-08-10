<?php

namespace OpenKOS\Core\Contracts;

use OpenKOS\Core\Data\WhatsApp\WhatsAppContent;

interface WhatsAppChannelNotification
{
    public function toWhatsAppChannel(object $notifiable): WhatsAppContent;
}
