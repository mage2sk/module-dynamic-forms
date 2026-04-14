<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Form;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Model\SubmissionFactory;
use Panth\DynamicForms\Model\ResourceModel\Submission as SubmissionResource;
use Panth\DynamicForms\Model\SubmissionValueFactory;
use Panth\DynamicForms\Model\ResourceModel\SubmissionValue as SubmissionValueResource;
use Panth\DynamicForms\Model\ResourceModel\Field\CollectionFactory as FieldCollectionFactory;
use Panth\DynamicForms\Helper\Data as Helper;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Psr\Log\LoggerInterface;

class Submit implements HttpPostActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private FormKeyValidator $formKeyValidator;
    private CustomerSession $customerSession;
    private StoreManagerInterface $storeManager;
    private FormFactory $formFactory;
    private FormResource $formResource;
    private SubmissionFactory $submissionFactory;
    private SubmissionResource $submissionResource;
    private SubmissionValueFactory $submissionValueFactory;
    private SubmissionValueResource $submissionValueResource;
    private FieldCollectionFactory $fieldCollectionFactory;
    private Helper $helper;
    private RemoteAddress $remoteAddress;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        FormKeyValidator $formKeyValidator,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManager,
        FormFactory $formFactory,
        FormResource $formResource,
        SubmissionFactory $submissionFactory,
        SubmissionResource $submissionResource,
        SubmissionValueFactory $submissionValueFactory,
        SubmissionValueResource $submissionValueResource,
        FieldCollectionFactory $fieldCollectionFactory,
        Helper $helper,
        RemoteAddress $remoteAddress,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        $this->formFactory = $formFactory;
        $this->formResource = $formResource;
        $this->submissionFactory = $submissionFactory;
        $this->submissionResource = $submissionResource;
        $this->submissionValueFactory = $submissionValueFactory;
        $this->submissionValueResource = $submissionValueResource;
        $this->fieldCollectionFactory = $fieldCollectionFactory;
        $this->helper = $helper;
        $this->remoteAddress = $remoteAddress;
        $this->logger = $logger;
    }

    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $result = $this->jsonFactory->create();

        // Validate form key
        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData([
                'success' => false,
                'message' => __('Invalid form key. Please refresh the page and try again.'),
            ]);
        }

        $formId = (int) $this->request->getParam('form_id');
        if (!$formId) {
            return $result->setData([
                'success' => false,
                'message' => __('Invalid form.'),
            ]);
        }

        // Load form
        $form = $this->formFactory->create();
        $this->formResource->load($form, $formId);

        if (!$form->getId() || !$form->getData('is_active')) {
            return $result->setData([
                'success' => false,
                'message' => __('This form is no longer available.'),
            ]);
        }

        // Load fields
        $fieldCollection = $this->fieldCollectionFactory->create();
        $fieldCollection->addFieldToFilter('form_id', $formId)
            ->addFieldToFilter('is_active', 1)
            ->setOrder('sort_order', 'ASC');

        $postData = $this->request->getParams();
        $errors = [];
        $submissionValues = [];

        /** @var \Panth\DynamicForms\Model\Field $field */
        foreach ($fieldCollection as $field) {
            $fieldName = $field->getData('name');
            $fieldType = $field->getData('field_type');
            $value = $postData[$fieldName] ?? '';

            // Handle array values (checkbox, multiselect)
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $value = trim((string) $value);

            // Required validation
            if ($field->getData('is_required') && $value === '' && $fieldType !== 'file') {
                $errors[$fieldName] = __('%1 is required.', $field->getData('label'));
                continue;
            }

            // File fields: value is the filename from AJAX upload (sent as the field name)
            if ($fieldType === 'file') {
                // The JS sends uploaded filename as fd.append(fieldName, filename)
                $fileValue = $postData[$fieldName] ?? '';
                if (is_array($fileValue)) {
                    $fileValue = '';
                }
                $fileValue = trim((string) $fileValue);
                if ($field->getData('is_required') && !$fileValue) {
                    $errors[$fieldName] = __('%1 is required.', $field->getData('label'));
                    continue;
                }
                // Make it a clickable URL for admin view
                if ($fileValue) {
                    $value = $this->helper->getFileUrl($fileValue);
                } else {
                    $value = '';
                }
            }

            // Type-specific validation
            $validationRules = $field->getData('validation_rules');
            if ($validationRules) {
                $rules = json_decode($validationRules, true);
                if (is_array($rules) && $value !== '') {
                    $fieldError = $this->validateFieldValue($value, $rules, $field->getData('label'));
                    if ($fieldError) {
                        $errors[$fieldName] = $fieldError;
                        continue;
                    }
                }
            }

            // Email format validation
            if ($fieldType === 'email' && $value !== '') {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$fieldName] = __('Please enter a valid email address.');
                    continue;
                }
            }

            // Phone format validation
            if ($fieldType === 'phone' && $value !== '') {
                if (!preg_match('/^[\d\s\-\+\(\)\.]{7,20}$/', $value)) {
                    $errors[$fieldName] = __('Please enter a valid phone number.');
                    continue;
                }
            }

            // Number validation
            if ($fieldType === 'number' && $value !== '') {
                if (!is_numeric($value)) {
                    $errors[$fieldName] = __('Please enter a valid number.');
                    continue;
                }
            }

            $submissionValues[] = [
                'field_id' => (int) $field->getId(),
                'label' => $field->getData('label'),
                'type' => $fieldType,
                'value' => $value,
            ];
        }

        if (!empty($errors)) {
            return $result->setData([
                'success' => false,
                'message' => __('Please correct the errors below.'),
                'errors' => $errors,
            ]);
        }

        try {
            // Determine customer info
            $customerEmail = '';
            $customerName = '';
            $customerId = null;

            if ($this->customerSession->isLoggedIn()) {
                $customer = $this->customerSession->getCustomer();
                $customerEmail = $customer->getEmail();
                $customerName = $customer->getName();
                $customerId = (int) $customer->getId();
            }

            // Check if any field value is an email (for guest submissions)
            if (!$customerEmail) {
                foreach ($submissionValues as $sv) {
                    if ($sv['type'] === 'email' && $sv['value']) {
                        $customerEmail = $sv['value'];
                        break;
                    }
                }
            }

            // Check for name field
            if (!$customerName) {
                foreach ($submissionValues as $sv) {
                    $labelLower = strtolower($sv['label']);
                    if (in_array($labelLower, ['name', 'full name', 'your name']) && $sv['value']) {
                        $customerName = $sv['value'];
                        break;
                    }
                }
            }

            // Create submission
            $submission = $this->submissionFactory->create();
            $submission->setData([
                'form_id' => $formId,
                'customer_id' => $customerId,
                'customer_email' => $customerEmail,
                'customer_name' => $customerName,
                'customer_ip' => $this->remoteAddress->getRemoteAddress(),
                'store_id' => (int) $this->storeManager->getStore()->getId(),
                'status' => 'new',
            ]);
            $this->submissionResource->save($submission);

            // Save submission values
            $emailValues = [];
            foreach ($submissionValues as $sv) {
                $submissionValue = $this->submissionValueFactory->create();
                $submissionValue->setData([
                    'submission_id' => (int) $submission->getId(),
                    'field_id' => $sv['field_id'],
                    'field_label' => $sv['label'],
                    'field_type' => $sv['type'],
                    'value' => $sv['value'],
                ]);
                $this->submissionValueResource->save($submissionValue);

                $emailValues[] = [
                    'label' => $sv['label'],
                    'value' => $sv['value'],
                    'type' => $sv['type'],
                ];
            }

            // Send admin notification
            $this->helper->sendAdminNotification($form, $submission, $emailValues);

            // Send auto-reply
            $this->helper->sendAutoReply($form, $submission);

            $successMessage = $form->getData('success_message')
                ?: __('Thank you! Your form has been submitted successfully.');
            $redirectUrl = $form->getData('redirect_url') ?: '';

            return $result->setData([
                'success' => true,
                'message' => $successMessage,
                'redirect_url' => $redirectUrl,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('DynamicForms submission error: ' . $e->getMessage(), [
                'form_id' => $formId,
                'trace' => $e->getTraceAsString(),
            ]);

            return $result->setData([
                'success' => false,
                'message' => __('An error occurred while submitting the form. Please try again.'),
            ]);
        }
    }

    /**
     * Validate a field value against custom rules
     */
    private function validateFieldValue(string $value, array $rules, string $label): ?string
    {
        if (isset($rules['min_length']) && mb_strlen($value) < (int) $rules['min_length']) {
            return (string) __('%1 must be at least %2 characters.', $label, $rules['min_length']);
        }

        if (isset($rules['max_length']) && mb_strlen($value) > (int) $rules['max_length']) {
            return (string) __('%1 must be no more than %2 characters.', $label, $rules['max_length']);
        }

        if (isset($rules['min']) && is_numeric($value) && (float) $value < (float) $rules['min']) {
            return (string) __('%1 must be at least %2.', $label, $rules['min']);
        }

        if (isset($rules['max']) && is_numeric($value) && (float) $value > (float) $rules['max']) {
            return (string) __('%1 must be no more than %2.', $label, $rules['max']);
        }

        if (isset($rules['pattern']) && !preg_match('/' . $rules['pattern'] . '/', $value)) {
            $msg = $rules['pattern_message'] ?? __('Please enter a valid value for %1.', $label);
            return (string) $msg;
        }

        return null;
    }
}
