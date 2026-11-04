<?php

namespace App;

use Illuminate\Routing\ResourceRegistrar as OriginalRegistrar;

class ExtendedRoutes extends OriginalRegistrar
{
	// add data to the array
	/**
	* The default actions for a resourceful controller.
	*
	* @var array
	*/
	protected $resourceDefaults = [ 'index', 'create', 'store', 'show', 'edit', 'update', 'destroy', 'indexAHR' ];


	/**
	* Add the data method for a resourceful route.
	*
	* @param  string  $name
	* @param  string  $base
	* @param  string  $controller
	* @param  array   $options
	* @return \Illuminate\Routing\Route
	*/
	protected function addResourceIndexAHR($name, $base, $controller, $options)
	{
		$uri = $this->getResourceUri($name) . '/index-ahr';

		$action = $this->getResourceAction($name, $controller, 'indexAHR', $options);
		$action['as'] = str_replace('.indexAHR', '.index-ahr', $action['as']);

		return $this->router->post($uri, $action);
	}
}
