<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

abstract class KeywordSearchDataProvider extends DataProvider
{
    private string $keyword = '';

    abstract protected function getKeywordFields(): array;

    public function addFilter(Filter $filter)
    {
        if ($filter->getConditionType() !== 'fulltext') {
            parent::addFilter($filter);
            return;
        }

        $value = $filter->getValue();
        $this->keyword = is_scalar($value) ? trim((string) $value) : '';
    }

    public function getSearchResult()
    {
        $result = parent::getSearchResult();

        if ($this->keyword === '' || !$result instanceof AbstractDb) {
            return $result;
        }

        $connection = $result->getConnection();
        $like = '%' . addcslashes($this->keyword, '\\%_') . '%';
        $conditions = [];
        foreach ($this->getKeywordFields() as $field) {
            $conditions[] = $connection->quoteInto(
                $connection->quoteIdentifier('main_table.' . $field) . ' LIKE ?',
                $like
            );
        }

        if ($conditions !== []) {
            $result->getSelect()->where(implode(' OR ', $conditions));
        }

        return $result;
    }
}
