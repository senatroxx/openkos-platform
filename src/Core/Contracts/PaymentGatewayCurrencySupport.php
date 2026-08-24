<?php

namespace OpenKOS\Core\Contracts;

interface PaymentGatewayCurrencySupport
{
    public function supportsCurrency(string $currency): bool;

    /**
     * @return list<string>
     */
    public function supportedCurrencies(): array;
}
