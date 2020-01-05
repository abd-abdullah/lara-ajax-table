<?php

namespace Abd\LaraAjaxTable\Models;

use Illuminate\Http\Request;

trait LaraAjaxTable
{
    protected $searchColumn = [];
    protected $Offset = 0;
    protected $Limit = 10;
    public $Join = [];
    public $Where = [];
    public $Order = [];
    public $Search = [];

    public function DataTableLoader(Request $request)
    {

        $this->searchColumnFilter($request);

        if ($request->input('length')) {
            $this->Limit = $request->input('length');
        }

        if ($request->input('start')) {
            $this->Offset = $request->input('start');
        }

        if ($request->input('search') && $request->input('search')['value'] != "") {
            foreach ($this->searchColumn as $name) {
                $this->Search[$name] = $request->input('search')['value'];
            }
        }

        if ($request->input('order')[0]['column'] != 0) {
            $column_name = $request->input('columns')[$request->input('order')[0]['column']]['name'];
            $sort = $request->input('order')[0]['dir'];
            $this->Order[$column_name] = $sort;
        }

        return $this->GetDataForDataTable();
    }

    protected function searchColumnFilter(Request $request)
    {
        $requestColumns = $request->input('columns');

        foreach ($requestColumns as $searchColumn) {
            if ($searchColumn['searchable'] == "false" || $searchColumn['name'] == NULL) continue;
            $this->searchColumn [] = $searchColumn['name'];
        }
    }


    public function GetDataForDataTable()
    {
        $where = $this->Where;
        $offset = $this->Offset;
        $limit = $this->Limit;
        $join = $this->Join;
        $search = $this->Search;
        $order_by = $this->Order;


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

        if (count($join) > 0) {
            foreach ($join as $val) {
                $name_array = explode(" ", $val['table1']);
                $name_as = end($name_array);
                $totalData->leftJoin($val['table1'], $name_as . "." . $val['column'], '=', $val['table2'] . '.id');
                $filterData->leftJoin($val['table1'], $name_as . "." . $val['column'], '=', $val['table2'] . '.id');
                $totalCount->leftJoin($val['table1'], $name_as . "." . $val['column'], '=', $val['table2'] . '.id');
            }
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

        $totalData = $totalData->get();
        $totalData->transform(function ($item) {
            $item['Row_Index'] = ++$this->offset;
            return $item;
        });


        return [
            'data' => $totalData,
            'draw' => 0,
            'recordsTotal' => $totalCount->count(),
            'recordsFiltered' => $filterData->count(),
        ];
    }
}

?>