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

        $model = (clone $query)->whereHas('translations', function (Builder $query) use ($slug, $locale) {
            $query->where($this->getTranslationsTable() . '.slug', $slug);
        })->firstOrFail();

        if ($model->hasTranslation($locale)) {
            $query->whereHas('translations', function (Builder $query) use ($slug, $locale) {
                $query->where($this->getTranslationsTable() . '.slug', $slug)
                      ->where($this->getTranslationsTable() . '.' . $this->getLocaleKey(), $locale);
            });
        } else {
            $query->whereHas('translations', function (Builder $query) use ($slug, $locale) {
                $query->where($this->getTranslationsTable() . '.slug', $slug);
            });
        }

        return $query;
    }
}