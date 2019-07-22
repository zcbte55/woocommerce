<?php
/**
 * Unit tests for gateways.
 *
 * @package WooCommerce\Tests\Gateways
 */
class WC_Tests_Gateways extends WC_Unit_Test_Case {

	/**
	 * Test for supports() method.
	 */
	public function test_supports() {
		$gateway = new WC_Mock_Payment_Gateway();

		$this->assertTrue( $gateway->supports( 'products' ) );
		$this->assertFalse( $gateway->supports( 'made-up-feature' ) );
	}

	/**
	 * Test for supports() method.
	 */
	public function test_can_refund_order() {
		$gateway = new WC_Mock_Payment_Gateway();
		$order   = WC_Helper_Order::create_order();

		$order->set_payment_method( 'mock' );
		$order->set_transaction_id( '12345' );
		$order->save();

		$this->assertFalse( $gateway->can_refund_order( $order ) );

		$gateway->supports[] = 'refunds';

		$this->assertTrue( $gateway->can_refund_order( $order ) );
	}

	/**
	 * Test for PayPal supports() method.
	 */
	public function test_paypal_can_refund_order() {
		$gateway = new WC_Gateway_Paypal();
		$order   = WC_Helper_Order::create_order();

		$order->set_payment_method( 'paypal' );
		$order->set_transaction_id( '12345' );
		$order->save();

		// Refunds won't work without credentials.
		$this->assertFalse( $gateway->can_refund_order( $order ) );

		// Add API credentials.
		$settings = array(
			'testmode'              => 'yes',
			'sandbox_api_username'  => 'test',
			'sandbox_api_password'  => 'test',
			'sandbox_api_signature' => 'test',
		);
		update_option( 'woocommerce_paypal_settings ', $settings );
		$gateway = new WC_Gateway_Paypal();
		$this->assertTrue( $gateway->can_refund_order( $order ) );

		// Refund requires transaction ID.
		$order->set_transaction_id( '' );
		$order->save();
		$this->assertFalse( $gateway->can_refund_order( $order ) );
	}

	/**
	 * Test BACS gateway settings
	 *
	 * @return void
	 */
	public function test_bacs_gateway() {
		$_POST['bacs_account_name'][0]   = 'test';
		$_POST['bacs_account_number'][0] = '123';
		$_POST['bacs_bank_name'][0]      = 'bank';
		$_POST['bacs_sort_code'][0]      = '123';
		$_POST['bacs_iban'][0]           = '123';
		$_POST['bacs_bic'][0]            = '123';

		$gateway = new WC_Gateway_BACS();

		// Test saving account details.
		$gateway->save_account_details();
		$save_option = get_option( 'woocommerce_bacs_accounts' );
		$this->assertSame( 'test', $save_option[0]['account_name'] );

		// Test HTML generation.
		$html = $gateway->generate_account_details_html();
		$this->assertNotFalse( strpos( $html, 'name="bacs_sort_code[0]"' ) );

		// Test thankyou.
		$order = WC_Helper_Order::create_order();
		ob_start();
		$gateway->thankyou_page( $order->get_id() );
		$html = ob_get_clean();
		$this->assertNotFalse( strpos( $html, 'woocommerce-bacs-bank-details' ) );

	}


}

