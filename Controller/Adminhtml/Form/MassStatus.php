<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Adminhtml\Form;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Panth\DynamicForms\Model\ResourceModel\Form\CollectionFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;

class MassStatus extends Action implements HttpPostActionInterface
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
        $status = (string) $this->getRequest()->getParam('status');

        if (!in_array($status, ['0', '1'], true)) {
            $this->messageManager->addErrorMessage(__('Invalid status.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;

            foreach ($collection as $form) {
                $form->setData('is_active', (int) $status);
                $this->formResource->save($form);
                $count++;
            }

            $this->messageManager->addSuccessMessage(
                $status === '1'
                    ? __('A total of %1 form(s) have been enabled.', $count)
                    : __('A total of %1 form(s) have been disabled.', $count)
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
