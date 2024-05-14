<?php

namespace DigitalMarketingFramework\Distributor\Core\Api\EndPoint;

use DigitalMarketingFramework\Distributor\Core\Api\EndPoint\EndPointStorageInterface;
use DigitalMarketingFramework\Distributor\Core\Model\Api\EndPointInterface;

class EndPointStorage implements EndPointStorageInterface
{
    protected array $endpoints = [];

    public function getEndPointByName(string $name): ?EndPointInterface
    {
        return $this->endpoints[$name] ?? null;
    }

    public function getAllEndPoints(): array
    {
        return $this->endpoints;
    }

    public function addEndPoint(EndPointInterface $endPoint): void
    {
        $this->endpoints[$endPoint->getName()] = $endPoint;
    }

    public function removeEndPoint(EndPointInterface $endPoint): void
    {
        unset($this->endpoints[$endPoint->getName()]);
    }

    public function updateEndPoint(EndPointInterface $endPoint): void
    {
        $this->endpoints[$endPoint->getName()] = $endPoint;
    }
}
