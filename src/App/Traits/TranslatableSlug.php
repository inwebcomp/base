<?php

namespace InWeb\Base\Traits;

use Illuminate\Database\Eloquent\Builder;

trait TranslatableSlug
{
    /**
     * @param Builder $query
     * @param         $slug
     * @return Builder
     */
    public function scopeFindBySlug(Builder $query, $slug)
    {
        $locale = \App::getLocale();

        $translationsTable = app()->make($this->getTranslationModelName())->getTable();

        $model = (clone $query)->leftJoin($translationsTable, $this->getTable() . '.' . $this->getKeyName(), '=', $translationsTable . '.' . $this->getForeignKey())->where($this->getTranslationsTable() . '.slug', $slug)->first();

        if (! $model)
            return $query->whereKey('-1');

        if ($model->hasTranslation($locale)) {
            $query->leftJoin($translationsTable, $this->getTable() . '.' . $this->getKeyName(), '=', $translationsTable . '.' . $this->getForeignKey())
                ->where($this->getTranslationsTable() . '.slug', $slug)
                ->where($this->getTranslationsTable() . '.' . $this->getLocaleKey(), $locale);
        } else {
            $query->leftJoin($translationsTable, $this->getTable() . '.' . $this->getKeyName(), '=', $translationsTable . '.' . $this->getForeignKey())->where($this->getTranslationsTable() . '.slug', $slug)->first();
        }

        return $query;
    }
}