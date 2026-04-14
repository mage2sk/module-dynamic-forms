<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class SubmissionStatus extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['status'])) {
                    $status = $item['status'];
                    $colorMap = [
                        'new' => '#1979c3',
                        'read' => '#f0ad4e',
                        'replied' => '#79a22e',
                        'closed' => '#999999',
                    ];
                    $color = $colorMap[$status] ?? '#333333';
                    $item['status_html'] = sprintf(
                        '<span style="display:inline-block;padding:2px 10px;border-radius:3px;color:#fff;background:%s;font-size:12px;">%s</span>',
                        $color,
                        ucfirst($status)
                    );
                }
            }
        }

        return $dataSource;
    }
}
