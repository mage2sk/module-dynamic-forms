<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Panth\DynamicForms\Model\ResourceModel\Form\CollectionFactory as FormCollectionFactory;
use Panth\DynamicForms\Helper\Data as Helper;

class Router implements RouterInterface
{
    private ActionFactory $actionFactory;
    private FormCollectionFactory $formCollectionFactory;
    private Helper $helper;

    public function __construct(
        ActionFactory $actionFactory,
        FormCollectionFactory $formCollectionFactory,
        Helper $helper
    ) {
        $this->actionFactory = $actionFactory;
        $this->formCollectionFactory = $formCollectionFactory;
        $this->helper = $helper;
    }

    /**
     * Match /pages/{url_key} and forward to Form/View controller
     */
    public function match(RequestInterface $request): ?\Magento\Framework\App\ActionInterface
    {
        if (!$this->helper->isEnabled()) {
            return null;
        }

        // Prevent re-matching on forwarded requests
        if ($request->getModuleName() === 'dynamicforms') {
            return null;
        }

        $identifier = trim($request->getPathInfo(), '/');

        // Match pattern: pages/{url_key}
        if (!preg_match('#^pages/([a-zA-Z0-9_-]+)$#', $identifier, $matches)) {
            return null;
        }

        $urlKey = $matches[1];

        // Verify the form exists, is active, and has page type (not widget-only)
        $collection = $this->formCollectionFactory->create();
        $collection->addFieldToFilter('url_key', $urlKey)
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('form_type', ['in' => ['page', 'both']])
            ->setPageSize(1);

        if ($collection->getSize() === 0) {
            return null;
        }

        // Forward to the controller
        $request->setModuleName('dynamicforms')
            ->setControllerName('form')
            ->setActionName('view')
            ->setParam('url_key', $urlKey);

        $request->setAlias(
            \Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS,
            $identifier
        );

        return $this->actionFactory->create(
            \Magento\Framework\App\Action\Forward::class
        );
    }
}
