<?php

namespace InWeb\Base\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    public function searchableColumns()
    {
        return [];
    }

    /**
     * Apply the search query to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch(Builder $query, $search, $type = 'fulltext')
    {
        if (is_numeric($search) && in_array($query->getModel()->getKeyType(), ['int', 'integer'])) {
            $query->where($query->getModel()->getQualifiedKeyName(), $search);
        } else {
            if ($type == 'fulltext') {
                $this->applySearchFulltext($query, $search);
            } else if ($type == 'like') {
                $this->applySearchLike($query, $search);
            }
        }

        return $query;
    }

    public function applySearchFulltext(Builder $query, $search)
    {
        $search = str_replace(['@', '-', '+'], ['', ' ', ' '], $search);
        $search = preg_replace('/[^\w\s\.]+/iu', '', $search);
        $s = preg_replace('/\s+/iu', ' ', trim($search));
        $searchQ = "";

        $tmp = explode(' ', $s);
        foreach ($tmp as $k => $word) {
            if ($k <= 2)
                $searchQ .= "+" . $word . "*";
            else
                $searchQ .= " " . $word . "*";
        }

        $translationTableName = $this->getTranslationsTable();

        $values = \DB::select(
            'SELECT pt.' . $this->getForeignKey() . '
                FROM `' . $translationTableName . '` pt WHERE MATCH(pt.title) AGAINST (? IN BOOLEAN MODE)',
            [$searchQ, $s, $searchQ]
        );

        $ids = [];

        foreach ($values as $value)
            $ids[] = $value->product_id;

        $ids = array_unique($ids);

        if (! count($ids))
            $ids = [-1];

        $query->whereIn($this->getModel()->getKeyName(), $ids);
        $query->orderByRaw("FIELD(" . $this->getTable() . ".id, " . implode(',', $ids) . ") ASC");
    }

    public function applySearchLike(Builder $query, $search)
    {
        $translationTable = $this->getTranslationsTable();
        $localeKey = $this->getLocaleKey();

        $query
            ->select($this->getTable() . '.*')
            ->leftJoin($translationTable, $translationTable . '.' . $this->getRelationKey(), '=', $this->getTable() . '.' . $this->getKeyName())
            ->where($translationTable . '.' . $localeKey, $this->locale());

        $columns = $this->searchableColumns();

        $query->where(function ($q) use ($columns, $search, $translationTable) {
            foreach ($columns as $column) {
                if (! $this->isTranslationAttribute($column))
                    $q->orWhere($column, 'like', $search . '%');
                else {
                    $q->orWhere($translationTable . '.' . $column, 'like', $search . '%');
                }
            }
        });

        $order = '(';

        $count = count($columns);
        foreach ($columns as $i => $column) {
            if ($order != '(')
                $order .= ' + ';

            $percent = '(' . strlen($search) . ' / LENGTH(' . $translationTable . '.' . $column . '))';
            $order .= "(CASE WHEN " . $translationTable . '.' . $column . " LIKE '" . $search . '%' . "' THEN " . (($count - $i) * 2) . ' * ' . $percent . ' ELSE 0 END)';
            $order .= " + (CASE WHEN " . $translationTable . '.' . $column . " = '" . $search . "' THEN " . (($count - $i) * 3) . ' ELSE 0 END)';
        }
        $order .= ' )';

        $query->orderByRaw($order . ' DESC');
    }
}