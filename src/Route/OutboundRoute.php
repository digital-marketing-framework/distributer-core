<?php

namespace DigitalMarketingFramework\Distributor\Core\Route;

use DigitalMarketingFramework\Core\SchemaDocument\RenderingDefinition\RenderingDefinitionInterface;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\BooleanSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\ContainerSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\Custom\InheritableBooleanSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\Custom\RestrictedTermsSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\Custom\DataMapperGroupReferenceSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\CustomSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\Plugin\DataProcessor\ConditionSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\SchemaInterface;
use DigitalMarketingFramework\Core\Context\ContextInterface;
use DigitalMarketingFramework\Core\DataProcessor\DataProcessorAwareInterface;
use DigitalMarketingFramework\Core\DataProcessor\DataProcessorAwareTrait;
use DigitalMarketingFramework\Core\DataProcessor\DataProcessorContextInterface;
use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use DigitalMarketingFramework\Core\Model\Data\DataInterface;
use DigitalMarketingFramework\Core\Route\Route;
use DigitalMarketingFramework\Core\Utility\GeneralUtility;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Core\Model\Configuration\DistributorConfigurationInterface;
use DigitalMarketingFramework\Distributor\Core\Model\DataSet\SubmissionDataSetInterface;
use DigitalMarketingFramework\Distributor\Core\Plugin\ConfigurablePlugin;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface;
use DigitalMarketingFramework\Distributor\Core\Service\DistributorInterface;

abstract class OutboundRoute extends ConfigurablePlugin implements OutboundRouteInterface, DataProcessorAwareInterface
{
    use DataProcessorAwareTrait;

    protected const KEY_ENABLE_DATA_PROVIDERS = 'enableDataProviders';

    public const MESSAGE_GATE_FAILED = 'Gate not passed for route "%s" with ID %s.';

    public const MESSAGE_DATA_EMPTY = 'No data generated for route "%s" with ID %s.';

    public const MESSAGE_NO_DATA_MAPPER_GROUP_DEFINED = 'No data mapper group defined in route "%s" with ID %s.';

    public const MESSAGE_NO_DATA_MAPPER_GROUP_CONFIG_FOUND = 'No data mapper group configuration found for group ID "%s" in outbound route "%s" with ID %s.';

    public function __construct(
        string $keyword,
        RegistryInterface $registry,
        protected SubmissionDataSetInterface $submission,
        protected string $routeId,
    ) {
        parent::__construct($keyword, $registry);
        $this->configuration = $this->submission->getConfiguration()->getOutboundRouteConfiguration(static::getIntegrationName(), $this->routeId);
    }

    abstract public static function getIntegrationName(): string;

    public static function getIntegrationLabel(): ?string
    {
        return null;
    }

    public static function getIntegrationWeight(): int
    {
        return static::WEIGHT;
    }

    public static function getOutboundRouteListLabel(): ?string
    {
        return null;
    }

    public function buildData(): DataInterface
    {
        $dataMapperGroupId = $this->getConfig(static::KEY_DATA);
        if ($dataMapperGroupId === '') {
            throw new DigitalMarketingFrameworkException(sprintf(static::MESSAGE_NO_DATA_MAPPER_GROUP_DEFINED, $this->getKeyword(), $this->routeId));
        }

        $dataMapperGroupConfig = $this->submission->getConfiguration()->getDataMapperGroupConfiguration($dataMapperGroupId);
        if ($dataMapperGroupConfig === null) {
            throw new DigitalMarketingFrameworkException(sprintf(static::MESSAGE_NO_DATA_MAPPER_GROUP_CONFIG_FOUND, $dataMapperGroupId, $this->getKeyword(), $this->routeId));
        }

        $context = $this->dataProcessor->createContext(
            $this->submission->getData(),
            $this->submission->getConfiguration()
        );

        return $this->dataProcessor->processDataMapperGroup($dataMapperGroupConfig, $context);
    }

    protected function getDataProcessorContext(): DataProcessorContextInterface
    {
        return $this->dataProcessor->createContext(
            $this->submission->getData(),
            $this->submission->getConfiguration()
        );
    }

