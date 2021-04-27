<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\File;
use App\Http\Controllers\Controller;

use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\VerifyWebhookSignature;
use PayPal\Api\WebhookEvent;
use Log;
use App\Models\User;
use PayPal\Rest\ApiContext;
use PayPal\Api\Agreement;
use PayPal\Api\Payer;
use PayPal\Api\Plan;
use PayPal\Api\PaymentDefinition;
use PayPal\Api\PayerInfo;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Amount;
use PayPal\Api\Transaction;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use Illuminate\Support\Facades\Input;
use Redirect;
use URL;
use Session;

class PayPalController extends Controller
{
    private $_api_context;

    public function __construct()
    {
        // $this->_api_context = new ApiContext(
        //     new OAuthTokenCredential(config('paypal.client_id'), config('paypal.secret'))
        // );
        // $this->_api_context->setConfig(config('paypal'));
        $paypal_conf = \Config::get('paypal');
         $this->_api_context = new ApiContext(new OAuthTokenCredential(
             $paypal_conf['client_id'],
             $paypal_conf['secret'])
         );
         $this->_api_context->setConfig($paypal_conf['settings']);
    }

    /**
     * Webhook (Payment sale completed)
     * 
     * @param Request $request 
     * @return void
     */
    // public function index(){
    //     die('est');
    // }
    public function payWithpaypal(){
        $amountToBePaid = 100;
            $payer = new Payer();
            $payer->setPaymentMethod('paypal');
        
            $item_1 = new Item();
            $item_1->setName('Mobile Payment') /** item name **/
                    ->setCurrency('USD')
                    ->setQuantity(1)
                    ->setPrice($amountToBePaid); /** unit price **/
        
            $item_list = new ItemList();
            $item_list->setItems(array($item_1));
        
            $amount = new Amount();
            $amount->setCurrency('USD')
                    ->setTotal($amountToBePaid);
            $redirect_urls = new RedirectUrls();
            /** Specify return URL **/
            $redirect_urls->setReturnUrl(URL::route('status'))
                        ->setCancelUrl(URL::route('status'));
            
            $transaction = new Transaction();
            $transaction->setAmount($amount)
                    ->setItemList($item_list)
                    ->setDescription('Your transaction description');   
        
            $payment = new Payment();
            $payment->setIntent('Sale')
                    ->setPayer($payer)
                    ->setRedirectUrls($redirect_urls)
                    ->setTransactions(array($transaction));
            try {
                $payment->create($this->_api_context);
            } catch (\PayPal\Exception\PPConnectionException $ex) {
                die('tets');
                session()->flash('message', 'Payment failed');  
                session()->flash('alert-class', 'alert-danger'); 
                return Redirect::route('home');
                // Session::put('message', 'Some error occur, sorry for inconvenient');
                // return Redirect::route('home');
                // if (\Config::get('app.debug')) {
                //     \Session::put('error', 'Connection timeout');
                //     return Redirect::route('home');
                // } else {
                //     \Session::put('error', 'Some error occur, sorry for inconvenient');
                //     return Redirect::route('home');
                // }
            }
           foreach ($payment->getLinks() as $link) {
                if ($link->getRel() == 'approval_url') {
                $redirect_url = $link->getHref();
                break;
                }
            }
            
            /** add payment ID to session **/
            \Session::put('paypal_payment_id', $payment->getId());
            if (isset($redirect_url)) {
                /** redirect to paypal **/
                return Redirect::away($redirect_url);
            }
        
            \Session::put('error', 'Unknown error occurred');
            return Redirect::route('home');
    }
    public function getPaymentStatus(Request $request)
    {
      /** Get the payment ID before session clear **/
    
      $payment_id = Session::get('paypal_payment_id');
      /** clear the session payment ID **/
      Session::forget('paypal_payment_id');
      if (empty($request->PayerID) || empty($request->token)) {
        session()->flash('message', 'Payment failed');
         session()->flash('alert-class', 'alert-danger'); 
        return Redirect::route('home');
     
      }
      $payment = Payment::get($payment_id, $this->_api_context);
      $execution = new PaymentExecution();
      $execution->setPayerId($request->PayerID);
      /**Execute the payment **/
      $result = $payment->execute($execution, $this->_api_context);
      
      if ($result->getState() == 'approved') {
         session()->flash('success', 'Payment success');
          session()->flash('alert-class', 'alert-success');
         return Redirect::route('home');
      }
    //   session()->flash('error', 'Payment failed');
       session()->flash('message', 'Payment failed');
       session()->flash('alert-class', 'alert-danger'); 
      return Redirect::route('home');
    }
  
