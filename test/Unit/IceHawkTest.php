<?php
/**
 * @author h.woltersdorf
 */

namespace Fortuneglobe\IceHawk\Test\Unit;

use Fortuneglobe\IceHawk\Constants\Http;
use Fortuneglobe\IceHawk\Events\HandlingRequestEvent;
use Fortuneglobe\IceHawk\Events\IceHawkWasInitializedEvent;
use Fortuneglobe\IceHawk\Events\RequestWasHandledEvent;
use Fortuneglobe\IceHawk\IceHawk;
use Fortuneglobe\IceHawk\IceHawkConfig;
use Fortuneglobe\IceHawk\IceHawkDelegate;
use Fortuneglobe\IceHawk\Interfaces\HandlesIceHawkTasks;
use Fortuneglobe\IceHawk\Interfaces\ListensToIceHawkEvents;
use Fortuneglobe\IceHawk\Interfaces\RewritesUri;
use Fortuneglobe\IceHawk\Interfaces\ServesIceHawkConfig;
use Fortuneglobe\IceHawk\RequestInfo;
use Fortuneglobe\IceHawk\Requests\GetRequest;
use Fortuneglobe\IceHawk\Responses\Redirect;
use Fortuneglobe\IceHawk\UriResolver;
use Fortuneglobe\IceHawk\UriRewriter;

class IceHawkTest extends \PHPUnit_Framework_TestCase
{
	public function testDelegateMethodsWillBeCalledDuringInitialization()
	{
		$config   = new IceHawkConfig();
		$delegate = $this->prophesize( HandlesIceHawkTasks::class );

		$delegate->configureErrorHandling()->shouldBeCalled();
		$delegate->configureSession()->shouldBeCalled();

		$iceHawk = new IceHawk( $config, $delegate->reveal() );
		$iceHawk->init();
	}

	public function testPublishesEventWhenInitializationIsDone()
	{
		$initEvent     = new IceHawkWasInitializedEvent();
		$eventListener = $this->getMockBuilder( ListensToIceHawkEvents::class )
		                      ->setMethods( [ 'acceptsEvent', 'notify' ] )
		                      ->getMockForAbstractClass();

		$eventListener->expects( $this->once() )
		              ->method( 'acceptsEvent' )
		              ->with( $this->equalTo( $initEvent ) )
		              ->willReturn( true );

		$eventListener->expects( $this->once() )
		              ->method( 'notify' )
		              ->with( $this->equalTo( $initEvent ) );

		$config = $this->getMockBuilder( ServesIceHawkConfig::class )
		               ->setMethods( [ 'getEventListeners' ] )
		               ->getMockForAbstractClass();

		$config->expects( $this->once() )
		       ->method( 'getEventListeners' )
		       ->willReturn( [ $eventListener ] );

		$delegate = new IceHawkDelegate();

		$iceHawk = new IceHawk( $config, $delegate );
		$iceHawk->init();
	}

	/**
	 * @expectedException \Fortuneglobe\IceHawk\Exceptions\MalformedRequestUri
	 */
	public function testHandlingMalformedRequestThrowsException()
	{
		$config   = new IceHawkConfig();
		$delegate = new IceHawkDelegate();

		$iceHawk = new IceHawk( $config, $delegate );
		$iceHawk->init();

		$iceHawk->handleRequest();
	}

	public function testCanCallHandlerForGetRequest()
	{
		$config = $this->getMockBuilder( ServesIceHawkConfig::class )
		               ->setMethods(
			               [
				               'getProjectNamespace', 'getRequestInfo', 'getUriRewriter', 'getUriResolver',
				               'getEventListeners'
			               ]
		               )
		               ->getMockForAbstractClass();

		$requestInfo = new RequestInfo(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/domain/ice_hawk_read'
			]
		);

		$config->expects( $this->once() )->method( 'getProjectNamespace' )->willReturn( __NAMESPACE__ . '\\Fixtures' );
		$config->expects( $this->once() )->method( 'getRequestInfo' )->willReturn( $requestInfo );
		$config->expects( $this->once() )->method( 'getUriRewriter' )->willReturn( new UriRewriter() );
		$config->expects( $this->once() )->method( 'getUriResolver' )->willReturn( new UriResolver() );
		$config->expects( $this->exactly( 3 ) )->method( 'getEventListeners' )->willReturn( [ ] );

		$delegate = new IceHawkDelegate();

		$iceHawk = new IceHawk( $config, $delegate );
		$iceHawk->init();
		$iceHawk->handleRequest();

