<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Contracts\View\View;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;

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
        return view('coolify::spa');
    }
}