    /**
     * Responds with a welcome message with instructions
     *
     * @return \Illuminate\Http\Response
     */
    public function success(Request $request)
    {
        $response = $provider->getExpressCheckoutDetails($request->token);
  
        if (in_array(strtoupper($response['ACK']), ['SUCCESS', 'SUCCESSWITHWARNING'])) {
            dd('Your payment was successfully. You can create success page here.');
        }
  
        dd('Something is wrong.');
    }
    public function testwebhook(Request $request){
        Log::info($request->all());
        if($request->event_type == 'PAYMENT.SALE.COMPLETED'){


        }
        if($request->event_type == 'BILLING.SUBSCRIPTION.PAYMENT.FAILED'){

        }
        if($request->event_type == 'BILLING.SUBSCRIPTION.CANCELLED'){

        }
        if($request->event_type == 'BILLING.SUBSCRIPTION.SUSPENDED'){

        }
      

    }
    public function createwebhook(){

        // Create a new instance of Webhook object
        $webhook = new \PayPal\Api\Webhook();

        // # Basic Information
        //     {
        //         "url":"https://requestb.in/10ujt3c1",
        //         "event_types":[
        //            {
        //                "name":"PAYMENT.AUTHORIZATION.CREATED"
        //            },
        //            {
        //                "name":"PAYMENT.AUTHORIZATION.VOIDED"
        //            }
        //         ]
        //      }

        $webhook->setUrl("https://requestb.in/10ujt3c1?uniqid=" . uniqid());

        // # Event Types
        // Event types correspond to what kind of notifications you want to receive on the given URL.
        $webhookEventTypes = array();
        $webhookEventTypes[] = new \PayPal\Api\WebhookEventType(
            '{
                "name":"PAYMENT.AUTHORIZATION.CREATED"
            }'
        );
        $webhookEventTypes[] = new \PayPal\Api\WebhookEventType(
            '{
                "name":"PAYMENT.AUTHORIZATION.VOIDED"
            }'
        );
        $webhook->setEventTypes($webhookEventTypes);

        // For Sample Purposes Only.
        $request = clone $webhook;

        // ### Create Webhook
        try {
            $output = $webhook->create($this->_api_context);
        } catch (Exception $ex) {
            // ^ Ignore workflow code segment
            if ($ex instanceof \PayPal\Exception\PayPalConnectionException) {
                $data = $ex->getData();
                // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
                print_r("Created Webhook Failed. Checking if it is Webhook Number Limit Exceeded. Trying to delete all existing webhooks", "Webhook", "Please Use <a style='color: red;' href='DeleteAllWebhooks.php' >Delete All Webhooks</a> Sample to delete all existing webhooks in sample", $request, $ex);
                if (strpos($data, 'WEBHOOK_NUMBER_LIMIT_EXCEEDED') !== false) {
                    require 'DeleteAllWebhooks.php';
                    try {
                        $output = $webhook->create($this->_api_context);
                    } catch (Exception $ex) {
                        // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
                    print_r($ex);
                        exit(1);
                    }
                } else {
                    // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
                    print_r($ex);
                    exit(1);
                }
            } else {
                // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
                print_r($ex);
                exit(1);
            }
            // Print Success Result
        }


      return $output;
    }
    public function getwebhook(){
       
        $webhookId = '87406266S3919381Y';
        try {
            $output = \PayPal\Api\Webhook::get($webhookId, $this->_api_context);
        } catch (Exception $ex) {
            print_r("Get a Webhook", "Webhook", null, $webhookId, $ex);
            exit(1);
        }
        echo "<pre>";print_r($output);
        die('tets');
    }
    public function webhooksPaymentSaleCompleted(Request $request)
    {
        /** @var string $request_body */
        $request_body = file_get_contents('php://input');

        /** @var array $headers */
        $headers = $request->headers->all();
        $headers = array_change_key_case($headers, CASE_UPPER);

        $signature_verification = new VerifyWebhookSignature();
        $signature_verification->setAuthAlgo($headers['PAYPAL-AUTH-ALGO'][0]);
        $signature_verification->setTransmissionId($headers['PAYPAL-TRANSMISSION-ID'][0]);
        $signature_verification->setCertUrl($headers['PAYPAL-CERT-URL'][0]);
        // get the webhook ID in config file
        $signature_verification->setWebhookId(config('paypal.webhooks.payment_sale_completed')); // Note that the Webhook ID must be a currently valid Webhook that you created with your client ID/secret.
        $signature_verification->setTransmissionSig($headers['PAYPAL-TRANSMISSION-SIG'][0]);
        $signature_verification->setTransmissionTime($headers['PAYPAL-TRANSMISSION-TIME'][0]);

        $signature_verification->setRequestBody($request_body);
        $req = clone $signature_verification;

        // for error message, I log it into a file for debug purpose
        $exception_log_file = storage_path('logs/paypal-exception.log');

        try {
            /** @var \PayPal\Api\VerifyWebhookSignatureResponse $output */
            $output = $signature_verification->post($this->_api_context);
        } catch (\Exception $ex) {
            file_put_contents($exception_log_file, $ex->getMessage());
            exit(1);
        }
        $status = $output->getVerificationStatus(); // 'SUCCESS' or 'FAILURE'
        // if the status is not success, then end here
        if (strtoupper($status) !== 'SUCCESS') exit(1);

        $json = json_decode($request_body, 1);

        // Because PayPal don't let us to add in custom data in JSON form, so I add it to a field 'custom' as encoded string. Now decode to get the data back
        $custom_data = json_decode($json['resource']['custom'], 1);
        $user = User::find($custom_data['user_id']); // to get the User

        // save the payment info

        // generate invoice

        // email to user

        echo $status; // at the end must echo the status
        exit(1);
    }
}