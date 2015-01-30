<?php

namespace Bigcommerce\Api\Resources;

use Bigcommerce\Api\Resource;
use Bigcommerce\Api\Client;

class Webhook extends Resource
{

	protected $ignoreOnCreate = array(
		'id',
	);

	protected $ignoreOnUpdate = array(
		'id',
	);

	public function create()
	{
		return Client::createWebhook($this->getCreateFields());
	}
	
	public function update()
	{
		return Client::updateWebhook($this->id, $this->getUpdateFields());
	}
	
	public function delete()
	{
		return Client::deleteWebhook($this->id);
	}
}