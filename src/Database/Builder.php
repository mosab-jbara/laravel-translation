<?php

namespace Mosab\Translation\Database;

use Illuminate\Database\Eloquent\Builder as BaseBuilder;

class Builder extends BaseBuilder {
    public function newModelInstance($attributes = [])
    {
        $instance = $this->model->newInstance($attributes)->setConnection(
            $this->query->getConnection()->getName()
        );
        $translatable = $instance->getTranslatable();
        $translatable_attributes = [];
        foreach($attributes as $key => $value)
            if(in_array($key,$translatable))
                $translatable_attributes[$key] = $value;
        foreach($translatable_attributes as $key => $value)
            $instance->$key = $value;
        return $instance;
    }
}
