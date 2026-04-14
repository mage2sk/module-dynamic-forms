<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class FormActions extends Column
{
    private UrlInterface $urlBuilder;
    private StoreManagerInterface $storeManager;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $baseUrl = $this->storeManager->getStore()->getBaseUrl();

            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['form_id'])) {
                    $formId = $item['form_id'];
                    $actions = [
                        'edit' => [
                            'href' => $this->urlBuilder->getUrl(
                                'panth_dynamicforms/form/edit',
                                ['form_id' => $formId]
                            ),
                            'label' => __('Edit'),
                        ],
                        'submissions' => [
                            'href' => $this->urlBuilder->getUrl(
                                'panth_dynamicforms/submission/index',
                                ['form_id' => $formId]
                            ),
                            'label' => __('View Submissions'),
                        ],
                    ];

                    $urlKey = $item['url_key'] ?? '';
                    $formType = $item['form_type'] ?? 'page';
                    if ($urlKey !== '' && in_array($formType, ['page', 'both'])) {
                        $actions['view_page'] = [
                            'href' => $baseUrl . 'pages/' . $urlKey,
                            'label' => __('View Page'),
                            'target' => '_blank',
                        ];
                    }

                    $actions['delete'] = [
                        'href' => $this->urlBuilder->getUrl(
                            'panth_dynamicforms/form/delete',
                            ['form_id' => $formId]
                        ),
                        'label' => __('Delete'),
                        'confirm' => [
                            'title' => __('Delete Form'),
                            'message' => __('Are you sure you want to delete this form? All submissions will also be deleted.'),
                        ],
                    ];

                    $item[$this->getData('name')] = $actions;
                }
            }
        }

        return $dataSource;
    }
}
