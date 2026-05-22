<?php

namespace Sanalpospro\SanalPosPro\Lib\PaythorClient\Models\Payment;

class Refund
{
    /**
     * @var int
     */
    private int $amount;
    /**
     * @var string
     */
    private string $currency;
    /**
     * @var string
     */
    private string $paymentInstrument;

    /**
     *
     * @param int $amount
     * @param string $currency
     * @param string $paymentInstrument
     */
    public function __construct(int $amount, string $currency, string $paymentInstrument)
    {
        $this->amount = $amount;
        $this->currency = $currency;
        $this->paymentInstrument = $paymentInstrument;
    }

    /**
     * To array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_instrument' => $this->paymentInstrument
        ];
    }
}
