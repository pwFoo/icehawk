<?php
namespace IceHawk\IceHawk\Tests\Unit\Routing;

use IceHawk\IceHawk\Constants\HttpMethod;
use IceHawk\IceHawk\Defaults\RequestInfo;
use IceHawk\IceHawk\Defaults\RequestProxy;
use IceHawk\IceHawk\Interfaces\ProvidesRequestInfo;
use IceHawk\IceHawk\Routing\Patterns\NamedRegExp;
use IceHawk\IceHawk\Routing\RouteRedirect;

/**
 * Class RouteRedirectTest
 *
 * @package IceHawk\IceHawk\Tests\Unit\Routing
 */
class RequestProxyTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider routeWriteRedirectDataProvider
	 */
	public function testProxyWriteRoutes(
		array $routeRedirects, ProvidesRequestInfo $request, array $postData,
		string $expectedFinalUri, string $expectedFinalMethod, string $expectedQueryString
	)
	{
		$requestProxy = new RequestProxy();

		foreach ( $routeRedirects as $routeRedirect )
		{
			$requestProxy->addRedirect( $routeRedirect );
		}

		$_POST   = $postData;
		$request = $requestProxy->proxyRequest( $request );

		$this->assertEquals( $expectedFinalUri, $request->getUri() );
		$this->assertEquals( $expectedFinalMethod, $request->getMethod() );
		$this->assertEquals( $expectedQueryString, $request->getQueryString() );
		$this->assertEquals( $postData, $_GET );
	}

	public function routeWriteRedirectDataProvider()
	{
		return [
			[
				[
					new RouteRedirect(
						new NamedRegExp( '^/companies/stocks$' ),
						'/stocks/company/:companyId',
						HttpMethod::PUT
					),
					new RouteRedirect(
						new NamedRegExp( '^/companies/(?<companyId>\d*)/stores/(?<storeId>\d*)/stocks$' ),
						'/stocks/store/:storeId',
						HttpMethod::GET
					),
					new RouteRedirect(
						new NamedRegExp( '^/companies/stores/(?<storeId>\d*)/(?<companyId>\d*)/stocks$' ),
						'/stocks/company/:companyId',
						HttpMethod::PUT
					),
				],
				new RequestInfo(
					[ 'REQUEST_METHOD' => HttpMethod::POST, 'REQUEST_URI' => '/companies/1/stores/2/stocks' ]
				),
				[ 'stock' => '3' ],
				'/stocks/store/2',
				HttpMethod::GET,
				'companyId=1&storeId=2',
			],
			[
				[
					new RouteRedirect(
						new NamedRegExp( '^/companies/stocks$' ),
						'/stocks/company/:companyId',
						HttpMethod::HEAD
					),
					new RouteRedirect(
						new NamedRegExp( '^/companies/(?<companyId>\d*)/stores/(?<storeId>\d*)/stocks$' ),
						'/stocks/store/:storeId',
						HttpMethod::GET
					),
					new RouteRedirect(
						new NamedRegExp( '^/companies/stores/(?<storeId>\d*)/(?<companyId>\d*)/stocks$' ),
						'/stocks/company/:companyId',
						HttpMethod::PUT
					),
				],
				new RequestInfo(
					[
						'REQUEST_METHOD' => HttpMethod::HEAD, 'REQUEST_URI' => '/companies/1/stores/2/stocks',
						'QUERY_STRING'   => 'stock=1',
					]
				),
				[],
				'/stocks/store/2',
				HttpMethod::GET,
				'stock=1&companyId=1&storeId=2',
			],
		];
	}

	/**
	 * @dataProvider routeReadRedirectDataProvider
	 */
	public function testProxyReadRoutes(
		array $routeRedirects, ProvidesRequestInfo $request, string $expectedFinalUri, string $expectedFinalMethod,
		array $expectedPostData
	)
	{
		$requestProxy = new RequestProxy();

		foreach ( $routeRedirects as $routeRedirect )
		{
			$requestProxy->addRedirect( $routeRedirect );
		}

		$request = $requestProxy->proxyRequest( $request );

		$this->assertEquals( $expectedFinalUri, $request->getUri() );
		$this->assertEquals( $expectedFinalMethod, $request->getMethod() );
		$this->assertEquals( $expectedPostData, $_POST );
	}

	public function routeReadRedirectDataProvider()
	{
		return [
			[
				[
					new RouteRedirect(
						new NamedRegExp( '^/companies/stocks$' ),
						'/stocks/company/:companyId',
						HttpMethod::GET
					),
					new RouteRedirect(
						new NamedRegExp( '^/companies/(?<companyId>\d*)/stores/(?<storeId>\d*)/stocks$' ),
						'/company/:companyId/stocks/store/:storeId',
						HttpMethod::POST
					),
					new RouteRedirect(
						new NamedRegExp( '^/companies/stores/(?<storeId>\d*)/(?<companyId>\d*)/stocks$' ),
						'/stocks/company/:companyId',
						HttpMethod::GET
					),
				],
				new RequestInfo(
					[ 'REQUEST_METHOD' => HttpMethod::GET, 'REQUEST_URI' => '/companies/1/stores/2/stocks' ]
				),
				'/company/1/stocks/store/2',
				HttpMethod::POST,
				[ 'companyId' => '1', 'storeId' => '2' ],
			],
			[
				[
					new RouteRedirect(
						new NamedRegExp( '^/companies/stocks$' ),
						'/stocks/company/:companyId',
						HttpMethod::DELETE
					),
					new RouteRedirect(
						new NamedRegExp( '^/companies/(?<companyId>\d*)/stores/(?<storeId>\d*)/stocks$' ),
						'/company/:companyId/stocks/store/:storeId',
						HttpMethod::PUT
					),
					new RouteRedirect(
						new NamedRegExp( '^/companies/stores/(?<storeId>\d*)/(?<companyId>\d*)/stocks$' ),
						'/stocks/company/:companyId',
						HttpMethod::POST
					),
				],
				new RequestInfo(
					[
						'REQUEST_METHOD' => HttpMethod::POST, 'REQUEST_URI' => '/companies/1/stores/2/stocks',
					]
				),
				'/company/1/stocks/store/2',
				HttpMethod::PUT,
				[ 'companyId' => '1', 'storeId' => '2' ],
			],
		];
	}
}