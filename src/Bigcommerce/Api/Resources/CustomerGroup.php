<?php

namespace Bigcommerce\Api\Resources;

use Bigcommerce\Api\Resource;
use Bigcommerce\Api\Client;

class CustomerGroup extends Resource
{

	protected $ignoreOnCreate = array(
		'id',
	);

	protected $ignoreOnUpdate = array(
		'id',
	);

	public function create()
	{
		return Client::createCustomerGroup($this->getCreateFields());
	}

	public function update()
	{
		return Client::updateCustomerGroup($this->id, $this->getUpdateFields());
	}

	public function delete()
	{
		return Client::deleteCustomerGroup($this->id);
	}

}