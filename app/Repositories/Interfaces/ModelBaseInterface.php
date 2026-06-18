<?php

namespace App\Repositories\Interfaces;

interface ModelBaseInterface
{
	/**
	 * Get all
	 * @return mixed
	 */
	public function all();

	/**
	 * Get one
	 * @param $id
	 * @return mixed
	 */
	public function find($id);

	/**
	 * Create
	 * @param array $attributes
	 * @return mixed
	 */
	public function create(array $attributes);

	/**
	 * Update
	 * @param $id
	 * @param array $attributes
	 * @return mixed
	 */
	public function update($id, array $attributes);

	/**
	 * Delete
	 * @param $id
	 * @return mixed
	 */
	public function delete($id);
}