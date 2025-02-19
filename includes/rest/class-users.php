<?php
namespace Activitypub\Rest;

use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use Activitypub\Webfinger;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Users as User_Collection;

use function Activitypub\is_activitypub_request;

/**
 * ActivityPub Followers REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#followers
 */
class Users {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		self::register_routes();
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/users/(?P<user_id>[\w\-\.]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get' ),
					'args'                => self::request_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);

		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/users/(?P<user_id>[\w\-\.]+)/remote-follow',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'remote_follow_get' ),

					'args'                => array(
						'resource' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Handle GET request
	 *
	 * @param  WP_REST_Request   $request
	 *
	 * @return WP_REST_Response
	 */
	public static function get( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = User_Collection::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// redirect to canonical URL if it is not an ActivityPub request
		if ( ! is_activitypub_request() ) {
			header( 'Location: ' . $user->get_canonical_url(), true, 301 );
			exit;
		}

		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_rest_users_pre' );

		$user->set_context(
			Activity::CONTEXT
		);

		$json = $user->to_array();

		$rest_response = new WP_REST_Response( $json, 200 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );

		return $rest_response;
	}


	/**
	 * Endpoint for remote follow UI/Block
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return void|string The URL to the remote follow page
	 */
	public static function remote_follow_get( WP_REST_Request $request ) {
		$resource = $request->get_param( 'resource' );
		$user_id  = $request->get_param( 'user_id' );
		$user     = User_Collection::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$template = Webfinger::get_remote_follow_endpoint( $resource );

		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$resource = $user->get_resource();
		$url      = str_replace( '{uri}', $resource, $template );

		return new WP_REST_Response(
			array( 'url' => $url ),
			200
		);
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function request_parameters() {
		$params = array();

		$params['page'] = array(
			'type' => 'string',
		);

		$params['user_id'] = array(
			'required' => true,
			'type'     => 'string',
		);

		return $params;
	}
}
