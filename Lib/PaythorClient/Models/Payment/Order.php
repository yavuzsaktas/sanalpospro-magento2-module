<?php

namespace Sanalpospro\SanalPosPro\Lib\PaythorClient\Models\Payment;

class Order
{
    /**
     * @var Cart
     */
    private Cart $cart;
    /**
     * @var Shipping
     */
    private Shipping $shipping;
    /**
     * @var Invoice
     */
    private Invoice $invoice;
 
    /**
     * Set cart.
     *
     * @param Cart $cart
     * @return self
     */
    public function setCart(Cart $cart): self
    {
        $this->cart = $cart;
        return $this;
    }

    /**
     * Set shipping.
     *
     * @param Shipping $shipping
     * @return self
     */
    public function setShipping(Shipping $shipping): self
    {
        $this->shipping = $shipping;
        return $this;
    }

    /**
     * Set invoice.
     *
     * @param Invoice $invoice
     * @return self
     */
    public function setInvoice(Invoice $invoice): self
    {
        $this->invoice = $invoice;
        return $this;
    }
    
    /**
     * To array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'cart' => $this->cart->toArray(),
            'shipping' => $this->shipping->toArray(),
            'invoice' => $this->invoice->toArray()
        ];
    }
}
