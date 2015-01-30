<?php

namespace Bigcommerce\Api\Resources;

use Bigcommerce\Api\Resource;
use Bigcommerce\Api\Client;

class BlogPost extends Resource
{

	protected $ignoreOnCreate = array(
		'id', 'preview_url'
	);

	protected $ignoreOnUpdate = array(
		'id', 'preview_url'
	);

	public function create()
	{
		return Client::createBlogPost($this->getCreateFields());
	}
	
	public function update()
	{
		return Client::updateBlogPost($this->id, $this->getUpdateFields());
	}
	
	public function delete()
	{
		return Client::deleteBlogPost($this->id);
	}
}