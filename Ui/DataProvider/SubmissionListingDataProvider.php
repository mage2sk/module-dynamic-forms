<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Ui\DataProvider;

class SubmissionListingDataProvider extends KeywordSearchDataProvider
{
    protected function getKeywordFields(): array
    {
        return ['customer_name', 'customer_email', 'customer_ip', 'admin_notes'];
    }
}
