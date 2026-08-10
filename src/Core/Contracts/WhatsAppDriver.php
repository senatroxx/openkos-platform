<?php

namespace OpenKOS\Core\Contracts;

use OpenKOS\Core\Data\WhatsApp\DriverHealthResult;
use OpenKOS\Core\Data\WhatsApp\WhatsAppMessage;

interface WhatsAppDriver
{
    public function send(WhatsAppMessage $message): void;

    public function supportsAttachments(): bool;

    public function health(): DriverHealthResult;

    public function supportsPairing(): bool;

    public function configurationSchema(): array;

    public function getPairingQrCode(): ?string;

    public function pair(): void;

    public function disconnect(): void;
}
