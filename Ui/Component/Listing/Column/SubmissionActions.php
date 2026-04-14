<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class SubmissionActions extends Column
{
    private UrlInterface $urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['submission_id'])) {
                    $submissionId = $item['submission_id'];
                    $formId = $item['form_id'] ?? 0;
                    $item[$this->getData('name')] = [
                        'view' => [
                            'href' => $this->urlBuilder->getUrl(
                                'panth_dynamicforms/submission/view',
                                ['submission_id' => $submissionId]
                            ),
                            'label' => __('View'),
                        ],
                        'delete' => [
                            'href' => $this->urlBuilder->getUrl(
                                'panth_dynamicforms/submission/delete',
                                [
                                    'submission_id' => $submissionId,
                                    'form_id' => $formId,
                                ]
                            ),
                            'label' => __('Delete'),
                            'confirm' => [
                                'title' => __('Delete Submission'),
                                'message' => __('Are you sure you want to delete this submission?'),
                            ],
                        ],
                    ];
                }
            }
        }

        return $dataSource;
    }
}
