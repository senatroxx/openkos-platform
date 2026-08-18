<?php

namespace OpenKOS\Core\Contracts;

use OpenKOS\Core\Data\Payment\PaymentProviderResult;
use OpenKOS\Core\Data\Payment\PaymentStatusLookupRequest;

interface PaymentGatewayStatusLookup
{
    /**
     * Look up and normalize the current provider-side payment status.
     */
    public function lookupPaymentStatus(PaymentStatusLookupRequest $request): PaymentProviderResult;
}
