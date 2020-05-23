<?php

namespace InWeb\Base\Traits;

use Illuminate\Database\Eloquent\Builder;

trait TranslatableSlug
{
    /**
     * @param Builder $query
     * @param $slug
     * @return Builder
     */
    public function scopeFindBySlug(Builder $query, $slug)
    {
        $locale = \App::getLocale();

        $translationsTable = $this->getTranslationsTable();

        $model = (clone $query)->where($this->getKeyName(), '=', function (\Illuminate\Database\Query\Builder $query) use ($slug, $locale, $translationsTable) {
            $query->select($this->getForeignKey())->from($translationsTable)->where('slug', $slug)->take(1);
        })->first();

        if (! $model)
            return $query->whereKey('-1');

        if ($model->hasTranslation($locale)) {
            $query->where($this->getKeyName(), '=', function (\Illuminate\Database\Query\Builder $query) use ($slug, $locale, $translationsTable) {
                $query->select($this->getForeignKey())
                      ->from($translationsTable)
                      ->where('slug', $slug)
                      ->where($translationsTable . '.' . $this->getLocaleKey(), $locale)
                      ->take(1);
            });
        } else {
            $query->where($this->getKeyName(), '=', $model->getKey());
        }

        return $query;
    }
}