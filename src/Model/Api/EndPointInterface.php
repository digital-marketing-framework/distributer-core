<?php

namespace DigitalMarketingFramework\Distributor\Core\Model\Api;

interface EndPointInterface
{
    public function getName(): string;

    public function setName(string $name): void;

    public function getEnabled(): bool;

    public function setEnabled(bool $enabled): void;

    public function getDisableContext(): bool;

    public function setDisableContext(bool $disableContext): void;

    public function getAllowContextOverride(): bool;

    public function setAllowContextOverride(bool $allowContextOverride): void;

    public function getConfigurationDocument(): string;

    public function setConfigurationDocument(string $configurationDocument): void;
}