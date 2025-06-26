<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BaseAuthController extends Controller
{
    protected $guardName = 'web';
    protected $userModel = \App\Models\User::class;
    protected $userType = 'user';
    protected $redirectTo = '/dashboard';
    protected $loginView = 'auth.login';
    protected $registerView = 'auth.register';
    
    public function showLoginForm()
    {
        return view($this->loginView, ['userType' => $this->userType]);
    }
    
    public function showRegistrationForm()
    {
        return view($this->registerView, ['userType' => $this->userType]);
    }
}