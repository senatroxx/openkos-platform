<?php

namespace OpenKOS\Core\Data\WhatsApp;

final readonly class WhatsAppContent
{
    public function __construct(
        public string $message,
        public ?string $mediaUrl = null,
        public ?WhatsAppAttachment $attachment = null,
    ) {}
}
