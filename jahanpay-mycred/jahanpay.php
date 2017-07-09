<?php
@session_start();
/*
Plugin Name: jahanpay Gateway for MyCred
Plugin URI: http://jahanpay.me
Description: jahanpay Gateway for MyCred
Version: 1.0
Author: ModuleBank
Author URI: http://modulebank.ir/
*/

//error_reporting(E_ALL);
//ini_set('display_errors',1);

add_action('init', 'mycred_jahanpay_init', 0);
function mycred_jahanpay_init()
{
	add_filter( 'mycred_setup_gateways', 'add_myCRED_Payment_Gateway_jahanpay' );
	function add_myCRED_Payment_Gateway_jahanpay($gateways)
	{
		$gateways['jahanpay'] = array(
			'title'    => 'jahanpay',
			'callback' => array('myCRED_Payment_Gateway_jahanpay')
		);
		return $gateways;
	}
	if (class_exists('myCRED_Payment_Gateway'))
	{
		class myCRED_Payment_Gateway_jahanpay extends myCRED_Payment_Gateway
		{
			function __construct($gateway_prefs)
			{
				$types = mycred_get_types();
				$default_exchange = array();
				foreach ( $types as $type => $label )
					$default_exchange[ $type ] = 1;
				parent::__construct(array(
						'id'               => 'jahanpay',
						'label'            => 'jahanpay',
						'gateway_logo_url' => plugins_url( 'assets/images/jahanpay.png', myCRED_PURCHASE ),
						'defaults'         => array(
							'jahanpay_api'   => '',
							'exchange'      => $default_exchange
						)
					), $gateway_prefs);
			}
			function buy()
			{
				if ( ! isset( $this->prefs['jahanpay_api'] ) || empty( $this->prefs['jahanpay_api'] ) )
					wp_die( __( 'Please setup this gateway before attempting to make a purchase!', 'mycred' ) );
				$api = $this->prefs['jahanpay_api'];
				$type = $this->get_point_type();
				$mycred = mycred( $type );
				$amount = $mycred->number( $_REQUEST['amount'] );
				$amount = abs( $amount );
				$cost = $this->get_cost( $amount, $type );
				$to = $this->get_to();
				$from = $this->current_user_id;
				if ( isset( $_REQUEST['revisit'] ) )
				{
					$this->transaction_id = strtoupper( $_REQUEST['revisit'] );
				}
				else
				{
					$post_id = $this->add_pending_payment( array( $to, $from, $amount, $cost, $this->prefs['currency'], $type ) );
					$this->transaction_id = get_the_title( $post_id );
				}
				try
				{
					date_default_timezone_set("Asia/Tehran");
					$client = new SoapClient("http://www.jpws.me/directservice?wsdl");
					$res = $client->requestpayment($api, (int)$cost, $this->callback_url()."&custom=".$this->transaction_id, $this->transaction_id);	
					if ($res['result']&&$res['result'] ==1)
					{
						
			
						$_SESSION['jPrice'] = (int)$cost;
						$_SESSION['jAU'] = $res['au'];
						  echo ('<div style="display:none;">'.$res['form'].'</div><script>document.forms["jahanpay"].submit();</script>');
					}
					else
					{
						echo 'Error:'. $res['result'];
					}
				}
				catch (SoapFault $ex)
				{
					echo  'Error: '.$ex->getMessage();
				}
				unset( $this );
				exit;
			}
			function process()
			{
				if ( isset( $_REQUEST['custom'] ) && isset( $_SESSION['jResNum'] ) && isset( $_SESSION['jPrice'] ) && isset( $_SESSION['jAU'] ) )
				{
					$pending_post_id = sanitize_key( $_REQUEST['custom'] );
					$pending_payment = $this->get_pending_payment( $pending_post_id );
					if ( $pending_payment !== false )
					{
						
						$time = $_REQUEST['custom'];
						$cost = $_SESSION['jPrice'];
						$au = $_SESSION['jAU'];
						$new_call = array();
						$api = $this->prefs['jahanpay_api'];
						try
						{
							date_default_timezone_set("Asia/Tehran");
                            $client = new SoapClient("http://www.jpws.me/directservice?wsdl");
                            $res = $client->verification($api , $cost , $au , $time, $_POST + $_GET );
							if (!empty($res['result']) AND $res['result']==1)
							{
								if ( $this->complete_payment( $pending_payment, $au ) )
								{
									$this->trash_pending_payment( $pending_post_id );
									header('location: '.$this->get_thankyou());
									exit;die;
								}
								else
								{
									$new_call[] = __( 'Failed to credit users account.', 'mycred' );
								}
							}
							else
							{
								$new_call[] = __( 'verify error('.$res['result'].').', 'mycred' );
							}
						}
						catch (SoapFault $ex)
						{
							$new_call[] = __( 'Error: '.$ex->getMessage(), 'mycred' );
						}
					}
					$this->log_call( $pending_post_id, $new_call );
					   	header('location: '.$this->get_cancelled( $_REQUEST['custom']));
					exit;die;
								unset($_SESSION['jPrice']);
			unset($_SESSION['jAU']);
				}
			}


			function preferences()
			{
				?>
				<label class="subheader" for="<?php echo $this->field_id('jahanpay_api'); ?>">API</label><ol><li><div class="h2"><input type="text" name="<?php echo $this->field_name('jahanpay_api'); ?>" id="<?php echo $this->field_id('jahanpay_api'); ?>" value="<?php echo $this->prefs['jahanpay_api']; ?>" class="long" /></div></li></ol>
				<label class="subheader"><?php _e( 'Exchange Rates', 'mycred' ); ?></label><ol><?php $this->exchange_rate_setup(); ?></ol>
				<?php
			}
		}
	}
}

?>