<?php

namespace App\Traits;

trait ActionButtons
{

	/**
	 * @param  string $route
	 * @return string
	 */
	public function getEditButton($route)
	{
		return '<a href="'.$route.'" class="btn btn-edit btn-sm mr-2 btn-primary-small"> <i class="far fa-edit mr-2"></i> Edit</a>';
	}

	/**
	 * @param string $route
	 * @return string
	 */
	public function getDeleteButton($route)
	{
		return '<button data-url="' . $route. '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small"> <i class="far fa-trash-alt mr-2"></i> Delete</button>';
	}

	/**
	 * @param string $title
	 * @param string $route
	 * @param string $class
	 * @param string $iconClass
	 * @return string
	 */
	public function getCustomButton($title,$route,$class,$iconClass)
	{
		return '<a href="' . $route. '" class="'.$class.'"><i class="'.$iconClass.' mr-1"> </i> '.$title.'</button>';
	}
}
