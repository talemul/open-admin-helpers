<?php

namespace OpenAdmin\Admin\Helpers\Model;

use Illuminate\Database\Eloquent\Model;

class ScaffoldDetail extends Model
{
    protected $fillable = [
        'scaffold_id', 'name', 'type', 'nullable', 'key', 'default', 'comment', 'order'
    ];
}
