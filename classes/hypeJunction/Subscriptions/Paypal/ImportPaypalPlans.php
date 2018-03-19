<?php

namespace hypeJunction\Subscriptions\Paypal;

use Elgg\Database\QueryBuilder;
use Elgg\Http\ResponseBuilder;
use Elgg\HttpException;
use Elgg\Request;
use hypeJunction\Subscriptions\SubscriptionPlan;
use PayPal\Api\Plan;

class ImportPaypalPlans {

	/**
	 * Import paypal subscriptions
	 *
	 * @param Request $request
	 *
	 * @return ResponseBuilder
	 * @throws \Exception
	 */
	public function __invoke(Request $request) {

		$paypal = elgg()->paypal;
		/* @var $paypal \hypeJunction\Paypal\PaypalClient */

		$paypal_subscriptions = elgg()->{'subscriptions.paypal'};
		/* @var $paypal_subscriptions \hypeJunction\Subscriptions\Paypal\PaypalSubscriptionsService */

		$limit = 10;

		$has_more = true;
		$page = 0;

		$imported = 0;
		$exported = 0;

		try {
			while ($has_more) {
				$plans = Plan::all([
					'page' => $page,
					'page_size' => $limit,
				], $paypal->getApiContext());

				if (empty($plans->plans)) {
					$has_more = false;
					continue;
				}

				foreach ($plans->plans as $plan) {
					$paypal_subscriptions->importPlan($plan);
					$imported++;
				}

				$page++;
				$has_more = $plans->total_pages < $page;
			}
		} catch (\Exception $ex) {
			throw new HttpException($ex->getMessage());
		}

		elgg_call(ELGG_IGNORE_ACCESS | ELGG_SHOW_DISABLED_ENTITIES, function () use (&$exported, $paypal_subscriptions) {
			$plans = elgg_get_entities([
				'types' => 'object',
				'subtypes' => SubscriptionPlan::SUBTYPE,
				'wheres' => function (QueryBuilder $qb) {
					$qb->joinMetadataTable('e', 'guid', 'paypal_id', 'left', 'paypal_id');

					return $qb->compare('paypal_id.value', 'IS NULL');
				},
				'batch' => true,
				'limit' => 0,
				'batch_inc_offset' => false,
			]);

			foreach ($plans as $plan) {
				/* @var $plan SubscriptionPlan */

				try {
					if ($paypal_subscriptions->exportPlan($plan)) {
						$exported++;
					}
				} catch (\Exception $ex) {
					register_error($ex->getMessage());

					return false;
				}
			}
		});

		return elgg_ok_response('', elgg_echo('subscriptions:paypal:import:success', [$imported, $exported]));
	}


}