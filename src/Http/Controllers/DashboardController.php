<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Exceptions\CoolifyApiException;

class DashboardController extends Controller
{
    public function __construct(
        protected ApplicationRepository $applications,
        protected DatabaseRepository $databases,
        protected DeploymentRepository $deployments
    ) {
        parent::__construct();
    }

    /**
     * Display the Coolify dashboard.
     */
    public function index(): View
    {
        return view('coolify::dashboard');
    }
}
