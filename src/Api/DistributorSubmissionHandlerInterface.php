<?php

namespace DigitalMarketingFramework\Distributor\Core\Api;

use DigitalMarketingFramework\Core\Model\Data\DataInterface;
use DigitalMarketingFramework\Distributor\Core\Model\Api\EndPointInterface;
use DigitalMarketingFramework\Distributor\Core\Model\Configuration\DistributorConfigurationInterface;

interface DistributorSubmissionHandlerInterface
{
    public function submit(array|DistributorConfigurationInterface $configuration, array|DataInterface $data): void;

    public function submitToEndPoint(EndPointInterface $endPoint, array|DataInterface $data): void;

    public function submitToEndPointByName(string $endPointName, array|DataInterface $data): void;

    public function getEndPointNames(): array;
}
