<?php

return [
    'modal' => [
        'heading'     => 'User Activity Log',
        'description' => 'Track all user activities',
        'tooltip'     => 'User Activities',
    ],
    'event' => [
        'created'  => 'created',
        'deleted'  => 'deleted',
        'updated'  => 'updated',
        'restored' => 'restored',
        'export'   => 'exported',
        'import'   => 'imported',
        'export_completed' => 'export completed',
        'import_completed' => 'import completed',
    ],
    'view'                => 'View',
    'edit'                => 'Edit',
    'restore'             => 'Restore',
    'restore_soft_delete' => [
        'label'             => 'Restore Data',
        'modal_heading'     => 'Restore Deleted Data',
        'modal_description' => 'This action will restore the deleted data.',
    ],
];