		$this->expectOutputString( 'Handler method for get request called.' );
	}

	public function testCanCallHandlerForPostRequest()
	{
		$config = $this->getMockBuilder( ServesIceHawkConfig::class )
		               ->setMethods(
			               [
				               'getProjectNamespace', 'getRequestInfo', 'getUriRewriter', 'getUriResolver',
				               'getEventListeners'
			               ]
		               )
		               ->getMockForAbstractClass();

		$requestInfo = new RequestInfo(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/domain/ice_hawk_write'
			]
		);

		$config->expects( $this->once() )->method( 'getProjectNamespace' )->willReturn( __NAMESPACE__ . '\\Fixtures' );
		$config->expects( $this->once() )->method( 'getRequestInfo' )->willReturn( $requestInfo );
		$config->expects( $this->once() )->method( 'getUriRewriter' )->willReturn( new UriRewriter() );
		$config->expects( $this->once() )->method( 'getUriResolver' )->willReturn( new UriResolver() );
		$config->expects( $this->exactly( 3 ) )->method( 'getEventListeners' )->willReturn( [ ] );

		$delegate = new IceHawkDelegate();

		$iceHawk = new IceHawk( $config, $delegate );
		$iceHawk->init();
		$iceHawk->handleRequest();

		$this->expectOutputString( 'Handler method for post request called.' );
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCanRewriteUrl()
	{
		$config = $this->getMockBuilder( ServesIceHawkConfig::class )
		               ->setMethods( [ 'getRequestInfo', 'getUriRewriter', 'getEventListeners' ] )
		               ->getMockForAbstractClass();

		$requestInfo = new RequestInfo(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/domain/ice_hawk_rewrite'
			]
		);

		$uriRewriter = $this->getMockBuilder( RewritesUri::class )->setMethods( [ 'rewrite' ] )->getMock();
		$uriRewriter->expects( $this->once() )->method( 'rewrite' )->with( $requestInfo )->willReturn(
			new Redirect( '/domain/rewritten', Http::MOVED_PERMANENTLY )
		);

		$config->expects( $this->once() )->method( 'getRequestInfo' )->willReturn( $requestInfo );
		$config->expects( $this->once() )->method( 'getUriRewriter' )->willReturn( $uriRewriter );
		$config->expects( $this->once() )->method( 'getEventListeners' )->willReturn( [ ] );

		$delegate = new IceHawkDelegate();

		$iceHawk = new IceHawk( $config, $delegate );
		$iceHawk->init();
		$iceHawk->handleRequest();

		$this->assertContains( 'Location: /domain/rewritten', xdebug_get_headers() );
		$this->assertEquals( Http::MOVED_PERMANENTLY, http_response_code() );
	}

	public function testPublishesEventsWhenHandlingRequest()
	{
		$config = $this->getMockBuilder( ServesIceHawkConfig::class )
		               ->setMethods(
			               [
				               'getProjectNamespace', 'getRequestInfo', 'getUriRewriter', 'getUriResolver',
				               'getEventListeners'
			               ]
		               )
		               ->getMockForAbstractClass();

		$requestInfo = new RequestInfo(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/domain/valid_read_test'
			]
		);

		$initEvent     = new IceHawkWasInitializedEvent();
		$handlingEvent = new HandlingRequestEvent( $requestInfo, new GetRequest( [ ] ) );
		$handledEvent  = new RequestWasHandledEvent( $requestInfo, new GetRequest( [ ] ) );

		$eventListener = $this->getMockBuilder( ListensToIceHawkEvents::class )
		                      ->setMethods( [ 'acceptsEvent', 'notify' ] )
		                      ->getMockForAbstractClass();

		$eventListener->expects( $this->exactly( 3 ) )
		              ->method( 'acceptsEvent' )
		              ->withConsecutive(
			              [ $this->equalTo( $initEvent ) ],
			              [ $this->equalTo( $handlingEvent ) ],
			              [ $this->equalTo( $handledEvent ) ]
		              )
		              ->willReturn( true );

		$eventListener->expects( $this->exactly( 3 ) )
		              ->method( 'notify' )
		              ->withConsecutive(
			              [ $this->equalTo( $initEvent ) ],
			              [ $this->equalTo( $handlingEvent ) ],
			              [ $this->equalTo( $handledEvent ) ]
		              );

		$config->expects( $this->once() )->method( 'getProjectNamespace' )->willReturn( __NAMESPACE__ . '\\Fixtures' );
		$config->expects( $this->once() )->method( 'getRequestInfo' )->willReturn( $requestInfo );
		$config->expects( $this->once() )->method( 'getUriRewriter' )->willReturn( new UriRewriter() );
		$config->expects( $this->once() )->method( 'getUriResolver' )->willReturn( new UriResolver() );
		$config->expects( $this->exactly( 3 ) )->method( 'getEventListeners' )->willReturn( [ $eventListener ] );

		$delegate = new IceHawkDelegate();

		$iceHawk = new IceHawk( $config, $delegate );
		$iceHawk->init();
		$iceHawk->handleRequest();
	}
}
