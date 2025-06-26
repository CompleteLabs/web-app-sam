<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TableView extends Model
{
    use HasFactory;

    /**
     * Fillable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'icon',
        'color',
        'is_public',
        'filters',
        'filterable_type',
        'user_id',
    ];

    /**
     * Table name.
     *
     * @var string
     */
    protected $casts = [
        'filters' => 'array',
    ];

    /**
     * Get the user that owns the saved filter.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
