<?php
namespace Fortuneglobe\IceHawk\Routing;

use Fortuneglobe\IceHawk\Interfaces\HandlesReadRequest;
use Fortuneglobe\IceHawk\Interfaces\ProvidesRequestInfo;
use Fortuneglobe\IceHawk\Interfaces\RoutesToReadHandler;
use Fortuneglobe\IceHawk\Routing\Patterns\ExactRegExp;

/**
 * Class ReadRouteGroup
 *
 * @package Fortuneglobe\IceHawk\Routing
 */
final class ReadRouteGroup implements RoutesToReadHandler
{
	/** @var ExactRegExp */
	private $pattern;

	/** @var HandlesReadRequest */
	private $requestHandler;

	/**
	 * @var array
	 */
	private $routes;

	/**
	 * @var array
	 */
	private $uriParams = [ ];

	public function __construct( ExactRegExp $pattern, HandlesReadRequest $requestHandler, array $routes = [ ] )
	{
		$this->pattern        = $pattern;
		$this->requestHandler = $requestHandler;
		$this->routes         = $routes;
	}

	public function addRoute( RoutesToReadHandler $route ) : self
	{
		$this->routes[] = $route;

		return $this;
	}

	public function matches( ProvidesRequestInfo $requestInfo ) : bool
	{
		if ( $this->pattern->matches( $requestInfo->getUri() ) )
		{
			if ( $this->pattern->matchedExact() )
			{
				return true;
			}

			foreach ( $this->routes as $route )
			{
				if ( $route->matches( $requestInfo ) )
				{
					$this->requestHandler = $route->getRequestHandler();
					$this->uriParams      = $route->getUriParams();

					return true;
				}
			}
		}

		return false;
	}

	public function getUriParams() : array
	{
		return $this->uriParams;
	}

	public function getRequestHandler() : HandlesReadRequest
	{
		return $this->requestHandler;
	}
}