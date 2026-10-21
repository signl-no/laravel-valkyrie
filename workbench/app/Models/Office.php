<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Signl\Valkyrie\Concerns\IsAccessScope;

#[Fillable(['name'])]
class Office extends Model
{
    use IsAccessScope;
}
