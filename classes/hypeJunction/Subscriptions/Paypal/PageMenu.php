<?php

namespace hypeJunction\Subscriptions\Paypal;

use Elgg\Hook;
use ElggMenuItem;

class PageMenu {

	/**
	 * Setup page menu
	 *
	 * @elgg_plugin_hook register menu:page
	 *
	 * @param Hook $hook Hook
	 *
	 * @return ElggMenuItem[]|null
	 */
	public function __invoke(Hook $hook) {

		$menu = $hook->getValue();
		/* @var $menu ElggMenuItem[] */

		if (elgg_in_context('admin')) {
			$menu[] = ElggMenuItem::factory([
				'name' => 'subscriptions:paypal:import',
				'parent_name' => 'subscriptions',
				'href' => elgg_generate_action_url('subscriptions/paypal/import'),
				'text' => elgg_echo('subscriptions:paypal:import'),
				'icon' => 'import',
				'section' => 'configure',
				'confirm' => true,
			]);
		}

		return $menu;
	}
}