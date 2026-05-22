<?php

namespace Sanalpospro\SanalPosPro\Lib\PaythorClient\Models\Payment;

use Sanalpospro\SanalPosPro\Lib\PaythorClient\Models\Payment\Common\Pagination;
use Sanalpospro\SanalPosPro\Lib\PaythorClient\Models\Payment\Common\Search;

class PaymentList
{
    /**
     * @var array
     */
    private array $pagination;
    /**
     * @var array
     */
    private array $search;

    /**
     * Constructor
     *
     * @param Pagination $pagination Pagination parameters
     * @param Search $search Optional search parameters
     */
    public function __construct(Pagination $pagination, Search $search)
    {
        $this->pagination = $pagination->toArray();
        $this->search = $search->toArray();
    }

    /**
     * Set pagination.
     *
     * @param Pagination $pagination
     * @return self
     */
    public function setPagination(Pagination $pagination): self
    {
        $this->pagination = $pagination->toArray();
        return $this;
    }

    /**
     * Set search.
     *
     * @param Search $search
     * @return self
     */
    public function setSearch(Search $search): self
    {
        $this->search = $search->toArray();
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
            'pagination' => $this->pagination,
            'search' => $this->search
        ];
    }
}
