<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Block\Adminhtml\Submission;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Panth\DynamicForms\Model\SubmissionFactory;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Submission as SubmissionResource;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Model\ResourceModel\SubmissionValue\CollectionFactory as ValueCollectionFactory;
use Panth\DynamicForms\Model\Config\Source\FormStatus;

class View extends Template
{
    private SubmissionFactory $submissionFactory;
    private SubmissionResource $submissionResource;
    private FormFactory $formFactory;
    private FormResource $formResource;
    private ValueCollectionFactory $valueCollectionFactory;
    private FormStatus $formStatus;
    private Json $json;
    private ?\Panth\DynamicForms\Model\Submission $submission = null;

    public function __construct(
        Context $context,
        SubmissionFactory $submissionFactory,
        SubmissionResource $submissionResource,
        FormFactory $formFactory,
        FormResource $formResource,
        ValueCollectionFactory $valueCollectionFactory,
        FormStatus $formStatus,
        Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->submissionFactory = $submissionFactory;
        $this->submissionResource = $submissionResource;
        $this->formFactory = $formFactory;
        $this->formResource = $formResource;
        $this->valueCollectionFactory = $valueCollectionFactory;
        $this->formStatus = $formStatus;
        $this->json = $json;
    }

    public function getSubmission(): \Panth\DynamicForms\Model\Submission
    {
        if ($this->submission === null) {
            $submissionId = (int) $this->getRequest()->getParam('submission_id');
            $this->submission = $this->submissionFactory->create();
            $this->submissionResource->load($this->submission, $submissionId);
        }

        return $this->submission;
    }

    public function getForm(): \Panth\DynamicForms\Model\Form
    {
        $formId = (int) $this->getSubmission()->getData('form_id');
        $form = $this->formFactory->create();
        $this->formResource->load($form, $formId);

        return $form;
    }

    public function getSubmissionValues(): array
    {
        $submissionId = (int) $this->getSubmission()->getId();
        $collection = $this->valueCollectionFactory->create();
        $collection->addFieldToFilter('submission_id', $submissionId);

        $values = [];
        foreach ($collection as $value) {
            $values[] = $value->getData();
        }

        return $values;
    }

    public function getStatusOptions(): array
    {
        return $this->formStatus->toOptionArray();
    }

    public function getStatusColor(string $status): string
    {
        $colorMap = [
            'new' => '#1979c3',
            'read' => '#f0ad4e',
            'replied' => '#79a22e',
            'closed' => '#999999',
        ];

        return $colorMap[$status] ?? '#333333';
    }

    public function getUpdateStatusUrl(): string
    {
        return $this->getUrl('panth_dynamicforms/submission/updateStatus');
    }

    public function getBackUrl(): string
    {
        $formId = (int) $this->getSubmission()->getData('form_id');

        return $this->getUrl('panth_dynamicforms/submission/index', ['form_id' => $formId]);
    }

    public function getDeleteUrl(): string
    {
        return $this->getUrl('panth_dynamicforms/submission/delete', [
            'submission_id' => $this->getSubmission()->getId(),
            'form_id' => $this->getSubmission()->getData('form_id'),
        ]);
    }
}
