<?php

namespace App\Repositories\Abstracts;

use App\Repositories\Interfaces\CRMActivitiesRepositoryInterface;
use App\Repositories\Interfaces\ModelBaseInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

abstract class EloquentRepository implements ModelBaseInterface
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $_model;

    /**
     * EloquentRepository constructor.
     */
    public function __construct()
    {
        $this->setModel();
    }

    /**
     * @return mixed
     */
    abstract protected function getModel();

    /**
     * Set model
     *
     */
    private function setModel(): void
    {
        $this->_model = app()->make($this->getModel());
    }

    /**
     * Get All
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($columns = ['*'])
    {

        return $this->_model::all($columns);
    }

    /**
     * Get one
     *
     * @param $id
     *
     * @return mixed
     */
    public function find($id)
    {
        return $this->_model::find($id) ?? false;
    }

    /**
     * Create
     *
     * @param array $attributes
     *
     * @return mixed
     */
    public function create(array $attributes)
    {
        if ($this->_model->isFillable('CreatedBy') && $this->_model->isFillable('CreatedDate')) {
            $staffId = Auth::user()['StaffId'] ?? 0;
            $attributes['CreatedBy'] = $staffId;
            $attributes['CreatedDate'] = date('Y-m-d H:i:s');
        }
        return $this->_model->create($attributes);
    }

    /**
     * Update
     *
     * @param       $id
     * @param array $attributes
     *
     * @return bool|mixed
     */
    public function update($id, array $attributes)
    {
        $item = $this->_model->find($id);
        if ($item) {
            if ($this->_model->isFillable('UpdatedBy') && $this->_model->isFillable('UpdatedDate')) {
                $staffId = Auth::user()['StaffId'] ?? 0;
                $attributes['UpdatedBy'] = $staffId;
                $attributes['UpdatedDate'] = date('Y-m-d H:i:s');
            }
            return $item->update($attributes);
        }
        return false;
    }

    /**
     * Delete
     *
     * @param $id
     *
     * @return bool
     */
    public function delete($id)
    {
        $result = $this->find($id);
        if ($result) {
            $result->delete();
            return true;
        }
        return false;
    }

    /**
     * Custom Soft Delete
     *
     * @param $id
     * @return bool
     */
    public function softDelete($id)
    {
        if ($item = $this->find($id)) {
            if ($this->_model->isFillable('UpdatedBy') && $this->_model->isFillable('IsDeleted')) {
                $staffId = Auth::user()['StaffId'] ?? 0;
                $attributes['UpdatedBy'] = $staffId;
                $attributes['IsDeleted'] = 1;
            }
            return $item->update($attributes);
        }
        return false;
    }

    /**
     * Insert Or Ignore Duplicate Errors
     *
     * @param array $attributes
     *
     * @return mixed
     */
    public function insertOrIgnore(array $attributes)
    {
        if ($this->_model->isFillable('CreatedBy') && $this->_model->isFillable('LastUpdatedBy')) {
            $userId = Auth::id();
            $attributes['CreatedBy'] = $userId;
            $attributes['LastUpdatedBy'] = $userId;
        }
        return $this->_model->insertOrIgnore($attributes);
    }

	/**
     * Show blinded query by DB::getQueryLog()
     * Just for testing
     * @author: Tuan Dao
     */
    public function getBindedQueries()
    {

        $queries = DB::getQueryLog();

        $result = [];
        foreach ($queries as $queryItem) {
            $query = $queryItem['query'];
            $bindings = $queryItem['bindings'];
            $arr = explode('?', $query);
            $res = '';
            foreach ($arr as $idx => $ele) {
                if ($idx < count($arr) - 1) {
                    $res = $res . $ele . "'" . $bindings[$idx] . "'";
                }
            }
            $res = $res . $arr[count($arr) - 1];
            $result[] = $res;
        }
        return $result;
    }
}