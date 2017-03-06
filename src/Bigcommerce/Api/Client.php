<?php

namespace Bigcommerce\Api;

use Firebase\JWT\JWT;
use \Exception as Exception;

/**
 * Bigcommerce API Client.
 */
class Client
{
	static private $store_url;
	static private $username;
	static private $api_key;
	static private $connection;
	static private $resource;
	static private $path_prefix = '/api/v2';
	static private $client_id;
	static private $client_secret;
	static private $auth_token;
	static private $store_hash;
	static private $stores_prefix = '/stores/%s/v2';
	static private $api_url = 'https://api.bigcommerce.com';
	static private $login_url = 'https://login.bigcommerce.com';

	static private $cipher;
	static private $verifyPeer;

	/**
	 * Full URL path to the configured store API.
	 *
	 * @var string
	 */
	static public $api_path;

	/**
	 * Configure the API client with the required credentials.
	 *
	 * Requires a settings array to be passed in with the following keys:
	 *
	 * - store_url
	 * - username
	 * - api_key
	 *
	 * @param array $settings
	 * @throws Exception
	 */
	public static function configureBasicAuth($settings)
	{
		if (!isset($settings['store_url'])) {
			throw new Exception("'store_url' must be provided");
		}

		if (!isset($settings['username'])) {
			throw new Exception("'username' must be provided");
		}

		if (!isset($settings['api_key'])) {
			throw new Exception("'api_key' must be provided");
		}

		self::$client_id = null;
		self::$auth_token = null;
		self::$username  = $settings['username'];
		self::$api_key 	 = $settings['api_key'];
		self::$store_url = rtrim($settings['store_url'], '/');
		self::$api_path  = self::$store_url . self::$path_prefix;
		self::$connection = false;
	}

	/**
	 * Configure the API client with the required OAuth credentials.
	 *
	 * Requires a settings array to be passed in with the following keys:
	 *
	 * - client_id
	 * - auth_token
	 * - store_hash
	 *
	 * @param array $settings
	 * @throws Exception
	 */
	public static function configureOAuth($settings)
	{
		if (!isset($settings['auth_token'])) {
			throw new Exception("'auth_token' must be provided");
		}

		if (!isset($settings['store_hash'])) {
			throw new Exception("'store_hash' must be provided");
		}

		self::$username  = null;
		self::$api_key 	 = null;
		self::$client_id = $settings['client_id'];
		self::$auth_token = $settings['auth_token'];
		self::$store_hash = $settings['store_hash'];

		self::$client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : null;

		self::$api_path = self::$api_url . sprintf(self::$stores_prefix, self::$store_hash);
		self::$connection = false;
	}

	/**
	 * Configure the API client with the required settings to access
	 * the API for a store.
	 *
	 * Accepts both OAuth and Basic Auth credentials
	 *
	 * @param array $settings
	 */
	public static function configure($settings)
	{
		if (isset($settings['client_id'])) {
			self::configureOAuth($settings);
		} else {
			self::configureBasicAuth($settings);
		}
	}

	/**
	 * Configure the API client to throw exceptions when HTTP errors occur.
	 *
	 * Note that network faults will always cause an exception to be thrown.
	 *
	 * @param bool $option
	 */
	public static function failOnError($option=true)
	{
		self::connection()->failOnError($option);
	}

	/**
	 * Return XML strings from the API instead of building objects.
	 */
	public static function useXml()
	{
		self::connection()->useXml();
	}

	/**
	 * Return JSON objects from the API instead of XML Strings.
	 * This is the default behavior.
	 */
	public static function useJson()
	{
		self::connection()->useXml(false);
	}

	/**
	 * Switch SSL certificate verification on requests.
	 *
	 * @param bool $option
	 */
	public static function verifyPeer($option=false)
	{
		self::$verifyPeer = $option;
		self::connection()->verifyPeer($option);
	}

	/**
	 * Set which cipher to use during SSL requests.
	 *
	 * @param string $cipher
	 */
	public static function setCipher($cipher='TLSv1')
	{
		self::$cipher = $cipher;
		self::connection()->setCipher($cipher);
	}

	/**
	 * Connect to the internet through a proxy server.
	 *
	 * @param string $host host server
	 * @param string|bool $port port
	 */
	public static function useProxy($host, $port=false)
	{
		self::connection()->useProxy($host, $port);
	}

	/**
	 * Get error message returned from the last API request if
	 * failOnError is false (default).
	 *
	 * @return string
	 */
	public static function getLastError()
	{
		return self::connection()->getLastError();
	}

	/**
	 * Get an instance of the HTTP connection object. Initializes
	 * the connection if it is not already active.
	 *
	 * @return Connection
	 */
	private static function connection()
	{
		if (!self::$connection) {
			self::$connection = new Connection();

			if (self::$client_id) {
				self::$connection->authenticateOauth(self::$client_id, self::$auth_token);
			} else {
				self::$connection->authenticate(self::$username, self::$api_key);
			}
		}

		return self::$connection;
	}

	/**
	 * Convenience method to return instance of the connection
	 *
	 * @return Connection
	 */
	public static function getConnection()
	{
		return self::connection();
	}
	/**
	 * Set the HTTP connection object. DANGER: This can screw up your Client!
	 *
	 * @param Connection $connection The connection to use
	 */
	public static function setConnection(Connection $connection = null)
	{
		self::$connection = $connection;
	}

	/**
	 * Get a collection result from the specified endpoint.
	 *
	 * @param string $path api endpoint
	 * @param string $resource resource class to map individual items
	 * @return mixed array|string mapped collection or XML string if useXml is true
	 */
	public static function getCollection($path, $resource='Resource')
	{
		$response = self::connection()->get(self::$api_path . $path);

		return self::mapCollection($resource, $response);
	}

