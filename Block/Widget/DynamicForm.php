<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Model\ResourceModel\Field\CollectionFactory as FieldCollectionFactory;
use Panth\DynamicForms\Helper\Data as Helper;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Registry;
use Panth\Core\Helper\Theme as ThemeHelper;

class DynamicForm extends Template implements BlockInterface
{
    private FormFactory $formFactory;
    private FormResource $formResource;
    private FieldCollectionFactory $fieldCollectionFactory;
    private Helper $helper;
    private FilterProvider $filterProvider;
    private Registry $registry;
    private ThemeHelper $themeHelper;
    private FormKey $formKey;

    private ?\Panth\DynamicForms\Model\Form $form = null;

    private ?array $fields = null;

    public function __construct(
        Template\Context $context,
        FormFactory $formFactory,
        FormResource $formResource,
        FieldCollectionFactory $fieldCollectionFactory,
        Helper $helper,
        FilterProvider $filterProvider,
        Registry $registry,
        ThemeHelper $themeHelper,
        FormKey $formKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->formFactory = $formFactory;
        $this->formResource = $formResource;
        $this->fieldCollectionFactory = $fieldCollectionFactory;
        $this->helper = $helper;
        $this->filterProvider = $filterProvider;
        $this->registry = $registry;
        $this->themeHelper = $themeHelper;
        $this->formKey = $formKey;
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getForm(): ?\Panth\DynamicForms\Model\Form
    {
        if ($this->form !== null) {
            return $this->form;
        }

        $registeredForm = $this->registry->registry('current_dynamic_form');
        if ($registeredForm) {
            $this->form = $registeredForm;
            return $this->form;
        }

        $formId = (int) $this->getData('form_id');
        if (!$formId) {
            return null;
        }

        $form = $this->formFactory->create();
        $this->formResource->load($form, $formId);

        if (!$form->getId() || !$form->getData('is_active')) {
            return null;
        }

        $this->form = $form;
        return $this->form;
    }

    public function getFields(): array
    {
        if ($this->fields !== null) {
            return $this->fields;
        }

        $form = $this->getForm();
        if (!$form) {
            $this->fields = [];
            return $this->fields;
        }

        $collection = $this->fieldCollectionFactory->create();
        $collection->addFieldToFilter('form_id', $form->getId())
            ->addFieldToFilter('is_active', 1)
            ->setOrder('sort_order', 'ASC');

        $this->fields = $collection->getItems();
        return $this->fields;
    }

    public function getFormUrl(): string
    {
        return $this->getUrl('dynamicforms/form/submit');
    }

    public function getUploadUrl(): string
    {
        return $this->getUrl('dynamicforms/form/upload');
    }

    public function getFormConfig(): string
    {
        $form = $this->getForm();
        if (!$form) {
            return '{}';
        }

        $config = [
            'form_id' => (int) $form->getId(),
            'submit_url' => $this->getFormUrl(),
            'upload_url' => $this->getUploadUrl(),
            'ajax_enabled' => $this->helper->isAjaxEnabled(),
            'loading_text' => __('Submitting...'),
            'success_message' => $form->getData('success_message')
                ?: (string) __('Thank you! Your form has been submitted successfully.'),
            'redirect_url' => $form->getData('redirect_url') ?: '',
            'submit_button_text' => $form->getData('submit_button_text') ?: (string) __('Submit'),
            'allowed_extensions' => $this->helper->getAllowedExtensions(),
            'max_file_size' => $this->helper->getMaxFileSize(),
            'max_file_size_mb' => $this->helper->getMaxFileSize() / (1024 * 1024),
        ];

        return json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    public function isHyvaTheme(): bool
    {
        return $this->themeHelper->isHyva();
    }

    public function renderCmsContent(?string $content): string
    {
        if (!$content) {
            return '';
        }

        try {
            return $this->filterProvider->getPageFilter()->filter($content);
        } catch (\Exception $e) {
            return htmlspecialchars((string) $content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    public function getFieldOptions(\Panth\DynamicForms\Model\Field $field): array
    {
        $options = $field->getData('options');
        if (!$options) {
            return [];
        }

        $decoded = json_decode($options, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getFieldValidationRules(\Panth\DynamicForms\Model\Field $field): array
    {
        $rules = $field->getData('validation_rules');
        if (!$rules) {
            return [];
        }

        $decoded = json_decode($rules, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getWidthClass(string $width): string
    {
        $map = [
            'full' => 'panth-df-field--full',
            'half' => 'panth-df-field--half',
            'third' => 'panth-df-field--third',
        ];

        return $map[$width] ?? 'panth-df-field--full';
    }

    public function getFormStyle(): array
    {
        $form = $this->getForm();
        if (!$form) {
            return [];
        }

        $style = $form->getData('form_style');
        if (!$style) {
            return [];
        }

        $decoded = json_decode($style, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function showTitle(): bool
    {
        $showTitle = $this->getData('show_title');
        if ($showTitle !== null) {
            return (bool) $showTitle;
        }
        return true;
    }

    public function showDescription(): bool
    {
        $showDescription = $this->getData('show_description');
        if ($showDescription !== null) {
            return (bool) $showDescription;
        }
        return true;
    }

    protected function _toHtml(): string
    {
        if (!$this->helper->isEnabled()) {
            return '';
        }

        $form = $this->getForm();
        if (!$form) {
            return '';
        }

        if (!$this->getTemplate()) {
            if ($this->isHyvaTheme()) {
                $this->setTemplate('Panth_DynamicForms::widget/form_hyva.phtml');
            } else {
                $this->setTemplate('Panth_DynamicForms::widget/form.phtml');
            }
        }

        return parent::_toHtml();
    }

    public function getFormIdentifier(): string
    {
        $form = $this->getForm();
        return 'dynamicForm_' . ($form ? $form->getId() : '0');
    }

    public function getFieldsJson(): string
    {
        $fieldsData = [];
        foreach ($this->getFields() as $field) {
            $fieldsData[] = [
                'field_id' => (int) $field->getId(),
                'name' => $field->getData('name'),
                'type' => $field->getData('field_type'),
                'label' => $field->getData('label'),
                'placeholder' => $field->getData('placeholder') ?: '',
                'default_value' => $field->getData('default_value') ?: '',
                'is_required' => (bool) $field->getData('is_required'),
                'options' => $this->getFieldOptions($field),
                'validation_rules' => $this->getFieldValidationRules($field),
                'width' => $field->getData('width') ?: 'full',
                'css_class' => $field->getData('css_class') ?: '',
            ];
        }

        return json_encode($fieldsData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
