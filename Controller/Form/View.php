<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Form;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\PageFactory;
use Panth\DynamicForms\Model\ResourceModel\Form\CollectionFactory as FormCollectionFactory;
use Panth\DynamicForms\Helper\Data as Helper;
use Magento\Framework\Registry;

class View implements HttpGetActionInterface
{
    private RequestInterface $request;
    private PageFactory $pageFactory;
    private ForwardFactory $forwardFactory;
    private FormCollectionFactory $formCollectionFactory;
    private Helper $helper;
    private Registry $registry;

    public function __construct(
        RequestInterface $request,
        PageFactory $pageFactory,
        ForwardFactory $forwardFactory,
        FormCollectionFactory $formCollectionFactory,
        Helper $helper,
        Registry $registry
    ) {
        $this->request = $request;
        $this->pageFactory = $pageFactory;
        $this->forwardFactory = $forwardFactory;
        $this->formCollectionFactory = $formCollectionFactory;
        $this->helper = $helper;
        $this->registry = $registry;
    }

    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        if (!$this->helper->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $urlKey = $this->request->getParam('url_key');
        if (!$urlKey) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $collection = $this->formCollectionFactory->create();
        $collection->addFieldToFilter('url_key', $urlKey)
            ->addFieldToFilter('is_active', 1)
            ->setPageSize(1);

        $form = $collection->getFirstItem();
        if (!$form || !$form->getId()) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        // Register form for use in blocks
        $this->registry->register('current_dynamic_form', $form);

        /** @var \Magento\Framework\View\Result\Page $page */
        $page = $this->pageFactory->create();

        $title = $form->getData('title') ?: $form->getData('name');
        $metaTitle = $form->getData('meta_title');
        $page->getConfig()->getTitle()->set($metaTitle ?: $title);

        // Set meta description
        $metaDescription = $form->getData('meta_description');
        if (!$metaDescription) {
            $description = $form->getData('description');
            $metaDescription = $description ? mb_substr(strip_tags($description), 0, 255) : '';
        }
        if ($metaDescription) {
            $page->getConfig()->setDescription($metaDescription);
        }

        // Set meta keywords
        $metaKeywords = $form->getData('meta_keywords');
        if ($metaKeywords) {
            $page->getConfig()->setKeywords($metaKeywords);
        }

        // Set meta robots
        $metaRobots = $form->getData('meta_robots') ?: 'index,follow';
        $page->getConfig()->setRobots($metaRobots);

        // Set canonical URL
        $urlKey = $form->getData('url_key');
        if ($urlKey) {
            $baseUrl = rtrim($this->request->getDistroBaseUrl(), '/');
            $canonicalUrl = $baseUrl . '/pages/' . $urlKey;
            $page->getConfig()->addRemotePageAsset(
                $canonicalUrl,
                'canonical',
                ['attributes' => ['rel' => 'canonical']]
            );
        }

        return $page;
    }
}
