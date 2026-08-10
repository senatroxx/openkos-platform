<?php

namespace OpenKOS\Core\Contracts;

use OpenKOS\Core\Data\Mail\DriverHealthResult;
use OpenKOS\Core\Data\Mail\MailMessage;
use OpenKOS\Core\Data\Mail\MailSendResult;

interface MailDriver
{
    public function send(MailMessage $message): MailSendResult;

    public function health(): DriverHealthResult;
}
