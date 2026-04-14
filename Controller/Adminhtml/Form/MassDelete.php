<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Adminhtml\Form;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Panth\DynamicForms\Model\ResourceModel\Form\CollectionFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;

class MassDelete extends Action
{
    public const ADMIN_RESOURCE = 'Panth_DynamicForms::form';

    private Filter $filter;
    private CollectionFactory $collectionFactory;
    private FormResource $formResource;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        FormResource $formResource
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->formResource = $formResource;
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;

            foreach ($collection as $form) {
                $this->formResource->delete($form);
                $count++;
            }

            $this->messageManager->addSuccessMessage(
                __('A total of %1 form(s) have been deleted.', $count)
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
