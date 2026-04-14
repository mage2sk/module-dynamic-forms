<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Ui\DataProvider;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

class SubmissionListingDataProvider extends DataProvider
{
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
    }

    public function getData(): array
    {
        $this->applyFormIdFilter();
        return parent::getData();
    }

    private function applyFormIdFilter(): void
    {
        $formId = (int) $this->request->getParam('form_id');
        if ($formId) {
            $this->addFilter(
                $this->filterBuilder
                    ->setField('form_id')
                    ->setValue($formId)
                    ->setConditionType('eq')
                    ->create()
            );
        }
    }
}
