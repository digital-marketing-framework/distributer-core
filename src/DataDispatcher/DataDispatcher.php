<?php

namespace DigitalMarketingFramework\Distributor\Core\DataDispatcher;

use DigitalMarketingFramework\Core\TemplateEngine\TemplateEngineAwareInterface;
use DigitalMarketingFramework\Core\TemplateEngine\TemplateEngineAwareTrait;
use DigitalMarketingFramework\Core\Utility\GeneralUtility;
use DigitalMarketingFramework\Distributor\Core\Plugin\Plugin;
use DigitalMarketingFramework\TemplateEngineTwig\TemplateEngine\TwigTemplateEngine;

abstract class DataDispatcher extends Plugin implements DataDispatcherInterface, TemplateEngineAwareInterface
{
    use TemplateEngineAwareTrait;

    protected function getTemplateNameCandidates(): array
    {
        return [
            sprintf('preview/data-dispatcher/%s.html.twig', GeneralUtility::camelCaseToDashed($this->getKeyword())),
            'preview/data-dispatcher/default.html.twig',
        ];
    }

    protected function getPreviewData(array $data): array
    {
        return [
            'dataDispatcher' => $this,
            'keyword' => $this->getKeyword(),
            'class' => $this::class,
            'data' => $data,
        ];
    }

    public function preview(array $data): string
    {
        $viewData = $this->getPreviewData($data);
        $templateNameCandidates = $this->getTemplateNameCandidates();

        $config = [
            TwigTemplateEngine::KEY_TEMPLATE => '',
            TwigTemplateEngine::KEY_TEMPLATE_NAME => $templateNameCandidates,
        ];

        return $this->templateEngine->render($config, $viewData);
    }
}
