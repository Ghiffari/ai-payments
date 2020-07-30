<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2020
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


/**
 * mpay24 payment provider
 *
 * @package MShop
 * @subpackage Service
 */
class Mpay24
	extends \Aimeos\MShop\Service\Provider\Payment\OmniPay
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
	/**
	 * Executes the payment again for the given order if supported.
	 * This requires support of the payment gateway and token based payment
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @return void
	 */
	public function repay( \Aimeos\MShop\Order\Item\Iface $order )
	{
		$base = $this->getOrderBase( $order->getBaseId(), \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS );

		if( ( $cfg = $this->getCustomerData( $base->getCustomerId(), 'repay' ) ) === null )
		{
			$msg = sprintf( 'No reoccurring payment data available for customer ID "%1$s"', $base->getCustomerId() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		if( !isset( $cfg['token'] ) )
		{
			$msg = sprintf( 'No payment token available for customer ID "%1$s"', $base->getCustomerId() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		$data = array(
			'transactionId' => $order->getId(),
			'currency' => $base->getPrice()->getCurrencyId(),
			'amount' => $this->getAmount( $base->getPrice() ),
			'cardReference' => $cfg['token'],
			'paymentPage' => false,
			'language' => 'en',
		);

		$provider = \Omnipay\Omnipay::create( 'Mpay24_Backend' );
		$provider->setTestMode( (bool) $this->getValue( 'testmode', false ) );
		$provider->initialize( $this->getServiceItem()->getConfig() );
		$response = $provider->purchase( $data )->send();

		if( $response->isSuccessful() )
		{
			$this->setOrderData( $order, ['TRANSACTIONID' => $response->getTransactionReference()] );
			$this->saveOrder( $order->setPaymentStatus( \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED ) );
		}
		else
		{
			$msg = sprintf( 'Token based payment failed with code "%1$s" and message "%2$s": %3$s',
				$response->getCode(), $response->getMessage(), print_r( $response->getData(), true ) );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}
	}
}
