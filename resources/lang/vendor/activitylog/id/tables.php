<?php

return [
    'columns' => [
        'log_name' => [
            'label' => 'Jenis',
        ],
        'event' => [
            'label' => 'Aktivitas',
        ],
        'subject_type' => [
            'label'        => 'Data',
            'soft_deleted' => ' (Dihapus Sementara)',
            'deleted'      => ' (Dihapus)',
        ],
        'causer' => [
            'label' => 'Pengguna',
        ],
        'properties' => [
            'label' => 'Detail',
        ],
        'created_at' => [
            'label' => 'Waktu Tercatat',
        ],
    ],
    'filters' => [
        'created_at' => [
            'label'                   => 'Waktu Tercatat',
            'created_from'            => 'Dari tanggal ',
            'created_from_indicator'  => 'Dari tanggal: :created_from',
            'created_until'           => 'Sampai tanggal ',
            'created_until_indicator' => 'Sampai tanggal: :created_until',
        ],
        'event' => [
            'label' => 'Aktivitas',
        ],
    ],
];
