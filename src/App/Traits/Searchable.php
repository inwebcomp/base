<?php

namespace InWeb\Base\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    public function searchableColumns()
    {
        return [];
    }

    public function fulltextSearchColumn()
    {
        return 'title';
    }

    /**
     * Apply the search query to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @param string $type
     * @param bool $order
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws \Exception
     */
    public function scopeSearch(Builder $query, $search, $type = 'fulltext', $order = true)
    {
        if (preg_match('/^\d+$/', $search) && in_array($query->getModel()->getKeyType(), ['int', 'integer'])) {
            $query->where($query->getModel()->getQualifiedKeyName(), $search);
        } else {
//            if ($type == 'fulltext' and (strpos($search, '-') !== false or strpos($search, '.') !== false)) {
//                $type = 'like';
//            }

            if ($type == 'fulltext') {
                $this->applySearchFulltext($query, $search, $order);
            } else if ($type == 'like') {
                $this->applySearchLike($query, $search, $order);
            } else {
                throw new \Exception('Order type [' . $type . '] not found');
            }
        }

        return $query;
    }

    public function applySearchFulltext(Builder $query, $search, $order = true)
    {
        $search = str_replace(['@', '+', '.', '-'], ['', ' ', ' ', ' '], $search);
        $search = preg_replace('/[^\w\s\.\-]+/iu', '', $search);

        $s = preg_replace('/\s+/iu', ' ', trim($search));
        $searchQ = "";

        $tmp = explode(' ', $s);
        foreach ($tmp as $k => $word) {
            if ($k <= 2 and strpos($word, '-') === false)
                $searchQ .= "+" . $word . "*";
            else
                $searchQ .= ">" . trim($word, '-') . "*";
        }

        $translationTableName = $this->getTranslationsTable();

        $values = \DB::select(
            'SELECT pt.' . $this->getForeignKey() . ',
                MATCH(pt.' . $this->fulltextSearchColumn() . ') AGAINST (? IN BOOLEAN MODE) as relevance
                FROM `' . $translationTableName . '` pt WHERE MATCH(pt.title) AGAINST (? IN BOOLEAN MODE) ORDER BY relevance DESC',
            [$searchQ, $searchQ]
        );

        $ids = [];

        foreach ($values as $value)
            $ids[] = $value->product_id;

        $ids = array_unique($ids);

        if (! count($ids))
            $ids = [-1];

        $query->whereIn($this->getModel()->getKeyName(), $ids);

        if ($order)
            $query->orderByRaw("FIELD(" . $this->getTable() . ".id, " . implode(',', $ids) . ") ASC");
    }

    public function applySearchLike(Builder $query, $search, $order = true)
    {
        $newQuery = clone $query;

//        $search = preg_replace('/[^\w\s\.\-%]+/iu', '', $search);
        $search = addcslashes($search, "%'");

        $translationTable = $this->getTranslationsTable();

        $newQuery
            ->select($this->getTable() . '.*')
            ->leftJoin($translationTable, $translationTable . '.' . $this->getRelationKey(), '=', $this->getTable() . '.' . $this->getKeyName());

        $columns = $this->searchableColumns();

        $words = [$search];

        $newQuery->where(function ($q) use ($words, $columns, $search, $translationTable) {
            foreach ($words as $k => $word) {
                if ($k > 10)
                    break;

                foreach ($columns as $column) {
                    if (! $this->isTranslationAttribute($column))
                        $q->orWhere($column, 'like', '%' . $word . '%');
                    else {
                        $q->orWhere($translationTable . '.' . $column, 'like', '%' . $word . '%');
                    }
                }
            }
        });

        $order = '(';

        $count = count($columns);

        foreach ($words as $k => $word) {
            if ($k > 10)
                break;

            foreach ($columns as $i => $column) {
                if ($order != '(')
                    $order .= ' + ';

                $table = '';

                if ($this->isTranslationAttribute($column))
                    $table = $translationTable . '.';

                $percent = '(' . strlen($word) . ' / LENGTH(' . $table . $column . '))';
                $order .= "(CASE WHEN " . $table . $column . " LIKE '%" . $word . '%' . "' THEN " . (($count - $i) * 2) . ' * ' . $percent . " ELSE 0 END)\n";
                $order .= " + (CASE WHEN " . $table . $column . " = '" . $word . "' THEN " . (($count - $i) * 3) . " ELSE 0 END)\n";
            }
        }
        $order .= ' )';

        $newQuery->orderByRaw($order . ' DESC');

        $ids = $newQuery->pluck($this->getTable() . '.' . $this->getKeyName())->toArray();

        $ids = array_unique($ids);

        if (! count($ids))
            $ids = [-1];

        $query->whereIn($this->getModel()->getKeyName(), $ids);

        if ($order)
            $query->orderByRaw("FIELD(" . $this->getTable() . ".id, " . implode(',', $ids) . ") ASC");
    }
}