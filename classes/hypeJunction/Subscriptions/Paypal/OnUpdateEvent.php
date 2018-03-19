<?php

namespace hypeJunction\Subscriptions\Paypal;

use Elgg\Event;
use hypeJunction\Subscriptions\SubscriptionPlan;
use PayPal\Api\Plan;

class OnUpdateEvent {

	/**
	 * Sync plan updates
	 *
	 * @param Event $event Event
	 *
	 * @return bool|null
	 */
	public function __invoke(Event $event) {

		$entity = $event->getObject();
		if (!$entity instanceof SubscriptionPlan) {
			return null;
		}

		if ($entity->getVolatileData('is_import')) {
			return null;
		}

		try {
			$subs = elgg()->{'subscriptions.paypal'};
			/* @var $subs \hypeJunction\Subscriptions\Paypal\PaypalSubscriptionsService */

			if (!$subs->exportPlan($entity)) {
				return false;
			}
		} catch (\Exception $ex) {
			register_error($ex->getMessage());

			return false;
		}

	}
}