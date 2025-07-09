<?php

return [
    'columns' => [
        'log_name' => [
            'label' => 'Type',
        ],
        'event' => [
            'label' => 'Activity',
        ],
        'subject_type' => [
            'label'        => 'Data',
            'soft_deleted' => ' (Soft Deleted)',
            'deleted'      => ' (Deleted)',
        ],
        'causer' => [
            'label' => 'User',
        ],
        'properties' => [
            'label' => 'Details',
        ],
        'created_at' => [
            'label' => 'Recorded Time',
        ],
    ],
    'filters' => [
        'created_at' => [
            'label'                   => 'Recorded Time',
            'created_from'            => 'From date ',
            'created_from_indicator'  => 'From date: :created_from',
            'created_until'           => 'Until date ',
            'created_until_indicator' => 'Until date: :created_until',
        ],
        'event' => [
            'label' => 'Activity',
        ],
    ],
];
