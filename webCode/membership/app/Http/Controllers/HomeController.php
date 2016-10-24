<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PayPal\PayPalService;
use Illuminate\Support\Facades\Redirect;

use App\Models\Member;
use App\Models\MemberTier;
use App\Models\PaymentProvider;
use App\Models\Resource;

use Session;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('home');
    }

    public function addMember(Member $member)
    {
        $tiers = MemberTier::pluck('description', 'id');
        $providers = PaymentProvider::pluck('description', 'id');

        $member->memberTier = MemberTier::where('description', 'Standard')->first();
        $member->paymentProvider = PaymentProvider::where('description', 'Paypal')->first();
        
        return view('home.registerMember', compact('member', 'tiers', 'providers'));
    }

    public function confirmMember(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|unique:members|max:255',
            'rfid' => 'required|unique:members|max:50'
        ]);
        
        if ($request->payment_provider_id != 2)
        {
            Session::flash('error', 'Only PayPal is supported at this time, so an administrator will need to manually add this member.');

            return Redirect::back()
                ->withInput();
        }


        $paypal = new PayPalService;
        
        $response = $paypal->findMember($request->email);

        if ($response->status != "Success")
        {
            Session::flash('error', 'Key not activated.  There are no recorded payments in the last month from '.$request->email.'.');

            return Redirect::back()
                ->withInput();       
        }

        $input = $request->all();
        
        $member = new Member;
        $member->fill($input);
        
        $member->member_status_id = 1;
        $member->name = $response->name;

        if ($response->amount == 50.00){
            $member->member_tier_id = 3;
        }
        else if ($response->amount == 30.00)
        {
            $member->member_tier_id = 2;
        }
        else if ($respone->amount == 20.00)
        {
            $member->member_tier_id = 1;
        }

        session(['memberToAdd' => $member]);

        $tier = MemberTier::find($member->member_tier_id);
        $provider = PaymentProvider::find($request->payment_provider_id);

        return view('home.confirm', compact('member', 'tier', 'provider'));        
    }

    public function storeMember(Request $request)
    {
        if ($request->session()->exists('memberToAdd')) 
        {
            $member = $request->session()->pull('memberToAdd');

            $member->save();

            $member->resources()->attach([1,2]);
            
            // redirect
            Session::flash('message', 'Successfully registered member and activated key.');
        }
        else
        {
            Session::flash('error', 'Unable to register member.  Please try again.');   
        }
        return redirect('/');
    }

}
