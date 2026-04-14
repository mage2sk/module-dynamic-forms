<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Panth\DynamicForms\Model\ResourceModel\Form\CollectionFactory;

class FormList implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var array|null
     */
    private ?array $options = null;

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Return array of active forms as options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $this->options = [];
            $this->options[] = [
                'value' => '',
                'label' => __('-- Please Select --')
            ];

            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('is_active', 1);
            $collection->setOrder('name', 'ASC');

            foreach ($collection as $form) {
                $this->options[] = [
                    'value' => $form->getId(),
                    'label' => $form->getData('name')
                ];
            }
        }

        return $this->options;
    }
}