    public function processGate(): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $gate = $this->getConfig(static::KEY_GATE);
        if (empty($gate)) {
            return true;
        }

        return $this->dataProcessor->processCondition(
            $this->getConfig(static::KEY_GATE),
            $this->getDataProcessorContext()
        );
    }

    public function getRouteId(): string
    {
        return $this->routeId;
    }

    public function enabled(): bool
    {
        return (bool)$this->getConfig(static::KEY_ENABLED);
    }

    public function async(): ?bool
    {
        return InheritableBooleanSchema::convert($this->getConfig(DistributorConfigurationInterface::KEY_ASYNC));
    }

    public function enableStorage(): ?bool
    {
        return InheritableBooleanSchema::convert($this->getConfig(DistributorConfigurationInterface::KEY_ENABLE_STORAGE));
    }

    public function getEnabledDataProviders(): array
    {
        $config = $this->getConfig(static::KEY_ENABLE_DATA_PROVIDERS);

        return RestrictedTermsSchema::getAllowedTerms($config);
    }

    public function addContext(ContextInterface $context): void
    {
    }

    public function process(): bool
    {
        if (!$this->processGate()) {
            $this->logger->debug(sprintf(static::MESSAGE_GATE_FAILED, $this->getKeyword(), $this->routeId));

            return false;
        }

        $data = $this->buildData();

        if (GeneralUtility::isEmpty($data)) {
            throw new DigitalMarketingFrameworkException(sprintf(static::MESSAGE_DATA_EMPTY, $this->getKeyword(), $this->routeId));
        }

        $dataDispatcher = $this->getDispatcher();
        $dataDispatcher->send($data->toArray());

        return true;
    }

    abstract protected function getDispatcher(): DataDispatcherInterface;

    /**
     * TODO to be used for auto-generation of data mapper field configuration
     */
    public static function getDefaultPassthroughFields(): bool
    {
        return false;
    }

    public static function getDefaultFields(): array
    {
        return [];
    }

    public static function getSchema(): SchemaInterface
    {
        $schema = new ContainerSchema();
        $schema->getRenderingDefinition()->setNavigationItem(false);

        $enabledProperty = $schema->addProperty(static::KEY_ENABLED, new BooleanSchema(static::DEFAULT_ENABLED));
        $enabledProperty->setWeight(10);

        $asyncSchema = new InheritableBooleanSchema();
        $asyncSchema->getRenderingDefinition()->setGroup(RenderingDefinitionInterface::GROUP_SECONDARY);
        $schema->addProperty(DistributorConfigurationInterface::KEY_ASYNC, $asyncSchema);

        $enableStorageSchema = new InheritableBooleanSchema();
        $enableStorageSchema->getRenderingDefinition()->setGroup(RenderingDefinitionInterface::GROUP_SECONDARY);
        $schema->addProperty(DistributorConfigurationInterface::KEY_ENABLE_STORAGE, $enableStorageSchema);

        $enableDataProviders = new RestrictedTermsSchema('/distributor/dataProviders/*');
        $enableDataProviders->getTypeSchema()->getRenderingDefinition()->setLabel('Enable Data Providers');
        $enableDataProviders->getRenderingDefinition()->setSkipHeader(true);
        $enableDataProviders->getRenderingDefinition()->setGroup(RenderingDefinitionInterface::GROUP_SECONDARY);
        $schema->addProperty(static::KEY_ENABLE_DATA_PROVIDERS, $enableDataProviders);

        $gateSchema = new CustomSchema(ConditionSchema::TYPE);
        $gateSchema->getRenderingDefinition()->setLabel('Gate');
        $schema->addProperty(static::KEY_GATE, $gateSchema);

        $dataSchema = new CustomSchema(DataMapperGroupReferenceSchema::TYPE);
        $schema->addProperty(static::KEY_DATA, $dataSchema);

        // TODO gdpr should not be handled in the gate. we need a dedicated service for that

        return $schema;
    }
}