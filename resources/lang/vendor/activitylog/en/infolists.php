<?php

return [
    'components' => [
        'created_by_at'             => '<strong>:subject</strong> has been <strong>:event</strong> by <strong>:causer</strong>. <br><small> Updated at: <strong>:update_at</strong></small>',
        'updater_updated'           => ':causer :event the following data: <br>:changes',
        'from_oldvalue_to_newvalue' => '- :key from <strong>:old_value</strong> to <strong>:new_value</strong>',
        'to_newvalue'               => '- :key <strong>:new_value</strong>',
        'unknown'                   => 'Unknown',
    ],
];
