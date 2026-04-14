<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Ui\Component\Listing\Column;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class SubmissionCount extends Column
{
    private ResourceConnection $resourceConnection;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->resourceConnection = $resourceConnection;
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $formIds = array_column($dataSource['data']['items'], 'form_id');
            $counts = $this->getSubmissionCounts($formIds);

            foreach ($dataSource['data']['items'] as &$item) {
                $formId = (int) ($item['form_id'] ?? 0);
                $item[$this->getData('name')] = (int) ($counts[$formId] ?? 0);
            }
        }

        return $dataSource;
    }

    private function getSubmissionCounts(array $formIds): array
    {
        if (empty($formIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('panth_dynamic_form_submission');

        $select = $connection->select()
            ->from($tableName, ['form_id', 'submission_count' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('form_id IN (?)', $formIds)
            ->group('form_id');

        $result = $connection->fetchPairs($select);

        return $result;
    }
}
