<?php

namespace App\Repositories\Category;

use App\Repositories\BaseRepository;
use App\Contracts\Category\CategoryRepositoryInterface;
use App\Models\Category;

class EloquentCategoryRepository extends BaseRepository
    implements CategoryRepositoryInterface
{
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }

    // 🚫 no repites find, findOrFail, etc.
    // aquí solo va lógica específica de Category si algún día la necesitas
}
