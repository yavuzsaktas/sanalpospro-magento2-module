<?php

namespace Sanalpospro\SanalPosPro\Lib\PaythorClient\Models\Payment;

class Invoice
{
    /**
     * @var string
     */
    private string $id = '';
    /**
     * @var string
     */
    private string $firstName = '';
    /**
     * @var string
     */
    private string $lastName = '';
    /**
     * @var string
     */
    private string $price = '';
    /**
     * @var int
     */
    private int $quantity = 0;

    /**
     * Set ID
     *
     * @param string $id
     * @return $this
     */
    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Set first name
     *
     * @param string $firstName
     * @return $this
     */
    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * Set last name
     *
     * @param string $lastName
     * @return $this
     */
    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * Set price
     *
     * @param string $price
     * @return $this
     */
    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }

    /**
     * Set quantity
     *
     * @param int $quantity
     * @return $this
     */
    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    /**
     * Convert invoice to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'price' => $this->price,
            'quantity' => $this->quantity
        ];
    }
}
