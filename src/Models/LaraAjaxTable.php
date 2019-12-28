<?php

namespace Abd\LaraAjaxTable\Models;

use Illuminate\Http\Request;

trait LaraAjaxTable
{
    private $searchColumn = [];

    public function DataTableLoader(Request $request)
    {

        $this->searchColumnFilter($request);
        $limit = 20;
        $offset = 0;
        $search = [];
        $where = [];
        $with = [];
        $join = [];
        $order_by = [];

        if ($request->input('length')) {
            $limit = $request->input('length');
        }

        if ($request->input('start')) {
            $offset = $request->input('start');
        }
        if ($request->input('search') && $request->input('search')['value'] != "") {
            foreach ($this->searchColumn as $name) {
                $search[$name] = $request->input('search')['value'];
            }
        }


        if ($request->input('where')) {
            $where = $request->input('where');
        }

        if ($request->input('order')[0]['column'] != 0) {
            $column_name = $request->input('columns')[$request->input('order')[0]['column']]['name'];
            $sort = $request->input('order')[0]['dir'];
            $order_by[$column_name] = $sort;
        }

        $with = [];


        $join = [];

        return $this->GetDataForDataTable($limit, $offset, $search, $where, $with, $join, $order_by);
    }

    protected function searchColumnFilter(Request $request)
    {
        $requestColumns = $request->input('columns');

        foreach ($requestColumns as $searchColumn) {
            if ($searchColumn['searchable'] == "false" || $searchColumn['name'] == NULL) continue;
            $this->searchColumn [] = $searchColumn['name'];
        }
    }


    public function GetDataForDataTable($limit = 20, $offset = 0, $search = [], $where = [], $with = [], $join = [], $order_by = [], $withTrashed = 0, $table_col_name = '')
    {

        $totalData = $this::query();
        $filterData = $this::query();
        $totalCount = $this::query();

        if (count($where) > 0) {
            foreach ($where as $keyW => $valueW) {
                if (strpos($keyW, ' IN') !== false) {
                    $keyW = str_replace(' IN', '', $keyW);
                    $totalData->whereIn($keyW, $valueW);
                    $filterData->whereIn($keyW, $valueW);
                    $totalCount->whereIn($keyW, $valueW);
                } else if (strpos($keyW, ' NOTIN') !== false) {
                    $keyW = str_replace(' NOTIN', '', $keyW);
                    $totalData->whereNotIn($keyW, $valueW);
                    $filterData->whereNotIn($keyW, $valueW);
                    $totalCount->whereNotIn($keyW, $valueW);
                } else if (is_array($valueW)) {
                    $totalData->where([$valueW]);
                    $filterData->where([$valueW]);
                    $totalCount->where([$valueW]);
                } else if (strpos($keyW, ' and') === false) {
                    $totalData->orWhere($keyW, $valueW);
                    $filterData->orWhere($keyW, $valueW);
                    $totalCount->orWhere($keyW, $valueW);
                } else {
                    $keyW = str_replace(' and', '', $keyW);
                    $totalData->where($keyW, $valueW);
                    $filterData->where($keyW, $valueW);
                    $totalCount->where($keyW, $valueW);
                }
            }
        }

        if ($limit > 0) {
            $totalData->limit($limit)->offset($offset);
        }

        if (count($with) > 0) {
            foreach ($with as $rel) {
                $totalData->with($rel);
                $filterData->with($rel);
            }
        }

        if (count($join) > 0) {
            foreach ($join as list($nameJ, $withJ, $asJ)) {
                $name_array = explode(" ", $nameJ);
                $name_as = end($name_array);
                if ($name_as == 'rev') {
                    $totalData->leftJoin($name_array[0], $withJ, '=', $this->getTable() . '.id')
                        ->selectRaw($asJ);
                    $filterData->leftJoin($name_array[0], $withJ, '=', $this->getTable() . '.id');
                    $totalCount->leftJoin($name_array[0], $withJ, '=', $this->getTable() . '.id');
                } else {
                    $totalData->leftJoin($nameJ, $withJ, '=', $name_as . '.id')
                        ->selectRaw($asJ);
                    $filterData->leftJoin($nameJ, $withJ, '=', $name_as . '.id');
                    $totalCount->leftJoin($nameJ, $withJ, '=', $name_as . '.id');
                }
            }

            $totalData->selectRaw($this->getTable() . '.*');
            $filterData->selectRaw($this->getTable() . '.*');
        }

        if (count($search) > 0) {
            $totalData->where(function ($totalData) use ($search) {
                foreach ($search as $keyS => $valueS) {
                    if (strpos($keyS, ' and') === false) {
                        $totalData->orWhere($keyS, 'like', "%$valueS%");
                    } else {
                        $keyS = str_replace(' and', '', $keyS);
                        $totalData->where($keyS, $valueS);
                    }
                }
            });

            $filterData->where(function ($filterData) use ($search) {
                foreach ($search as $keyS => $valueS) {
                    $filterData->orWhere($keyS, 'like', "%$valueS%");
                }
            });
        }

        if (count($order_by) > 0) {
            foreach ($order_by as $col => $by) {
                $totalData->orderBy($col, $by);
            }
        } else {
            $totalData->orderBy($this->getTable() . '.id', 'DESC');
        }


        if ($withTrashed) {
            $totalData->withTrashed();
            $filterData->withTrashed();
            $totalCount->withTrashed();
        }

        return [
            'data' => $totalData->get(),
            'draw' => 0,
            'recordsTotal' => $totalCount->count(),
            'recordsFiltered' => $filterData->count(),
        ];
    }
}

?>