	/**
	 * Get a resource entity from the specified endpoint.
	 *
	 * @param string $path api endpoint
	 * @param string $resource resource class to map individual items
	 * @return mixed Resource|string resource object or XML string if useXml is true
	 */
	public static function getResource($path, $resource='Resource')
	{
		$response = self::connection()->get(self::$api_path . $path);

		return self::mapResource($resource, $response);
	}

	/**
	 * Get a count value from the specified endpoint.
	 *
	 * @param string $path api endpoint
	 * @return mixed int|string count value or XML string if useXml is true
	 */
	public static function getCount($path)
	{
		$response = self::connection()->get(self::$api_path . $path);

		if ($response == false || is_string($response)) return $response;

		return $response->count;
	}

	/**
	 * Send a post request to create a resource on the specified collection.
	 *
	 * @param string $path api endpoint
	 * @param mixed $object object or XML string to create
	 * @return mixed
	 */
	public static function createResource($path, $object)
	{
		if (is_array($object)) $object = (object)$object;

		return self::connection()->post(self::$api_path . $path, $object);
	}

	/**
	 * Send a put request to update the specified resource.
	 *
	 * @param string $path api endpoint
	 * @param mixed $object object or XML string to update
	 * @return mixed
	 */
	public static function updateResource($path, $object)
	{
		if (is_array($object)) $object = (object)$object;

		return self::connection()->put(self::$api_path . $path, $object);
	}

	/**
	 * Send a delete request to remove the specified resource.
	 *
	 * @param string $path api endpoint
	 * @return mixed
	 */
	public static function deleteResource($path)
	{
		return self::connection()->delete(self::$api_path . $path);
	}

	/**
	 * Internal method to wrap items in a collection to resource classes.
	 *
	 * @param string $resource name of the resource class
	 * @param array $object object collection
	 * @return array
	 */
	private static function mapCollection($resource, $object)
	{
		if ($object == false || is_string($object)) return $object;

		if (!is_array($object)) {
			$object = array( $object );
		}

		$baseResource = __NAMESPACE__ . '\\' . $resource;
		self::$resource = (class_exists($baseResource)) ?  $baseResource  :  'Bigcommerce\\Api\\Resources\\' . $resource;

		return array_map(array('self', 'mapCollectionObject'), $object);
	}

	/**
	 * Callback for mapping collection objects resource classes.
	 *
	 * @param \stdClass $object
	 * @return Resource
	 */
	private static function mapCollectionObject($object)
	{
		$class = self::$resource;

		return new $class($object);
	}

	/**
	 * Map a single object to a resource class.
	 *
	 * @param string $resource name of the resource class
	 * @param \stdClass $object
	 * @return Resource
	 */
	private static function mapResource($resource, $object)
	{
		if ($object == false || is_string($object)) return $object;

		$baseResource = __NAMESPACE__ . '\\' . $resource;
		$class = (class_exists($baseResource)) ? $baseResource : 'Bigcommerce\\Api\\Resources\\' . $resource;

		return new $class($object);
	}

	/**
	 * Map object representing a count to an integer value.
	 *
	 * @param \stdClass $object
	 * @return int
	 */
	private static function mapCount($object)
	{
		if ($object == false || is_string($object)) return $object;

		return $object->count;
	}

	/**
	 * Swaps a temporary access code for a long expiry auth token.
	 *
	 * @param \stdClass $object
	 * @return \stdClass
	 */
	public static function getAuthToken($object)
	{
		$context = array_merge(array(
			'grant_type' => 'authorization_code'
		), (array)$object);

		$connection = new Connection();
		$connection->useUrlencoded();

		// update with previously selected option
		if (self::$cipher) $connection->setCipher(self::$cipher);
		if (self::$verifyPeer) $connection->verifyPeer(self::$verifyPeer);

		return $connection->post(self::$login_url . '/oauth2/token', $context);
	}

	/**
	 * generate login token
	 *
	 * @param int $id
	 * @param string $redirectUrl
	 * @param string $requestIp
	 * @return mixed
	 * @throws Exception
	 */
	public static function getCustomerLoginToken($id, $redirectUrl = '', $requestIp = '')
	{
		if (empty(self::$client_secret)) {
			throw new Exception('Cannot sign customer login tokens without a client secret');
		}

		$payload = array(
			'iss' => self::$client_id,
			'iat' => time(),
			'jti' => bin2hex(random_bytes(32)),
			'operation' => 'customer_login',
			'store_hash' => self::$store_hash,
			'customer_id' => $id
		);

		if (!empty($redirectUrl)) {
			$payload['redirect_to'] = $redirectUrl;
		}

		if (!empty($requestIp)) {
			$payload['request_ip'] = $requestIp;
		}

		return JWT::encode($payload, self::$client_secret, 'HS256');
	}

	/**
	 * Pings the time endpoint to test the connection to a store.
	 *
	 * @return \DateTime
	 */
	public static function getTime()
	{
		$response = self::connection()->get(self::$api_path . '/time');

		if ($response == false || is_string($response)) return $response;

		return new \DateTime("@{$response->time}");
	}

	/**
	 * Returns the default collection of products.
	 *
	 * @param mixed $filter
	 * @return mixed array|string list of products or XML string if useXml is true
	 */
	public static function getProducts($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/products' . $filter->toQuery(), 'Product');
	}

