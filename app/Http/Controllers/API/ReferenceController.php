<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use Apriansyahrs\CustomFields\Models\CustomField;
use Apriansyahrs\CustomFields\Models\CustomFieldSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ReferenceController extends Controller
{
    public function badanUsaha(Request $request)
    {
        try {
            $user = Auth::user();
            $scopes = $user ? $user->userScopes : [];
            $cacheKey = 'ref_badan_usaha_' . ($user ? $user->id : 'guest');
            $data = Cache::remember($cacheKey, 3600, function () use ($scopes) {
                $query = BadanUsaha::query();
                if ($scopes && count($scopes)) {
                    $query->where(function ($q) use ($scopes) {
                        foreach ($scopes as $scope) {
                            if ($scope->badan_usaha_id) {
                                $ids = is_array($scope->badan_usaha_id) ? $scope->badan_usaha_id : [$scope->badan_usaha_id];
                                $q->orWhereIn('id', $ids);
                            }
                        }
                    });
                }

                $results = $query->select('id', 'name')->get()->sortBy('name');
                return $results->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                    ];
                })->values();
            });

            return ResponseFormatter::success($data, 'Data badan usaha berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data badan usaha', 500);
        }
    }

    public function division(Request $request)
    {
        try {
            $user = Auth::user();
            $scopes = $user ? $user->userScopes : [];
            $badanUsahaId = $request->query('badan_usaha_id');
            $cacheKey = 'ref_division_' . ($user ? $user->id : 'guest') . '_' . ($badanUsahaId ?: 'all');
            $data = Cache::remember($cacheKey, 3600, function () use ($scopes, $badanUsahaId) {
                $query = Division::query();
                if ($badanUsahaId) {
                    $query->where('badan_usaha_id', $badanUsahaId);
                }
                if ($scopes && count($scopes)) {
                    $query->where(function ($q) use ($scopes) {
                        foreach ($scopes as $scope) {
                            if ($scope->division_id) {
                                $ids = is_array($scope->division_id) ? $scope->division_id : [$scope->division_id];
                                $q->orWhereIn('id', $ids);
                            } elseif ($scope->badan_usaha_id) {
                                $ids = is_array($scope->badan_usaha_id) ? $scope->badan_usaha_id : [$scope->badan_usaha_id];
                                $q->orWhereIn('badan_usaha_id', $ids);
                            }
                        }
                    });
                }

                $results = $query->select('id', 'name')->get()->sortBy('name');
                return $results->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                    ];
                })->values();
            });

            return ResponseFormatter::success($data, 'Data divisi berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data divisi', 500);
        }
    }

    public function region(Request $request)
    {
        try {
            $user = Auth::user();
            $scopes = $user ? $user->userScopes : [];
            $divisionId = $request->query('division_id');
            $cacheKey = 'ref_region_' . ($user ? $user->id : 'guest') . '_' . ($divisionId ?: 'all');
            $data = Cache::remember($cacheKey, 3600, function () use ($scopes, $divisionId) {
                $query = Region::query();
                if ($divisionId) {
                    $query->where('division_id', $divisionId);
                }
                if ($scopes && count($scopes)) {
                    $query->where(function ($q) use ($scopes) {
                        foreach ($scopes as $scope) {
                            if ($scope->region_id) {
                                $ids = is_array($scope->region_id) ? $scope->region_id : [$scope->region_id];
                                $q->orWhereIn('id', $ids);
                            } elseif ($scope->division_id) {
                                $ids = is_array($scope->division_id) ? $scope->division_id : [$scope->division_id];
                                $q->orWhereIn('division_id', $ids);
                            } elseif ($scope->badan_usaha_id) {
                                $ids = is_array($scope->badan_usaha_id) ? $scope->badan_usaha_id : [$scope->badan_usaha_id];
                                $q->orWhereIn('badan_usaha_id', $ids);
                            }
                        }
                    });
                }

                $results = $query->select('id', 'name')->get()->sortBy('name');
                return $results->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                    ];
                })->values();
            });

            return ResponseFormatter::success($data, 'Data region berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data region', 500);
        }
    }

    public function cluster(Request $request)
    {
        try {
            $user = Auth::user();
            $scopes = $user ? $user->userScopes : [];
            $regionId = $request->query('region_id');
            $cacheKey = 'ref_cluster_' . ($user ? $user->id : 'guest') . '_' . ($regionId ?: 'all');
            $data = Cache::remember($cacheKey, 3600, function () use ($scopes, $regionId) {
                $query = Cluster::query();
                if ($regionId) {
                    $query->where('region_id', $regionId);
                }
                if ($scopes && count($scopes)) {
                    $query->where(function ($q) use ($scopes) {
                        foreach ($scopes as $scope) {
                            if ($scope->cluster_id) {
                                $ids = is_array($scope->cluster_id) ? $scope->cluster_id : [$scope->cluster_id];
                                $q->orWhereIn('id', $ids);
                            } elseif ($scope->region_id) {
                                $ids = is_array($scope->region_id) ? $scope->region_id : [$scope->region_id];
                                $q->orWhereIn('region_id', $ids);
                            } elseif ($scope->division_id) {
                                $ids = is_array($scope->division_id) ? $scope->division_id : [$scope->division_id];
                                $q->orWhereIn('division_id', $ids);
                            } elseif ($scope->badan_usaha_id) {
                                $ids = is_array($scope->badan_usaha_id) ? $scope->badan_usaha_id : [$scope->badan_usaha_id];
                                $q->orWhereIn('badan_usaha_id', $ids);
                            }
                        }
                    });
                }

                $results = $query->select('id', 'name')->get()->sortBy('name');
                return $results->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                    ];
                })->values();
            });

            return ResponseFormatter::success($data, 'Data cluster berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data cluster', 500);
        }
    }

    public function role(Request $request)
    {
        try {
            $user = Auth::user();
            if (! $user || ! $user->role) {
                return ResponseFormatter::error(null, 'User tidak memiliki role', 403);
            }
            // Jika role user adalah root (parent_id null), ambil semua role kecuali dirinya sendiri
            if ($user->role->parent_id === null) {
                $data = \App\Models\Role::where('id', '!=', $user->role->id)
                    ->orderBy('name', 'asc')
                    ->select('id', 'name', 'scope_required_fields', 'scope_multiple_fields')
                    ->get();

                return ResponseFormatter::success($data, 'Data role berhasil diambil');
            }
            // Jika bukan root, ambil semua descendant role dari role user
            $descendants = $user->role->allDescendants();
            $data = $descendants->sortBy('name')->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'scope_required_fields' => $role->scope_required_fields,
                    'scope_multiple_fields' => $role->scope_multiple_fields,
                ];
            })->values();

            return ResponseFormatter::success($data, 'Data role berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data role', 500);
        }
    }

    /**
     * Get custom fields configuration for a specific entity type
     */
    public function customFields(Request $request)
    {
        try {
            $entityType = $request->query('entity_type');

            if (!$entityType) {
                return ResponseFormatter::error(null, 'Entity type is required', 400);
            }

            // Normalize entity type to full class name
            $normalizedEntityType = $this->normalizeEntityType($entityType);

            $cacheKey = "ref_custom_fields_{$normalizedEntityType}";

            $data = Cache::remember($cacheKey, 3600, function () use ($normalizedEntityType) {
                // Get sections for the entity type
                $sections = CustomFieldSection::where('entity_type', $normalizedEntityType)
                    ->where('active', true)
                    ->with(['fields' => function ($query) {
                        $query->where('active', true)
                            ->with('options');
                    }])
                    ->orderBy('sort_order')
                    ->get();

                // If no sections found, try to get custom fields directly without sections
                if ($sections->isEmpty()) {
                    $customFields = CustomField::where('entity_type', $normalizedEntityType)
                        ->where('active', true)
                        ->with('options')
                        ->orderBy('sort_order')
                        ->get();

                    // If we have custom fields without sections, create a default section
                    if ($customFields->isNotEmpty()) {
                        return [[
                            'id' => null,
                            'code' => 'default',
                            'name' => 'Custom Fields',
                            'type' => 'section',
                            'description' => null,
                            'sort_order' => 1,
                            'settings' => [],
                            'custom_fields' => $customFields->map(function ($field) {
                                return [
                                    'id' => $field->id,
                                    'code' => $field->code,
                                    'name' => $field->name,
                                    'type' => $field->type,
                                    'width' => $field->width,
                                    'lookup_type' => $field->lookup_type,
                                    'sort_order' => $field->sort_order,
                                    'validation_rules' => $field->validation_rules,
                                    'settings' => $field->settings,
                                    'options' => $field->options->map(function ($option) {
                                        return [
                                            'id' => $option->id,
                                            'name' => $option->name,
                                            'sort_order' => $option->sort_order,
                                        ];
                                    })->values(),
                                ];
                            })->values(),
                        ]];
                    }
                }

                return $sections->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'code' => $section->code,
                        'name' => $section->name,
                        'type' => $section->type,
                        'description' => $section->description,
                        'sort_order' => $section->sort_order,
                        'settings' => $section->settings,
                        'custom_fields' => $section->fields->map(function ($field) {
                            return [
                                'id' => $field->id,
                                'code' => $field->code,
                                'name' => $field->name,
                                'type' => $field->type,
                                'width' => $field->width,
                                'lookup_type' => $field->lookup_type,
                                'sort_order' => $field->sort_order,
                                'validation_rules' => $field->validation_rules,
                                'settings' => $field->settings,
                                'options' => $field->options->map(function ($option) {
                                    return [
                                        'id' => $option->id,
                                        'name' => $option->name,
                                        'sort_order' => $option->sort_order,
                                    ];
                                })->values(),
                            ];
                        })->values(),
                    ];
                })->values();
            });

            return ResponseFormatter::success($data, 'Data custom fields berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data custom fields: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get custom field values for a specific entity
     */
    public function customFieldValues(Request $request)
    {
        try {
            $entityType = $request->query('entity_type');
            $entityId = $request->query('entity_id');

            if (!$entityType || !$entityId) {
                return ResponseFormatter::error(null, 'Entity type and entity ID are required', 400);
            }

            $cacheKey = "ref_custom_field_values_{$entityType}_{$entityId}";

            $data = Cache::remember($cacheKey, 1800, function () use ($entityType, $entityId) {
                // Get the model class from entity type
                $modelClass = $this->getModelClassFromEntityType($entityType);

                if (!$modelClass || !class_exists($modelClass)) {
                    throw new \Exception("Invalid entity type: {$entityType}");
                }

                $entity = $modelClass::find($entityId);

                if (!$entity) {
                    throw new \Exception("Entity not found");
                }

                // Check if entity uses custom fields
                if (!method_exists($entity, 'customFieldValues')) {
                    throw new \Exception("Entity does not support custom fields");
                }

                $values = $entity->customFieldValues()->with('customField')->get();

                return $values->map(function ($value) {
                    return [
                        'custom_field_id' => $value->custom_field_id,
                        'custom_field_code' => $value->customField->code,
                        'custom_field_name' => $value->customField->name,
                        'custom_field_type' => $value->customField->type,
                        'value' => $value->getValue(), // Get the appropriate value based on type
                        'string_value' => $value->string_value,
                        'text_value' => $value->text_value,
                        'boolean_value' => $value->boolean_value,
                        'integer_value' => $value->integer_value,
                        'float_value' => $value->float_value,
                        'date_value' => $value->date_value,
                        'datetime_value' => $value->datetime_value,
                        'json_value' => $value->json_value,
                    ];
                })->values();
            });

            return ResponseFormatter::success($data, 'Data custom field values berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data custom field values: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Map entity type to model class
     */
    private function getModelClassFromEntityType(string $entityType): ?string
    {
        $mapping = [
            'outlet' => \App\Models\Outlet::class,
            'outlets' => \App\Models\Outlet::class,
            'App\Models\Outlet' => \App\Models\Outlet::class,
            'App\\Models\\Outlet' => \App\Models\Outlet::class,
            'user' => \App\Models\User::class,
            'users' => \App\Models\User::class,
            'App\Models\User' => \App\Models\User::class,
            'App\\Models\\User' => \App\Models\User::class,
            // Add more mappings as needed
        ];

        return $mapping[$entityType] ?? null;
    }

    /**
     * Normalize entity type to full class name for database queries
     */
    private function normalizeEntityType(string $entityType): string
    {
        $modelClass = $this->getModelClassFromEntityType($entityType);

        if ($modelClass) {
            // Return the model class as-is (single backslashes for database storage)
            return $modelClass;
        }

        // If already in full format, return as is
        if (str_contains($entityType, '\\')) {
            return $entityType;
        }

        // Default fallback
        return $entityType;
    }

    /**
     * Get outlet level-specific required fields configuration
     */
    private function getOutletLevelFields(string $level, bool $includeCustomFields = true): array
    {
        $baseFields = [
            'basic_info' => [
                'section_name' => 'Informasi Dasar',
                'section_code' => 'basic_info',
                'fields' => [
                    'name' => [
                        'name' => 'Nama Outlet',
                        'type' => 'text',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string|max:255',
                        'width' => 'full'
                    ],
                    'owner_name' => [
                        'name' => 'Nama Pemilik',
                        'type' => 'text',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string|max:255',
                        'width' => 'half'
                    ],
                    'owner_phone' => [
                        'name' => 'Telepon Pemilik',
                        'type' => 'phone',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string|max:20',
                        'width' => 'half'
                    ],
                    'district' => [
                        'name' => 'Kota/Kabupaten',
                        'type' => 'text',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string',
                        'width' => 'full'
                    ],
                    'address' => [
                        'name' => 'Alamat',
                        'type' => 'textarea',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string',
                        'width' => 'full'
                    ]
                ]
            ],
            'outlet_mapping' => [
                'section_name' => 'Outlet Mapping',
                'section_code' => 'outlet_mapping',
                'fields' => [
                    'badan_usaha_id' => [
                        'name' => 'Badan Usaha',
                        'type' => 'text',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string|max:255',
                        'width' => 'full'
                    ],
                    'division_id' => [
                        'name' => 'Divisi',
                        'type' => 'text',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string|max:255',
                        'width' => 'half'
                    ],
                    'region_id' => [
                        'name' => 'Region',
                        'type' => 'phone',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string|max:20',
                        'width' => 'half'
                    ],
                    'cluster_id' => [
                        'name' => 'Cluster',
                        'type' => 'text',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string',
                        'width' => 'full'
                    ]
                ]
            ],
            'outlet_preview' => [
                'section_name' => 'Photo Video',
                'section_code' => 'outet_preview',
                'fields' => [
                    'photo_shop_sign' => [
                        'name' => 'Photo Penanda Outlet',
                        'type' => 'text',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string|max:255',
                        'width' => 'full'
                    ],
                    'photo_front' => [
                        'name' => 'Photo Nampak Depan',
                        'type' => 'text',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string|max:255',
                        'width' => 'half'
                    ],
                    'photo_left' => [
                        'name' => 'Photo Nampak Kiri',
                        'type' => 'phone',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string|max:20',
                        'width' => 'half'
                    ],
                    'photo_right' => [
                        'name' => 'Photo Nampak Kanan',
                        'type' => 'text',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string',
                        'width' => 'full'
                    ],
                    'video' => [
                        'name' => 'Video',
                        'type' => 'text',
                        'required' => true,
                        'model_field' => true,
                        'validation' => 'required|string',
                        'width' => 'full'
                    ]
                ]
            ],
        ];

        $configurations = [
            'LEAD' => $baseFields,
            'NOO' => array_merge_recursive($baseFields, [
                'basic_info' => [
                    'section_name' => 'Informasi Dasar',
                    'section_code' => 'basic_info',
                    'fields' => [
                        'owner_id_card' => [
                            'name' => 'KTP Pemilik',
                            'type' => 'text',
                            'required' => true,
                            'model_field' => false,
                            'custom_field' => 'owner_id_card',
                            'validation' => 'required|string|max:16',
                            'width' => 'half'
                        ],
                        'tax_number' => [
                            'name' => 'NPWP',
                            'type' => 'text',
                            'required' => false,
                            'model_field' => false,
                            'custom_field' => 'tax_number',
                            'validation' => 'nullable|string|max:20',
                            'width' => 'half'
                        ]
                    ]
                ],
                'outlet_preview' => [
                    'section_name' => 'Photo Video',
                    'section_code' => 'outet_preview',
                    'fields' => [
                        'photo_id_card' => [
                            'name' => 'Photo KTP Pemilik',
                            'type' => 'text',
                            'required' => true,
                            'model_field' => false,
                            'custom_field' => 'photo_id_card',
                            'validation' => 'nullable|string|max:20',
                            'width' => 'half'
                        ]
                    ]
                ]
            ]),
        ];

        // Get level configuration
        $levelConfig = $configurations[$level] ?? $configurations['LEAD'];

        // If include custom fields, inject/merge with actual custom fields
        if ($includeCustomFields) {
            $levelConfig = $this->injectCustomFieldsToLevelConfig($levelConfig);
        }

        // Transform to API response format
        return $this->transformLevelConfigToApiFormat($levelConfig);
    }

    /**
     * Inject custom fields data into level configuration with safe section merging
     */
    private function injectCustomFieldsToLevelConfig(array $levelConfig): array
    {
        try {
            // Get all custom fields for outlets grouped by section
            $customFieldSections = CustomFieldSection::where('entity_type', 'App\Models\Outlet')
                ->where('active', true)
                ->with(['fields' => function ($query) {
                    $query->where('active', true)->with('options');
                }])
                ->get()
                ->keyBy('code'); // Key by section code for exact matching

            // First pass: Inject custom field data to existing level config sections
            foreach ($levelConfig as $sectionKey => &$section) {
                if (isset($section['fields'])) {
                    // Check if there's a matching custom field section
                    if ($customFieldSections->has($sectionKey)) {
                        $customFieldSection = $customFieldSections->get($sectionKey);
                        $customFieldsMap = $customFieldSection->fields->keyBy('code');

                        // Inject custom field data to matching fields in this section
                        foreach ($section['fields'] as $fieldKey => &$fieldConfig) {
                            if (isset($fieldConfig['custom_field'])) {
                                $customFieldCode = $fieldConfig['custom_field'];

                                if ($customFieldsMap->has($customFieldCode)) {
                                    $customField = $customFieldsMap->get($customFieldCode);

                                    // Merge custom field metadata
                                    $fieldConfig = array_merge($fieldConfig, [
                                        'custom_field_id' => $customField->id,
                                        'custom_field_found' => true,
                                        'type' => $customField->type->value ?? $customField->type, // Handle enum properly
                                        'validation_rules' => $customField->validation_rules,
                                        'settings' => $customField->settings,
                                    ]);

                                    // Add options if available
                                    if ($customField->options && $customField->options->isNotEmpty()) {
                                        $fieldConfig['options'] = $customField->options->map(function ($option) {
                                            return [
                                                'value' => $option->id,
                                                'label' => $option->name,
                                                'sort_order' => $option->sort_order
                                            ];
                                        })->sortBy('sort_order')->values()->toArray();
                                    }
                                } else {
                                    // Mark as not found but keep the field
                                    $fieldConfig['custom_field_found'] = false;
                                    $fieldConfig['custom_field_note'] = "Custom field '{$customFieldCode}' not found in section '{$sectionKey}'";
                                }
                            }
                        }

                        // Add any additional custom fields from this section that weren't in level config
                        foreach ($customFieldSection->fields as $customField) {
                            $fieldExists = false;
                            foreach ($section['fields'] as $levelField) {
                                if (isset($levelField['custom_field']) && $levelField['custom_field'] === $customField->code) {
                                    $fieldExists = true;
                                    break;
                                }
                            }

                            // If custom field doesn't exist in level config, add it
                            if (!$fieldExists) {
                                $section['fields'][$customField->code] = [
                                    'name' => $customField->name,
                                    'type' => $customField->type->value ?? $customField->type,
                                    'required' => false, // Default not required for additional fields
                                    'model_field' => false,
                                    'custom_field' => $customField->code,
                                    'custom_field_id' => $customField->id,
                                    'custom_field_found' => true,
                                    'validation_rules' => $customField->validation_rules,
                                    'settings' => $customField->settings,
                                    'width' => $customField->width ?? 'full',
                                    'options' => $customField->options && $customField->options->isNotEmpty() ?
                                        $customField->options->map(function ($option) {
                                            return [
                                                'value' => $option->id,
                                                'label' => $option->name,
                                                'sort_order' => $option->sort_order
                                            ];
                                        })->sortBy('sort_order')->values()->toArray() : [],
                                    'source' => 'additional_custom_field'
                                ];
                            }
                        }
                    } else {
                        // No matching custom field section, mark custom fields as not found
                        foreach ($section['fields'] as $fieldKey => &$fieldConfig) {
                            if (isset($fieldConfig['custom_field'])) {
                                $fieldConfig['custom_field_found'] = false;
                                $fieldConfig['custom_field_note'] = "Custom field section '{$sectionKey}' not found in database";
                            }
                        }
                    }
                }
            }

            // Second pass: Add entirely new custom field sections that don't exist in level config
            foreach ($customFieldSections as $sectionCode => $customFieldSection) {
                if (!isset($levelConfig[$sectionCode]) && $customFieldSection->fields->isNotEmpty()) {
                    // Add new section with all its custom fields
                    $levelConfig[$sectionCode] = [
                        'section_name' => $customFieldSection->name,
                        'section_code' => $sectionCode,
                        'fields' => []
                    ];

                    foreach ($customFieldSection->fields as $customField) {
                        $levelConfig[$sectionCode]['fields'][$customField->code] = [
                            'name' => $customField->name,
                            'type' => $customField->type->value ?? $customField->type,
                            'required' => false, // Default not required for entirely new sections
                            'model_field' => false,
                            'custom_field' => $customField->code,
                            'custom_field_id' => $customField->id,
                            'custom_field_found' => true,
                            'validation_rules' => $customField->validation_rules,
                            'settings' => $customField->settings,
                            'width' => $customField->width ?? 'full',
                            'options' => $customField->options && $customField->options->isNotEmpty() ?
                                $customField->options->map(function ($option) {
                                    return [
                                        'value' => $option->id,
                                        'label' => $option->name,
                                        'sort_order' => $option->sort_order
                                    ];
                                })->sortBy('sort_order')->values()->toArray() : [],
                            'source' => 'custom_field_section'
                        ];
                    }
                }
            }

            return $levelConfig;
        } catch (\Exception $e) {
            // If there's an error with custom fields injection, return original config
            return $levelConfig;
        }
    }

    /**
     * Transform level configuration to API format (same as customFields format)
     */
    private function transformLevelConfigToApiFormat(array $levelConfig): array
    {
        $sections = [];

        foreach ($levelConfig as $sectionKey => $sectionData) {
            $customFields = [];

            if (isset($sectionData['fields'])) {
                foreach ($sectionData['fields'] as $fieldKey => $fieldConfig) {
                    // Convert validation to array format - always ensure it's an array
                    $validationRules = [];
                    if (isset($fieldConfig['validation_rules'])) {
                        if (is_array($fieldConfig['validation_rules'])) {
                            // Already in array format from custom fields
                            $validationRules = $fieldConfig['validation_rules'];
                        } elseif (is_string($fieldConfig['validation_rules'])) {
                            // Convert string validation to array format
                            $validationRules = $this->convertValidationToArray($fieldConfig['validation_rules']);
                        }
                    } elseif (isset($fieldConfig['validation']) && is_string($fieldConfig['validation'])) {
                        // Convert string validation to array format
                        $validationRules = $this->convertValidationToArray($fieldConfig['validation']);
                    }

                    $customFields[] = [
                        'id' => $fieldConfig['custom_field_id'] ?? null,
                        'code' => $fieldKey, // Use fieldKey as the field code
                        'name' => $fieldConfig['name'],
                        'type' => $fieldConfig['type'],
                        'width' => $fieldConfig['width'] ?? 'full',
                        'lookup_type' => null,
                        'sort_order' => count($customFields) + 1,
                        'validation_rules' => $validationRules, // Always array format
                        'settings' => $fieldConfig['settings'] ?? [],
                        'options' => isset($fieldConfig['options']) ? array_map(function ($option) {
                            return [
                                'id' => $option['value'] ?? $option['id'] ?? null,
                                'name' => $option['label'] ?? $option['name'] ?? '',
                                'sort_order' => $option['sort_order'] ?? 1,
                            ];
                        }, $fieldConfig['options']) : [],
                        // Additional metadata for outlet level fields
                        'required' => $fieldConfig['required'] ?? false,
                        'model_field' => $fieldConfig['model_field'] ?? false,
                        'custom_field_found' => $fieldConfig['custom_field_found'] ?? null,
                        'custom_field_note' => $fieldConfig['custom_field_note'] ?? null,
                        'source' => $fieldConfig['source'] ?? 'level_config',
                    ];
                }
            }

            $sections[] = [
                'id' => null, // Level sections don't have database ID
                'code' => $sectionKey,
                'name' => $sectionData['section_name'] ?? ucfirst(str_replace('_', ' ', $sectionKey)),
                'type' => 'section',
                'description' => null,
                'sort_order' => count($sections) + 1,
                'settings' => [],
                'custom_fields' => $customFields
            ];
        }

        return $sections;
    }

    /**
     * Get outlet level field requirements with custom field injection
     */
    public function outletLevelFields(Request $request)
    {
        try {
            $level = $request->query('level', 'LEAD');
            $includeCustomFields = filter_var($request->query('include_custom_fields', true), FILTER_VALIDATE_BOOLEAN);

            // Validate level
            $validLevels = ['LEAD', 'NOO'];
            if (!in_array(strtoupper($level), $validLevels)) {
                return ResponseFormatter::error(null, 'Invalid level. Valid levels: ' . implode(', ', $validLevels), 400);
            }

            $cacheKey = "ref_outlet_level_fields_" . strtoupper($level) . "_" . ($includeCustomFields ? 'with_cf' : 'no_cf');

            $data = Cache::remember($cacheKey, 1800, function () use ($level, $includeCustomFields) {
                return $this->getOutletLevelFields(strtoupper($level), $includeCustomFields);
            });

            return ResponseFormatter::success($data, "Data field requirements untuk level {$level} berhasil diambil");
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data outlet level fields: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Convert validation string to array format with value property for single parameters
     */
    private function convertValidationToArray(string $validation): array
    {
        if (empty($validation)) {
            return [];
        }

        $rules = [];
        $validationParts = explode('|', $validation);

        foreach ($validationParts as $rule) {
            if (strpos($rule, ':') !== false) {
                // Rule with parameters (e.g., "max:255", "min:1")
                [$name, $params] = explode(':', $rule, 2);
                $parameters = explode(',', $params);

                // If single parameter, use "value" property for easier frontend handling
                if (count($parameters) === 1) {
                    $rules[] = [
                        'name' => $name,
                        'value' => $parameters[0]
                    ];
                } else {
                    // Multiple parameters, use "parameters" array
                    $rules[] = [
                        'name' => $name,
                        'parameters' => $parameters
                    ];
                }
            } else {
                // Simple rule (e.g., "required", "nullable")
                $rules[] = [
                    'name' => $rule
                ];
            }
        }

        return $rules;
    }
}
