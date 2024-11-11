<?php

namespace App\Repositories\FormField;

use App\Models\FormField;
use App\Repositories\Saas\SaaSRepository;

class FormFieldsRepository extends SaaSRepository implements FormFieldsInterface {
    public function __construct(FormField $model) {
        parent::__construct($model);
    }
}
