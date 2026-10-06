<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Ui\DataProvider;

class FormListingDataProvider extends KeywordSearchDataProvider
{
    protected function getKeywordFields(): array
    {
        return ['name', 'title', 'url_key', 'admin_email'];
    }
}