	/**
	 * Returns the total number of products in the collection.
	 *
	 * @param mixed $filter
	 * @return mixed int|string number of products or XML string if useXml is true
	 */
	public static function getProductsCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/products/count' . $filter->toQuery());
	}

	/**
	 * Returns the collection of configurable fields
	 *
	 * @param int $id product id
	 * @param mixed $filter
	 * @return array
	 */
	public static function getProductConfigurableFields($id, $filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/products/'.$id.'/configurablefields', "ProductConfigurableField");
	}

	/**
	 * The total number of configurable fields in the collection.
	 *
	 * @param int $id product id
	 * @param mixed $filter
	 * @return int
	 */
	public static function getProductConfigurableFieldsCount($id, $filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/products/'.$id.'/configurablefields/count' . $filter->toQuery());
	}

	/**
	 * Returns the collection of discount rules
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getProductDiscountRules($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/products/discount_rules' . $filter->toQuery());
	}

	/**
	 * The total number of discount rules in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getProductDiscountRulesCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/products/discount_rules/count' . $filter->toQuery());
	}

	/**
	 * Create a new product image.
	 *
	 * @param string $productId
	 * @param mixed $object
	 * @return mixed
	 */
	public static function createProductImage($productId, $object)
	{
		return self::createResource('/products/' . $productId . '/images', $object);
	}

	/**
	 * Update a product image.
	 *
	 * @param string $productId
	 * @param string $imageId
	 * @param mixed $object
	 * @return mixed
	 */
	public static function updateProductImage($productId, $imageId, $object)
	{
		return self::updateResource('/products/' . $productId . '/images/' . $imageId, $object);
	}

	/**
	 * Returns a product image resource by the given product id.
	 *
	 * @param int $productId
	 * @param int $imageId
	 * @return Resources\ProductImage|string
	 */
	public static function getProductImage($productId, $imageId)
	{
		return self::getResource('/products/' . $productId . '/images/' . $imageId, 'ProductImage');
	}

	/**
	 * Gets collection of images for a product.
	 *
	 * @param int $id product id
	 * @return mixed array|string list of products or XML string if useXml is true
	 */
	public static function getProductImages($id)
	{
		return self::getResource('/products/' . $id . '/images/', 'ProductImage');
	}

	/**
	 * Delete the given product image.
	 *
	 * @param int $productId
	 * @param int $imageId
	 * @return mixed
	 */
	public static function deleteProductImage($productId, $imageId)
	{
		return self::deleteResource('/products/' . $productId . '/images/' . $imageId);
	}

	/**
	 * Returns the collection of product images
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getProductsImages($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/products/images' . $filter->toQuery(), "ProductImage");
	}

	/**
	 * The total number of product images in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getProductsImagesCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/products/images/count' . $filter->toQuery());
	}

	/**
	 * Return the collection of all option values for a given option.
	 *
	 * @param int $productId
	 * @return mixed
	 */
	public static function getProductOptions($productId)
	{
		return self::getCollection('/products/' . $productId . '/options');
	}

	/**
	 * Return the collection of all option values for a given option.
	 *
	 * @param int $productId
	 * @param int $productOptionId
	 * @return mixed
	 */
	public static function getProductOption($productId, $productOptionId)
	{
		return self::getResource('/products/' . $productId . '/options/' . $productOptionId);
	}

	/**
	 * Return the collection of all option values for a given option.
	 *
	 * @param int $productId
	 * @param int $productRuleId
	 * @return mixed
	 */
	public static function getProductRule($productId, $productRuleId)
	{
		return self::getResource('/products/' . $productId . '/rules/' . $productRuleId);
	}

	/**
	 * Returns the collection of product rules
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getProductRules($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/products/rules'  . $filter->toQuery(), "Rule");
	}

	/**
	 * The total number of product rules in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getProductRulesCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/products/rules/count' . $filter->toQuery());
	}

	/**
	 * Returns the collection of product skus
	 *
	 * @param array|bool $filter
	 * @return array
	 */
	public static function getProductSkus($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/products/skus' . $filter->toQuery(), "Sku");
	}

	/**
	 * The total number of product skus in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getProductSkusCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/products/skus/count' . $filter->toQuery());
	}

	/**
	 * Returns the collection of product videos
	 *
	 * @param array|bool $filter
	 * @return array
	 */
	public static function getProductVideos($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/products/videos' . $filter->toQuery(), "ProductVideo");
	}

	/**
	 * The total number of product videos in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getProductVideosCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/products/videos/count' . $filter->toQuery());
	}

	/**
	 * Gets collection of custom fields for a product.
	 *
	 * @param int $id product ID
	 * @return mixed array|string list of products or XML string if useXml is true
	 */
	public static function getProductCustomFields($id)
	{
		return self::getCollection('/products/' . $id . '/customfields/', 'ProductCustomField');
	}

	/**
	 * Returns a single custom field by given id
	 * @param  int $product_id product id
	 * @param  int $id         custom field id
	 * @return Resources\ProductCustomField|bool Returns ProductCustomField if exists, false if not exists
	 */
	public static function getProductCustomField($product_id, $id)
	{
		return self::getResource('/products/' . $product_id . '/customfields/' . $id, 'ProductCustomField');
	}

	/**
	 * Create a new custom field for a given product.
	 *
	 * @param int $product_id product id
	 * @param mixed $object fields to create
	 * @return Object Object with `id`, `product_id`, `name` and `text` keys
	 */
	public static function createProductCustomField($product_id, $object)
	{
		return self::createResource('/products/' . $product_id . '/customfields', $object);
	}

	/**
	 * Update the given custom field.
	 *
	 * @param int $product_id product id
	 * @param int $id custom field id
	 * @param mixed $object custom field to update
	 * @return mixed
	 */
	public static function updateProductCustomField($product_id, $id, $object)
	{
		return self::updateResource('/products/' . $product_id . '/customfields/' . $id, $object);
	}

	/**
	 * Delete the given custom field.
	 *
	 * @param int $product_id product id
	 * @param int $id custom field id
	 * @return mixed
	 */
	public static function deleteProductCustomField($product_id, $id)
	{
		return self::deleteResource('/products/' . $product_id . '/customfields/' . $id);
	}

	/**
	 * Returns the collection of custom fields
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getProductsCustomFields($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/products/customfields' . $filter->toQuery(), "ProductCustomField");
	}

	/**
	 * The total number of custom fields in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getProductsCustomFieldsCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/products/customfields/count' . $filter->toQuery());
	}

	/**
	 * Gets collection of reviews for a product.
	 *
	 * @param $id
	 * @return mixed
	 */
	public static function getProductReviews($id)
	{
		return self::getCollection('/products/' . $id . '/reviews/', 'ProductReview');
	}

	/**
	 * Returns a single product resource by the given id.
	 *
	 * @param int $id product id
	 * @return Resources\Product|string
	 */
	public static function getProduct($id)
	{
		return self::getResource('/products/' . $id, 'Product');
	}

	/**
	 * Create a new product.
	 *
	 * @param mixed $object fields to create
	 * @return mixed
	 */
	public static function createProduct($object)
	{
		return self::createResource('/products', $object);
	}

	/**
	 * Update the given product.
	 *
	 * @param int $id product id
	 * @param mixed $object fields to update
	 * @return mixed
	 */
	public static function updateProduct($id, $object)
	{
		return self::updateResource('/products/' . $id, $object);
	}

	/**
	 * Delete the given product.
	 *
	 * @param int $id product id
	 * @return mixed
	 */
	public static function deleteProduct($id)
	{
		return self::deleteResource('/products/' . $id);
	}

	/**
	 * Return the collection of options.
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getOptions($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/options' . $filter->toQuery(), 'Option');
	}

	/**
	 * create options
	 *
	 * @param \stdClass $object
	 * @return mixed
	 */
	public static function createOptions($object)
	{
		return self::createResource('/options', $object);
	}


	/**
	 * Return the number of options in the collection
	 *
	 * @return int
	 */
	public static function getOptionsCount()
	{
		return self::getCount('/options/count');
	}

	/**
	 * Return a single option by given id.
	 *
	 * @param int $id option id
	 * @return Resources\Option
	 */
	public static function getOption($id)
	{
		return self::getResource('/options/' . $id, 'Option');
	}



	/**
	 * Delete the given option.
	 *
	 * @param int $id option id
	 * @return mixed
	 */
	public static function deleteOption($id)
	{
		return self::deleteResource('/options/' . $id);
	}

	/**
	 * Return a single value for an option.
	 *
	 * @param int $option_id option id
	 * @param int $id value id
	 * @return Resources\OptionValue
	 */
	public static function getOptionValue($option_id, $id)
	{
		return self::getResource('/options/' . $option_id . '/values/' . $id, 'OptionValue');
	}

	/**
	 * Return the collection of all option values.
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getOptionValues($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/options/values' . $filter->toQuery(), 'OptionValue');
	}

	/**
	 * The number of option values in the collection.
	 *
	 * @return int
	 */
	public static function getOptionValuesCount()
	{
		$page = 1;
		$filter = Filter::create(array('page'=>$page, 'limit'=>250));

		$data = self::getOptionValues($filter);
		$count = count($data);
		while ($data) {
			$page++;
			$filter = Filter::create(array('page'=>$page, 'limit'=>250));
			$data = self::getOptionValues($filter);
			$count += count($data);
		}

		return $count;
	}

	/**
	 * The collection of categories.
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getCategories($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/categories' . $filter->toQuery(), 'Category');
	}

	/**
	 * The number of categories in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getCategoriesCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/categories/count' . $filter->toQuery());
	}

	/**
	 * A single category by given id.
	 *
	 * @param int $id category id
	 * @return Category
	 */
	public static function getCategory($id)
	{
		return self::getResource('/categories/' . $id, 'Category');
	}

	/**
	 * Create a new category from the given data.
	 *
	 * @param mixed $object
	 * @return mixed
	 */
	public static function createCategory($object)
	{
		return self::createResource('/categories/', $object);
	}

	/**
	 * Update the given category.
	 *
	 * @param int $id category id
	 * @param mixed $object
	 * @return mixed
	 */
	public static function updateCategory($id, $object)
	{
		return self::updateResource('/categories/' . $id, $object);
	}

	/**
	 * Delete the given category.
	 *
	 * @param int $id category id
	 * @return mixed
	 */
	public static function deleteCategory($id)
	{
		return self::deleteResource('/categories/' . $id);
	}

	/**
	 * The collection of brands.
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getBrands($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/brands' . $filter->toQuery(), 'Brand');
	}

	/**
	 * The total number of brands in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getBrandsCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/brands/count' . $filter->toQuery());
	}

	/**
	 * A single brand by given id.
	 *
	 * @param int $id brand id
	 * @return Resources\Brand
	 */
	public static function getBrand($id)
	{
		return self::getResource('/brands/' . $id, 'Brand');
	}

	/**
	 * Create a new brand from the given data.
	 *
	 * @param mixed $object
	 * @return mixed
	 */
	public static function createBrand($object)
	{
		return self::createResource('/brands', $object);
	}

	/**
	 * Update the given brand.
	 *
	 * @param int $id brand id
	 * @param mixed $object
	 * @return mixed
	 */
	public static function updateBrand($id, $object)
	{
		return self::updateResource('/brands/' . $id, $object);
	}

	/**
	 * Delete the given brand.
	 *
	 * @param int $id brand id
	 * @return mixed
	 */
	public static function deleteBrand($id)
	{
		return self::deleteResource('/brands/' . $id);
	}

	/**
	 * The collection of orders.
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getOrders($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/orders' . $filter->toQuery(), 'Order');
	}

	/**
	 * The number of orders in the collection.
	 *
	 * @return int
	 */
	public static function getOrdersCount()
	{
		return self::getCount('/orders/count');
	}

	/**
	 * A single order.
	 *
	 * @param int $id order id
	 * @return Resources\Order
	 */
	public static function getOrder($id)
	{
		return self::getResource('/orders/' . $id, 'Order');
	}

	/**
	 * Delete the given order (unlike in the Control Panel, this will permanently
	 * delete the order).
	 *
	 * @param int $id order id
	 * @return mixed
	 */
	public static function deleteOrder($id)
	{
		return self::deleteResource('/orders/' . $id);
	}

	/**
	 * Create an order
	 *
	 * @param \stdClass $object
	 * @return mixed
	 **/
	public static function createOrder($object)
	{
		return self::createResource('/orders', $object);
	}

	/**
	 * Returns the collection of order coupons
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getOrderCoupons($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/orders/coupons' . $filter->toQuery(), 'Coupon');
	}

	/**
	 * The total number of order coupons in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getOrderCouponsCount($filter=false)
	{
		$page = 1;
		$filter = Filter::create(array('page'=>$page, 'limit'=>250));
		$data = self::getOrderCoupons($filter);
		$count = count($data);
		while ($data) {
			$page++;
			$filter = Filter::create(array('page'=>$page, 'limit'=>250));
			$data = self::getOrderCoupons($filter);
			$count += count($data);
		}

		return $count;
	}

	/**
	 * Returns the collection of order products
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getOrderProducts($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/orders/products' . $filter->toQuery(), 'Product');
	}

	/**
	 * The total number of order products in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getOrderProductsCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/orders/products/count' . $filter->toQuery());
	}

	/**
	 * Returns the collection of order shipments
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getOrderShipments($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/orders/shipments' . $filter->toQuery());
	}

	/**
	 * The total number of order shipments in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getOrderShipmentsCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/orders/shipments/count' . $filter->toQuery());
	}

	/**
	 * Returns the collection of shipping addresses
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getOrderShippingAddresses($filter=false) {
		$filter = Filter::create($filter);
		return self::getCollection('/orders/shippingaddresses/' . $filter->toQuery(), "Address");
	}

	/**
	 * The total number of shipping addresses in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getOrderShippingAddressesCount($filter=false) {
		$filter = Filter::create($filter);
		return self::getCount('/orders/shippingaddresses/count' . $filter->toQuery());
	}

	/**
	 * The list of customers.
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getCustomers($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/customers' . $filter->toQuery(), 'Customer');
	}

	/**
	 * The total number of customers in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getCustomersCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/customers/count' . $filter->toQuery());
	}

	/**
	 * Bulk delete customers.
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function deleteCustomers($filter=false)
	{
		$filter = Filter::create($filter);
		return self::deleteResource('/customers' . $filter->toQuery());
	}

	/**
	 * A single customer by given id.
	 *
	 * @param int $id customer id
	 * @return Resources\Customer
	 */
	public static function getCustomer($id)
	{
		return self::getResource('/customers/' . $id, 'Customer');
	}

	/**
	 * Create a new customer from the given data.
	 *
	 * @param mixed $object
	 * @return mixed
	 */
	public static function createCustomer($object)
	{
		return self::createResource('/customers', $object);
	}

	/**
	 * Update the given customer.
	 *
	 * @param int $id customer id
	 * @param mixed $object
	 * @return mixed
	 */
	public static function updateCustomer($id, $object)
	{
		return self::updateResource('/customers/' . $id, $object);
	}

	/**
	 * Delete the given customer.
	 *
	 * @param int $id customer id
	 * @return mixed
	 */
	public static function deleteCustomer($id)
	{
		return self::deleteResource('/customers/' . $id);
	}

	/**
	 * A list of addresses belonging to the given customer.
	 *
	 * @param int $id customer id
	 * @return array
	 */
	public static function getCustomerAddresses($id)
	{
		return self::getCollection('/customers/' . $id . '/addresses', 'Address');
	}

	/**
	 * Returns the collection of customer addresses
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getCustomersAddresses($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/customers/addresses' . $filter->toQuery(), "Address");
	}

	/**
	 * The number of customer addresses in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getCustomersAddressesCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/customers/addresses/count' . $filter->toQuery());
	}

	/**
	 * The number of customer groups in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getCustomerGroupsCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/customer_groups/count' . $filter->toQuery());
	}

	/**
	 * Returns the collection of option sets.
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getOptionSets($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/optionsets' . $filter->toQuery(), 'OptionSet');
	}

	/** create optionsets **/
	public static function createOptionsets($object)
	{
		return self::createResource('/optionsets', $object);
	}

	/** connect optionsets options **/
	public static function createOptionsets_Options($object, $id)
	{
		return self::createResource('/optionsets/'.$id.'/options', $object);
	}


	/**
	 * Returns the total number of option sets in the collection.
	 *
	 * @return int
	 */
	public static function getOptionSetsCount()
	{
		return self::getCount('/optionsets/count');
	}

	/**
	 * A single option set by given id.
	 *
	 * @param int $id option set id
	 * @return Resources\OptionSet
	 */
	public static function getOptionSet($id)
	{
		return self::getResource('/optionsets/' . $id, 'OptionSet');
	}

	/**
	 * Returns the collection of optionset options
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getOptionSetOptions($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/optionsets/options' . $filter->toQuery(), 'Option');
	}

	/**
	 * The number of optionset options in the collection.
	 *
	 * @return int
	 */
	public static function getOptionSetOptionsCount()
	{
		$count = 0;
		$page = 1;
		$filter = Filter::create(array('page'=>$page, 'limit'=>250));
		$data = self::getOptionSetOptions($filter);
		$count = count($data);

		while ($data) {
			$page++;
			$filter = Filter::create(array('page'=>$page, 'limit'=>250));
			$data = self::getOptionSetOptions($filter);
			$count += count($data);
		}

		return $count;
	}

	/**
	 * Status codes used to represent the state of an order.
	 *
	 * @return array
	 */
	public static function getOrderStatuses()
	{
		return self::getCollection('/orderstatuses', 'OrderStatus');
	}

	/**
	 * Enabled shipping methods.
	 *
	 * @return array
	 */
	public static function getShippingMethods()
	{
		return self::getCollection('/shipping/methods', 'ShippingMethod');
	}

	/**
	 * Get collection of skus for all products
	 *
	 * @param array $filter
	 * @return mixed
	 */
	public static function getSkus($filter = array())
	{
		$filter = Filter::create($filter);
		return self::getCollection('/products/skus' . $filter->toQuery(), 'Sku');
	}

	/**
	 * Create a SKU
	 *
	 * @param $productId
	 * @param $object
	 * @return mixed
	 */
	public static function createSku($productId, $object)
	{
		return self::createResource('/products/' . $productId . '/skus', $object);
	}

	/**
	 * Update sku
	 *
	 * @param $id
	 * @param $object
	 * @return mixed
	 */
	public static function updateSku($id, $object)
	{
		return self::updateResource('/product/skus/' . $id, $object);
	}

	/**
	 * Returns the total number of SKUs in the collection.
	 *
	 * @return int
	 */
	public static function getSkusCount()
	{
		return self::getCount('/products/skus/count');
	}

	/**
	 * Get a single coupon by given id.
	 *
	 * @param int $id customer id
	 * @return Resources\Coupon
	 */
	public static function getCoupon($id)
	{
		return self::getResource('/coupons/' . $id, 'Coupon');
	}

	/**
	 * Get coupons
	 *
	 * @param array $filter
	 * @return mixed
	 */
	public static function getCoupons($filter = array())
	{
		$filter = Filter::create($filter);
		return self::getCollection('/coupons' . $filter->toQuery(), 'Coupon');
	}

	/**
	 * Create coupon
	 *
	 * @param $object
	 * @return mixed
	 */
	public static function createCoupon($object)
	{
		return self::createResource('/coupons', $object);
	}

	/**
	 * Update coupon
	 *
	 * @param $id
	 * @param $object
	 * @return mixed
	 */
	public static function updateCoupon($id, $object)
	{
		return self::updateResource('/coupons/' . $id, $object);
	}

	/**
	 * Delete the given coupon.
	 *
	 * @param int $id coupon id
	 * @return mixed
	 */
	public static function deleteCoupon($id)
	{
		return self::deleteResource('/coupons/' . $id);
	}

	/**
	 * Delete all Coupons.
	 *
	 * @return mixed
	 */
	public static function deleteAllCoupons()
	{
		return self::deleteResource('/coupons');
	}

	/**
	 * Return the number of coupons
	 *
	 * @return int
	 */
	public static function getCouponsCount()
	{
		return self::getCount('/coupons/count');
	}

	/**
	 * Returns the collection of redirects
	 *
	 * @param mixed $filter
	 * @return array
	 */
	public static function getRedirects($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/redirects' . $filter->toQuery(), 'OptionSet');
	}

	/**
	 * The total number of redirects in the collection.
	 *
	 * @param mixed $filter
	 * @return int
	 */
	public static function getRedirectsCount($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCount('/customer_groups/count');
	}

	/**
	 * The request logs with usage history statistics.
	 */
	public static function getRequestLogs()
	{
		return self::getCollection('/requestlogs', 'RequestLog');
	}

	public static function getStore()
	{
		$response = self::connection()->get(self::$api_path . '/store');
		return $response;
	}

	/**
	 * The number of requests remaining at the current time. Based on the
	 * last request that was fetched within the current script. If no
	 * requests have been made, pings the time endpoint to get the value.
	 *
	 * @return int
	 */
	public static function getRequestsRemaining()
	{
		$limit = self::connection()->getHeader('X-BC-ApiLimit-Remaining');

		if (!$limit) {
			$result = self::getTime();

			if (!$result) return false;

			$limit = self::connection()->getHeader('X-BC-ApiLimit-Remaining');
		}

		return intval($limit);
	}

	/**
	 * Get a single shipment by given id.
	 *
	 * @param $orderID
	 * @param $shipmentID
	 * @return mixed
	 */
	public static function getShipment($orderID, $shipmentID)
	{
		return self::getResource('/orders/' . $orderID . '/shipments/' . $shipmentID, 'Shipment');
	}

	/**
	 * Get shipments for a given order
	 *
	 * @param $orderID
	 * @param array $filter
	 * @return mixed
	 */
	public static function getShipments($orderID, $filter = array())
	{
		$filter = Filter::create($filter);
		return self::getCollection('/orders/' . $orderID . '/shipments' . $filter->toQuery(), 'Shipment');
	}

	/**
	 * Create shipment
	 *
	 * @param $orderID
	 * @param $object
	 * @return mixed
	 */
	public static function createShipment($orderID, $object)
	{
		return self::createResource('/orders/' . $orderID . '/shipments', $object);
	}

	/**
	 * Update shipment
	 *
	 * @param $orderID
	 * @param $shipmentID
	 * @param $object
	 * @return mixed
	 */
	public static function updateShipment($orderID, $shipmentID, $object)
	{
		return self::updateResource('/orders/' . $orderID . '/shipments/' . $shipmentID, $object);
	}

	/**
	 * Delete the given shipment.
	 *
	 * @param $orderID
	 * @param $shipmentID
	 * @return mixed
	 */
	public static function deleteShipment($orderID, $shipmentID)
	{
		return self::deleteResource('/orders/' . $orderID . '/shipments/' . $shipmentID);
	}

	/**
	 * Delete all Shipments for the given order.
	 *
	 * @param $orderID
	 * @return mixed
	 */
	public static function deleteAllShipmentsForOrder($orderID)
	{
		return self::deleteResource('/orders/' . $orderID . '/shipments');
	}

	/**
	 * Create a new currency.
	 *
	 * @param mixed $object fields to create
	 * @return mixed
	 */
	public static function createCurrency($object)
	{
		return self::createResource('/currencies', $object);
	}

	/**
	 * Returns a single currency resource by the given id.
	 *
	 * @param int $id currency id
	 * @return Resources\Currency|string
	 */
	public static function getCurrency($id)
	{
		return self::getResource('/currencies/' . $id, 'Currency');
	}

	/**
	 * Update the given currency.
	 *
	 * @param int $id currency id
	 * @param mixed $object fields to update
	 * @return mixed
	 */
	public static function updateCurrency($id, $object)
	{
		return self::updateResource('/currencies/' . $id, $object);
	}

	/**
	 * Delete the given currency.
	 *
	 * @param int $id currency id
	 * @return mixed
	 */
	public static function deleteCurrency($id)
	{
		return self::deleteResource('/currencies/' . $id);
	}

	/**
	 * Returns the default collection of currencies.
	 *
	 * @param array $filter
	 * @return mixed array|string list of currencies or XML string if useXml is true
	 */
	public static function getCurrencies($filter = array())
	{
		$filter = Filter::create($filter);
		return self::getCollection('/currencies' . $filter->toQuery(), 'Currency');
	}

	/**
	 * get list of webhooks
	 *
	 * @return 	array
	 */
	public static function getWebhooks()
	{
		return self::getCollection('/hooks', 'Webhook');
	}

	/**
	 * get a specific webhook by id
	 *
	 * @params 	int 		$id 		webhook id
	 * @return 	\stdClass 	$object
	 */
	public static function getWebhook($id)
	{
		return self::getResource('/hooks/' . $id, 'Webhook');
	}

	/**
	 * create webhook
	 * @param 	\stdClass 	$object 	webhook params
	 * @return 	\stdClass
	 */
	public static function createWebhook($object)
	{
		return self::createResource('/hooks', $object);
	}

	/**
	 * create a webhook
	 * @param 	int 		$id 		webhook id
	 * @param 	\stdClass 	$object 	webhook params
	 * @return 	\stdClass
	 */
	public static function updateWebhook($id, $object)
	{
		return self::updateResource('/hooks/' . $id, $object);
	}

	/**
	 * delete a webhook
	 * @param 	int 		$id 		webhook id
	 * @return 	\stdClass
	 */
	public static function deleteWebhook($id)
	{
		return self::deleteResource('/hooks/' . $id);
	}

	/**
	 * Get all content pages
	 *
	 * @return mixed
	 */
	public static function getPages()
	{
		return self::getCollection('/pages', 'Page');
	}

	/**
	 * Get single content pages
	 *
	 * @param int $pageId
	 * @return mixed
	 */
	public static function getPage($pageId)
	{
		return self::getResource('/pages/' . $pageId, 'Page');
	}

	/**
	 * Create a new content pages
	 *
	 * @param $object
	 * @return mixed
	 */
	public static function createPage($object)
	{
		return self::createResource('/pages', $object);
	}

	/**
	 * Update an existing content page
	 *
	 * @param int $pageId
	 * @param $object
	 * @return mixed
	 */
	public static function updatePage($pageId, $object)
	{
		return self::updateResource('/pages/' . $pageId, $object);
	}

	/**
	 * Delete an existing content page
	 *
	 * @param int $pageId
	 * @return mixed
	 */
	public static function deletePage($pageId)
	{
		return self::deleteResource('/pages/' . $pageId);
	}

	/**
	 * Create a Gift Certificate
	 *
	 * @param array $object
	 * @return mixed
	 */
	public static function createGiftCertificate($object)
	{
		return self::createResource('/gift_certificates', $object);
	}

	/**
	 * Get a Gift Certificate
	 *
	 * @param int $giftCertificateId
	 * @return mixed
	 */
	public static function getGiftCertificate($giftCertificateId)
	{
		return self::getResource('/gift_certificates/' . $giftCertificateId);
	}

	/**
	 * Return the collection of all gift certificates.
	 *
	 * @param array $filter
	 * @return mixed
	 */
	public static function getGiftCertificates($filter = array())
	{
		$filter = Filter::create($filter);
		return self::getCollection('/gift_certificates' . $filter->toQuery());
	}

	/**
	 * Update a Gift Certificate
	 *
	 * @param int $giftCertificateId
	 * @param array $object
	 * @return mixed
	 */
	public static function updateGiftCertificate($giftCertificateId, $object)
	{
		return self::updateResource('/gift_certificates/' . $giftCertificateId, $object);
	}

	/**
	 * Delete a Gift Certificate
	 *
	 * @param int $giftCertificateId
	 * @return mixed
	 */
	public static function deleteGiftCertificate($giftCertificateId)
	{
		return self::deleteResource('/gift_certificates/' . $giftCertificateId);
	}

	/**
	 * Delete all Gift Certificates
	 *
	 * @return mixed
	 */
	public static function deleteAllGiftCertificates()
	{
		return self::deleteResource('/gift_certificates');
	}

	/**
	 * Create Product Review
	 *
	 * @param int $productId
	 * @param array $object
	 * @return mixed
	 */
	public static function createProductReview($productId, $object)
	{
		return self::createResource('/products/' . $productId . '/reviews', $object);
	}

	/**
	 * Create Product Bulk Discount rules
	 *
	 * @param string $productId
	 * @param array $object
	 * @return mixed
	 */
	public static function createProductBulkPricingRules($productId, $object)
	{
		return self::createResource('/products/' . $productId . '/discount_rules', $object);
	}

	/**
	 * Create a Marketing Banner
	 *
	 * @param array $object
	 * @return mixed
	 */
	public static function createMarketingBanner($object)
	{
		return self::createResource('/banners', $object);
	}

	/**
	 * Get all Marketing Banners
	 *
	 * @return mixed
	 */
	public static function getMarketingBanners()
	{
		return self::getCollection('/banners');
	}

	/**
	 * Delete all Marketing Banners
	 *
	 * @return mixed
	 */
	public static function deleteAllMarketingBanners()
	{
		return self::deleteResource('/banners');
	}

	/**
	 * Delete a specific Marketing Banner
	 *
	 * @param int $bannerID
	 * @return mixed
	 */
	public static function deleteMarketingBanner($bannerID)
	{
		return self::deleteResource('/banners/' . $bannerID);
	}

	/**
	 * Update an existing banner
	 *
	 * @param int $bannerID
	 * @param array $object
	 * @return mixed
	 */
	public static function updateMarketingBanner($bannerID, $object)
	{
		return self::updateResource('/banners/' . $bannerID, $object);
	}

	/**
	 * Add a address to the customer's address book.
	 *
	 * @param int $customerID
	 * @param array $object
	 * @return mixed
	 */
	public static function createCustomerAddress($customerID, $object)
	{
		return self::createResource('/customers/' . $customerID . '/addresses', $object);
	}

	/**
	 * Create a product rule
	 *
	 * @param int $productID
	 * @param array $object
	 * @return mixed
	 */
	public static function createProductRule($productID, $object)
	{
		return self::createResource('/products/' . $productID . '/rules', $object);
	}

	/**
	 * Create a customer group.
	 *
	 * @param array $object
	 * @return mixed
	 */
	public static function createCustomerGroup($object)
	{
		return self::createResource('/customer_groups', $object);
	}

	/**
	 * Get list of customer groups
	 *
	 * @return mixed
	 */
	public static function getCustomerGroups()
	{
		return self::getCollection('/customer_groups');
	}

	/**
	 * Delete a customer group
	 *
	 * @param int $customerGroupId
	 * @return mixed
	 */
	public static function deleteCustomerGroup($customerGroupId)
	{
		return self::deleteResource('/customer_groups/' . $customerGroupId);
	}

	/**
	 * Delete all customers
	 *
	 * @return mixed
	 */
	public static function deleteAllCustomers()
	{
		return self::deleteResource('/customers');
	}

	/**
	 * Delete all options
	 *
	 * @return mixed
	 */
	public static function deleteAllOptions()
	{
		return self::deleteResource('/options');
	}

	/**
	 * Return the option value object that was created.
	 *
	 * @param int $optionId
	 * @param array $object
	 * @return mixed
	 */
	public static function createOptionValue($optionId, $object)
	{
		return self::createResource('/options/' . $optionId . '/values', $object);
	}

	/**
	 * Delete all option sets that were created.
	 *
	 * @return mixed
	 */
	public static function deleteAllOptionSets()
	{
		return self::deleteResource('/optionsets');
	}

	/**
	 * Return the option value object that was updated.
	 *
	 * @param int $optionId
	 * @param int $optionValueId
	 * @param array $object
	 * @return mixed
	 */
	public static function updateOptionValue($optionId, $optionValueId, $object)
	{
		return self::updateResource(
			'/options/' . $optionId . '/values/' . $optionValueId,
			$object
		);
	}

	/**
	 * get list of blog posts
	 *
	 * @return 	array
	 */
	public static function getBlogPosts($filter=false)
	{
		$filter = Filter::create($filter);
		return self::getCollection('/blog/posts' . $filter->toQuery(), 'BlogPost');
	}

	/**
	 * get a specific blog post by id
	 *
	 * @params 	int 		$id 		post id
	 * @return 	\stdClass 	$object
	 */
	public static function getBlogPost($id)
	{
		return self::getResource('/blog/posts/' . $id, 'BlogPost');
	}

	/**
	 * create blog post
	 * @param 	\stdClass 	$object 	post params
	 * @return 	\stdClass
	 */
	public static function createBlogPost($object)
	{
		return self::createResource('/blog/posts', $object);
	}

	/**
	 * update blog post
	 * @param 	int 		$id 		post id
	 * @param 	\stdClass 	$object 	post params
	 * @return 	\stdClass
	 */
	public static function updateBlogPost($id, $object)
	{
		return self::updateResource('/blog/posts/' . $id, $object);
	}

	/**
	 * delete a blog post
	 * @param 	int 		$id 		post id
	 * @return 	\stdClass
	 */
	public static function deleteBlogPost($id)
	{
		return self::deleteResource('/blog/posts/' . $id);
	}
}
