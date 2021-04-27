<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\File;
use App\Http\Controllers\Controller;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\VerifyWebhookSignature;
use PayPal\Api\WebhookEvent;
use Log;
use App\Models\User;
use App\Models\Subscriptions;
use App\Models\Transactions;
use Illuminate\Support\Facades\Input;
use Redirect;
use URL;
use Session;
use Illuminate\Support\Facades\Auth;
class PayPalController extends Controller
{
    private $_api_context;

    public function __construct()
    {
        
        $paypal_conf = \Config::get('paypal');
         $this->_api_context = new ApiContext(new OAuthTokenCredential(
             $paypal_conf['client_id'],
             $paypal_conf['secret'])
         );
         $this->_api_context->setConfig($paypal_conf['settings']);
    }
    public function fetch_subscriptions(Request $request){
        $results = array();
        $user =  User::where('id', 1)->first(); //Auth::user();
        $subscriptions= Subscriptions::with('transactions')->get();
        foreach ($subscriptions as $key => $value) {
             $results = array(
                'id' => $value->id,
                'agreement_id' => $value->agreement_id,
                'status' => $value->status,
                'amount' =>$value->amount,
                'user' =>  $user,
                'created_at' =>$value->created_at,
                'transactions' => $value->transactions,


             );

        }
        return response()->json([
            'data' => $results
            // 'data' => [
            //     // 'id' => $value->id,

            // ]
        ])->setStatusCode(201);


    }
    public function testwebhook(Request $request){
        Log::info($request->all());
        if($request->event_type == 'PAYMENT.SALE.COMPLETED'){
            $subscriptions = new Subscriptions();
            $subscriptions->agreement_id = $request->billing_agreement_id;
            $subscriptions->user_id = Auth::user()->id;
            $subscriptions->status = $request->resource['state'];
            $subscriptions->amount = $request->resource['amount']['total'];
            $subscriptions->save();


        }
        if($request->event_type == 'BILLING.SUBSCRIPTION.PAYMENT.FAILED'){
            $transactions = Transactions::where('subscription_id', $request->resource['id'])->first();
            $transactions->event_type = $request->event_type;
            $transactions->plan_id = $request->resource['plan_id'];
            $transactions->plan_status = $request->resource['status'];
            $transactions->subscription_status = 'FAILED';
            $transactions->save();
        }
        if($request->event_type == 'BILLING.SUBSCRIPTION.CANCELLED'){
            $transactions = Transactions::where('subscription_id', $request->resource['id'])->first();
            $transactions->event_type = $request->event_type;
            $transactions->plan_id = $request->resource['plan_id'];
            $transactions->plan_status = $request->resource['status'];
            $transactions->subscription_status = 'CANCELLED';
            $transactions->save();
        }
        if($request->event_type == 'BILLING.SUBSCRIPTION.SUSPENDED'){
            $transactions = Transactions::where('subscription_id', $request->resource['id'])->first();
            $transactions->event_type = $request->event_type;
            $transactions->plan_id = $request->resource['plan_id'];
            $transactions->plan_status = $request->resource['status'];
            $transactions->subscription_status = 'SUSPENDED';
            $transactions->save();
        }
      

    }
}