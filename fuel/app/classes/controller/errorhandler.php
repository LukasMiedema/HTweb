<?php
/**
 * Routed to by the router configuration. Currently only implementing HttpNotFound (404)
 */
class Controller_ErrorHandler extends Controller_Base {
	
	public function action_404() {		
		$this->template->title = "Not found";
		$this->template->content = View::forge('errorhandler/404');
	}
}