<?php

namespace hypeJunction\Subscriptions\Paypal;

use Elgg\EntityNotFoundException;
use Elgg\Http\ResponseBuilder;
use Elgg\Request;
use hypeJunction\Subscriptions\SubscriptionPlan;

class CreateAgreementAction {

	/**
	 * Checkout with paypal
	 *
	 * @param Request $request Request
	 *
	 * @return ResponseBuilder
	 * @throws \Exception
	 */
	public function __invoke(Request $request) {

		return elgg_call(ELGG_IGNORE_ACCESS, function () use ($request) {

			$plan = $request->getEntityParam('plan_guid');

			if (!$plan instanceof SubscriptionPlan) {
				throw new EntityNotFoundException();
			}

			$paypal_adapter = elgg()->{'subscriptions.gateways.paypal'};

			/* @var $paypal_adapter PaypalRecurringPaymentGateway */

			if ($agreement = $paypal_adapter->createAgreement($plan, $request->getParams())) {
				$approval_url = $agreement->getApprovalLink();
				$query = parse_url($approval_url, PHP_URL_QUERY);
				parse_str($query, $query_parts);
				return elgg_ok_response([
					'payment' => [
						'id' => elgg_extract('token', $query_parts),
					],
				]);
			}

			return elgg_error_response(elgg_echo('payments:paypal:pay:failed'));
		});
	}
}