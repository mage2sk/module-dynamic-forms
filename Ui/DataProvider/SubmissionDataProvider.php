<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Ui\DataProvider;

use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Panth\DynamicForms\Model\ResourceModel\Submission\CollectionFactory;

class SubmissionDataProvider extends AbstractDataProvider
{
    private RequestInterface $request;
    private ?array $loadedData = null;

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->request = $request;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $formId = (int) $this->request->getParam('form_id');
        if ($formId) {
            $this->collection->addFieldToFilter('form_id', $formId);
        }

        $this->loadedData = [];
        $items = $this->collection->getItems();

        foreach ($items as $submission) {
            $this->loadedData[$submission->getId()] = $submission->getData();
        }

        return $this->loadedData;
    }
}
